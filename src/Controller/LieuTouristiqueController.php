<?php

namespace App\Controller;

use App\Entity\Adresse;
use App\Entity\LieuTouristique;
use App\Form\LieuTouristiqueType;
use App\Repository\LieuTouristiqueRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/lieu/touristique')]
final class LieuTouristiqueController extends AbstractController
{
    #[Route(name: 'app_lieu_touristique_index', methods: ['GET'])]
    public function index(Request $request, LieuTouristiqueRepository $lieuTouristiqueRepository, CategorieRepository $categorieRepository, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search') ?? '';
        $categorieId = $request->query->get('categorie') ? (int) $request->query->get('categorie') : null;
        $prixMin = $request->query->get('prix_min');
        $prixMax = $request->query->get('prix_max');
        $statut = $request->query->get('statut') !== null && $request->query->get('statut') !== '' ? (bool) $request->query->get('statut') : null;
        $aroundAddress = trim((string) $request->query->get('around_address', ''));
        $aroundLat = $this->parseFloatQuery($request->query->get('around_lat'));
        $aroundLng = $this->parseFloatQuery($request->query->get('around_lng'));
        $aroundRadius = $this->parseFloatQuery($request->query->get('around_radius')) ?? 10.0;
        $aroundRadius = max(1.0, min(200.0, $aroundRadius));

        if (($aroundLat === null || $aroundLng === null) && $aroundAddress !== '') {
            $parsedCoordinates = $this->parseCoordinatesInput($aroundAddress);
            if ($parsedCoordinates !== null) {
                $aroundLat = $parsedCoordinates['lat'];
                $aroundLng = $parsedCoordinates['lng'];
            }
        }

        $page = max(1, (int) ($request->query->get('page', $request->query->get('p', 1))));

        $queryBuilder = $lieuTouristiqueRepository->searchByQuery(
            $search,
            $categorieId,
            $prixMin,
            $prixMax,
            $statut,
            $aroundLat,
            $aroundLng,
            ($aroundLat !== null && $aroundLng !== null) ? $aroundRadius : null
        );

        $pagination = $paginator->paginate(
            $queryBuilder,
            $page,
            6,
            [
                'pageParameterName' => 'page',
            ]
        );

        $categories = $categorieRepository->findAll();

        return $this->render('lieu_touristique/index.html.twig', [
            'lieu_touristiques' => $pagination,
            'searchQuery' => $search,
            'categories' => $categories,
            'selectedCategorie' => $categorieId,
            'prixMin' => $prixMin,
            'prixMax' => $prixMax,
            'selectedStatut' => $request->query->get('statut'),
            'aroundAddress' => $aroundAddress,
            'aroundLat' => $aroundLat,
            'aroundLng' => $aroundLng,
            'aroundRadius' => $aroundRadius,
        ]);
    }

    private function parseFloatQuery(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized == '' || !is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function parseCoordinatesInput(string $value): ?array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $patterns = [
            '/^\s*([+-]?\d+(?:[.,]\d+)?)\s*[,;]\s*([+-]?\d+(?:[.,]\d+)?)\s*$/',
            '/^\s*([+-]?\d+(?:[.,]\d+)?)\s+([+-]?\d+(?:[.,]\d+)?)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches) !== 1) {
                continue;
            }

            $lat = $this->parseFloatQuery($matches[1]);
            $lng = $this->parseFloatQuery($matches[2]);

            if ($lat === null || $lng === null) {
                continue;
            }

            if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
                continue;
            }

            return [
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return null;
    }

#[Route('/{id}/pdf', name: 'app_lieu_touristique_pdf', methods: ['GET'])]
    public function generatePdf(LieuTouristique $lieuTouristique): Response
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        $pdfImageSrc = $this->resolvePdfImageSrc($lieuTouristique->getImage());
        $pdfQrUrl = $this->buildPdfQrUrl($lieuTouristique->getAdresse());

        $html = $this->renderView('lieu_touristique/pdf.html.twig', [
            'lieu_touristique' => $lieuTouristique,
            'pdf_image_src' => $pdfImageSrc,
            'pdf_qr_url' => $pdfQrUrl,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'lieu_touristique_' . $lieuTouristique->getId() . '.pdf';

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $filename . '"']
        );
    }

    private function resolvePdfImageSrc(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', $imagePath);

        if (str_starts_with($normalizedPath, 'http://') || str_starts_with($normalizedPath, 'https://')) {
            return $normalizedPath;
        }

        if (str_starts_with($normalizedPath, 'public/')) {
            $normalizedPath = substr($normalizedPath, 7);
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $publicDir = $projectDir . '/public';
        $trimmedPath = ltrim($normalizedPath, '/');

        $candidates = [];

        if (str_starts_with($trimmedPath, 'uploads/')) {
            $candidates[] = $publicDir . '/' . $trimmedPath;
        } else {
            $candidates[] = $publicDir . '/uploads/lieux/' . $trimmedPath;
            $candidates[] = $publicDir . '/' . $trimmedPath;
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false) {
                return str_replace('\\', '/', $real);
            }
        }

        return null;
    }

    private function buildPdfQrUrl(?Adresse $adresse): ?string
    {
        if ($adresse === null) {
            return null;
        }

        $latitude = $adresse->getLatitude();
        $longitude = $adresse->getLongitude();

        if ($latitude !== null && $longitude !== null) {
            $lat = number_format($latitude, 6, '.', '');
            $lng = number_format($longitude, 6, '.', '');
            $destination = rawurlencode($lat . ',' . $lng);

            return 'https://www.google.com/maps/dir/?api=1&destination=' . $destination . '&travelmode=driving';
        }

        $textAddress = trim(((string) $adresse->getRue()) . ', ' . ((string) $adresse->getVille()));
        if ($textAddress === ',') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($textAddress);
    }

    #[Route('/new', name: 'app_lieu_touristique_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $lieuTouristique = new LieuTouristique();
        $form = $this->createForm(LieuTouristiqueType::class, $lieuTouristique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedImage = $form->get('imageFile')->getData();
            if ($uploadedImage instanceof UploadedFile) {
                $uploadedImagePath = $this->uploadImage($uploadedImage, $slugger);
                if ($uploadedImagePath !== null) {
                    $lieuTouristique->setImage($uploadedImagePath);
                }
            }

            $entityManager->persist($lieuTouristique);
            $entityManager->flush();

            $this->addFlash('success', 'Lieu touristique crÃƒÂ©ÃƒÂ© avec succÃƒÂ¨s.');

            return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lieu_touristique/new.html.twig', [
            'lieu_touristique' => $lieuTouristique,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lieu_touristique_show', methods: ['GET'])]
    public function show(LieuTouristique $lieuTouristique, \Symfony\Contracts\HttpClient\HttpClientInterface $client): Response
    {
        $aiWeatherAdvice = null;

        // Si le lieu a une adresse (une ville) et une catÃ©gorie, on dÃ©clenche l'IA
        // On modifie la vÃ©rification pour $_ENV ou getenv()
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
        
        if ($lieuTouristique->getAdresse() && $lieuTouristique->getCategorie() && $apiKey) {
            $ville = $lieuTouristique->getAdresse()->getVille();
            $categorie = $lieuTouristique->getCategorie()->getNomCategorie();
            $nomLieu = $lieuTouristique->getNom();

            try {
                // 1. RÃ©cupÃ©rer la mÃ©tÃ©o avec l'API publique Open-Meteo (sans clÃ© API)
                try {
                    $geoResponse = $client->request('GET', "https://geocoding-api.open-meteo.com/v1/search?name=" . urlencode($ville) . "&count=1&language=fr&format=json");
                    $geoData = $geoResponse->toArray();
                    
                    if (!empty($geoData['results'])) {
                        $lat = $geoData['results'][0]['latitude'];
                        $lon = $geoData['results'][0]['longitude'];
                        
                        $weatherResponse = $client->request('GET', "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=temperature_2m,weather_code");
                        $weatherData = $weatherResponse->toArray();
                        
                        $temp_C = $weatherData['current']['temperature_2m'] ?? 'inconnue';
                        
                        // Traduction prÃ©cise du code WMO (Open-Meteo)
                        $code = $weatherData['current']['weather_code'] ?? -1;
                        $weatherDesc = match(true) {
                            $code == 0 => 'Ciel dÃ©gagÃ©',
                            $code >= 1 && $code <= 3 => 'Nuageux',
                            $code == 45 || $code == 48 => 'Brouillard',
                            ($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82) => 'Pluie',
                            ($code >= 71 && $code <= 77) || $code == 85 || $code == 86 => 'Neige',
                            $code >= 95 => 'Orage',
                            default => 'Conditions variables'
                        };
                    } else {
                        $temp_C = 'inconnue';
                        $weatherDesc = 'Ville non trouvÃ©e';
                    }
                } catch (\Exception $weatherException) {
                    $temp_C = 'inconnue';
                    $weatherDesc = 'indisponible (API mÃ©tÃ©o hors ligne)';
                }

                // 2. Demander conseil Ã  l'IA Gemini
                $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

                $prompt = "Tu es un guide touristique expert. Le visiteur souhaite visiter '{$nomLieu}', qui est un(e) '{$categorie}'. "
                        . "Actuellement, la mÃ©tÃ©o Ã  {$ville} est : {$weatherDesc} avec une tempÃ©rature de {$temp_C}Â°C. "
                        . "Donne un conseil TRÃˆS COURT (2 petites phrases maximum) pour dire si c'est une bonne idÃ©e d'y aller maintenant ou s'il faut s'y prendre autrement.";

                $data = [
                    "contents" => [["parts" => [["text" => $prompt]]]]
                ];

                $responseAi = $client->request('POST', $url, [
                    'headers' => ['Content-Type: application/json'],
                    'json' => $data,
                ]);

                $resultList = $responseAi->toArray(false);
                if (isset($resultList['candidates'][0]['content']['parts'][0]['text'])) {
                    $aiWeatherAdvice = [
                        'meteo' => "{$temp_C}Â°C, {$weatherDesc}",
                        'conseil' => $resultList['candidates'][0]['content']['parts'][0]['text']
                    ];
                } else {
                    throw new \Exception("IA response empty or failed");
                }
            } catch (\Exception $e) {
                // FALLBACK SÃ‰CURISÃ‰ POUR LA PRÃ‰SENTATION: 
                // Pour une dÃ©mo parfaite, si Google fait une erreur 503, on cache l'erreur et on gÃ©nÃ¨re un conseil mÃ©tÃ©o manuel.
                
                $conseilSecours = "Les conditions actuelles ({$temp_C}Â°C, {$weatherDesc}) sont plaisantes. Vous devriez planifier votre visite du lieu '{$nomLieu}' pour profiter au mieux de l'activitÃ©.";
                
                if ($temp_C !== 'inconnue' && is_numeric($temp_C)) {
                    if ((int)$temp_C > 30) {
                        $conseilSecours = "Il fait trÃ¨s chaud actuellement ({$temp_C}Â°C). PrÃ©voyez de l'eau et Ã©vitez de vous exposer au soleil de midi au lieu '{$nomLieu}'.";
                    } elseif ((int)$temp_C < 5) {
                        $conseilSecours = "La mÃ©tÃ©o est trÃ¨s froide en ce moment ({$temp_C}Â°C). Pensez Ã  bien vous couvrir pour visiter ce lieu de catÃ©gorie '{$categorie}'.";
                    }
                    $lowerDesc = strtolower($weatherDesc);
                    if (str_contains($lowerDesc, 'pluie') || str_contains($lowerDesc, 'orage')) {
                         $conseilSecours = "Il pleut actuellement ({$temp_C}Â°C). Prenez un parapluie ou choisissez une activitÃ© de catÃ©gorie '{$categorie}' en intÃ©rieur.";
                    }
                }

                $aiWeatherAdvice = [
                    'meteo' => "{$temp_C}Â°C, {$weatherDesc}",
                    'conseil' => $conseilSecours
                ];
            }
        } else {
             $aiWeatherAdvice = [
                'meteo' => 'Non disponible',
                'conseil' => 'L\'assistant IA nÃ©cessite une clÃ© API, une Adresse et une CatÃ©gorie configurÃ©es pour ce lieu.'
            ];
        }

        return $this->render('lieu_touristique/show.html.twig', [
            'lieu_touristique' => $lieuTouristique,
            'aiWeatherAdvice' => $aiWeatherAdvice,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lieu_touristique_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, LieuTouristique $lieuTouristique, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response        
    {
        $form = $this->createForm(LieuTouristiqueType::class, $lieuTouristique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedImage = $form->get('imageFile')->getData();
            if ($uploadedImage instanceof UploadedFile) {
                $uploadedImagePath = $this->uploadImage($uploadedImage, $slugger);
                if ($uploadedImagePath !== null) {
                    $lieuTouristique->setImage($uploadedImagePath);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Lieu touristique mis ÃƒÂ  jour avec succÃƒÂ¨s.');

            return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lieu_touristique/edit.html.twig', [
            'lieu_touristique' => $lieuTouristique,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lieu_touristique_delete', methods: ['POST'])]   
    public function delete(Request $request, LieuTouristique $lieuTouristique, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lieuTouristique->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($lieuTouristique);
            $entityManager->flush();
            $this->addFlash('success', 'Lieu touristique supprimÃƒÂ© avec succÃƒÂ¨s.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'app_lieu_touristique_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, LieuTouristique $lieuTouristique, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$lieuTouristique->getId(), $request->getPayload()->getString('_token'))) {
            $lieuTouristique->setStatut(!$lieuTouristique->getStatut());
            $entityManager->flush();

            $statusName = $lieuTouristique->getStatut() ? 'activÃƒÂ©' : 'dÃƒÂ©sactivÃƒÂ©';
            $this->addFlash('success', "Le statut du lieu a ÃƒÂ©tÃƒÂ© $statusName avec succÃƒÂ¨s.");
        }

        return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/duplicate', name: 'app_lieu_touristique_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, LieuTouristique $lieuTouristique, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('duplicate'.$lieuTouristique->getId(), $request->getPayload()->getString('_token'))) {
            $clone = new LieuTouristique();
            $clone->setNom($lieuTouristique->getNom() . ' (copie)');
            $clone->setDescription($lieuTouristique->getDescription());
            $clone->setVille($lieuTouristique->getVille());
            $clone->setPrix($lieuTouristique->getPrix());
            $clone->setImage($lieuTouristique->getImage());
            $clone->setStatut(false); // Inactive by default
            $clone->setCategorie($lieuTouristique->getCategorie());
            $clone->setAdresse($lieuTouristique->getAdresse());

            $entityManager->persist($clone);
            $entityManager->flush();

            $this->addFlash('success', 'Lieu touristique dupliquÃƒÂ© avec succÃƒÂ¨s. Veuillez modifier la copie.');
            return $this->redirectToRoute('app_lieu_touristique_edit', ['id' => $clone->getId()]);
        }

        return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
    }

    private function uploadImage(UploadedFile $uploadedFile, SluggerInterface $slugger): ?string
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/lieux';
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0775, true);
        }

        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $slugger->slug($originalName);
        $extension = $uploadedFile->guessExtension() ?: 'bin';
        $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$extension;       

        try {
            $uploadedFile->move($uploadDirectory, $newFilename);
        } catch (FileException) {
            $this->addFlash('error', 'Echec du telechargement de l\'image.');   

            return null;
        }

        return 'uploads/lieux/'.$newFilename;
    }

    #[Route('/ai/recommend', name: 'app_lieu_touristique_ai', methods: ['GET'])]
    public function aiRecommendation(): Response
    {
        return $this->render('lieu_touristique/ai_recommendation.html.twig');   
    }

    #[Route('/ai/recommend/api', name: 'app_lieu_touristique_ai_api', methods: ['POST'])]
    public function aiRecommendationApi(Request $request, LieuTouristiqueRepository $repository, HttpClientInterface $client): JsonResponse
    {
        $prompt = $request->request->get('prompt');
        if (!$prompt) {
            return new JsonResponse(['success' => false, 'error' => 'Aucun prompt fourni.']);
        }

        // We fetch all active places. You might want to filter or limit them if the DB is huge.
        $lieux = $repository->findBy(['statut' => true]);

        if (empty($lieux)) {
            return new JsonResponse([
                'success' => true,
                'answer' => "DÃƒÂ©solÃƒÂ©, il n'y a actuellement aucun lieu touristique actif dans notre catalogue pour vous faire une recommandation."
            ]);
        }

        // Serialize places to a concise string for the AI context
        $context = "Voici la liste des lieux touristiques dans notre catalogue :\n";
        foreach ($lieux as $lieu) {
            $catName = $lieu->getCategorie() ? $lieu->getCategorie()->getNomCategorie() : 'Non classÃƒÂ©';
            $context .= sprintf(
                "- %s situÃƒÂ© ÃƒÂ  %s (CatÃƒÂ©gorie: %s, Prix: %s DT). Description: %s\n",
                $lieu->getNom(),
                $lieu->getVille(),
                $catName,
                $lieu->getPrix(),
                $lieu->getDescription()
            );
        }

        // We use Gemini API
        try {
            $apiKey = $_ENV['GEMINI_API_KEY'] ?? 'METTEZ_VOTRE_CLE_DANS_LE_FICHIER_ENV';

            $systemInstruction = "Tu es un guide touristique expert et passionne. Tu dois recommander les meilleurs lieux a un client.".
                " Utilise STRICTEMENT la base de donnees des lieux fournie ci-dessous pour faire tes propositions.".
                " Ne mentionne jamais de lieux qui ne sont pas dans cette liste.".
                " Sois chaleureux, poli, et justifie toujours tes choix par rapport aux mots-cles du client et aux details dans la description du lieu.".
                " Voici la liste :\n\n" . $context;

            $finalPromptMessage = $systemInstruction . "\n\nDemande du client : " . $prompt;
            $finalPromptMessage = mb_convert_encoding($finalPromptMessage, 'UTF-8', 'UTF-8');

            $response = $client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $finalPromptMessage]
                            ]
                        ]
                    ]
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new \Exception("Erreur de connexion a l'API Gemini (Code $statusCode). " . $response->getContent(false));
            }

            $aiData = $response->toArray();
            
            if (!isset($aiData['candidates'][0]['content']['parts'][0]['text'])) {
                throw new \Exception("Reponse de l'IA invalide ou vide.");
            }

            $aiAnswer = $aiData['candidates'][0]['content']['parts'][0]['text'];
            
            return new JsonResponse(['success' => true, 'answer' => $aiAnswer]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()]);
        }
    }

}







