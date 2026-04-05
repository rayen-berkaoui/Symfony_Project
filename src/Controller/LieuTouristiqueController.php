<?php

namespace App\Controller;

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

        $queryBuilder = $lieuTouristiqueRepository->searchByQuery($search, $categorieId, $prixMin, $prixMax, $statut);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            6 // items per page
        );

        $categories = $categorieRepository->findAll();

        return $this->render('lieu_touristique/index.html.twig', [
            'lieu_touristiques' => $pagination,
            'searchQuery' => $search,
            'categories' => $categories,
            'selectedCategorie' => $categorieId,
            'prixMin' => $prixMin,
            'prixMax' => $prixMax,
            'selectedStatut' => $request->query->get('statut')
        ]);
    }

    #[Route('/pdf', name: 'app_lieu_touristique_pdf', methods: ['GET'])]        
    public function generatePdf(LieuTouristiqueRepository $lieuTouristiqueRepository): Response
    {
        $lieux = $lieuTouristiqueRepository->findAll();

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        // Very basic simple style for PDF
        $html = "<h1>Liste des Lieux Touristiques</h1>";
        $html .= "<table border='1' width='100%' cellpadding='5'><tr><th>Nom</th><th>Ville</th><th>Prix</th><th>Statut</th></tr>";
        foreach ($lieux as $l) {
            $html .= "<tr><td>{$l->getNom()}</td><td>{$l->getVille()}</td><td>{$l->getPrix()} TND</td><td>" . ($l->getStatut() ? 'Actif' : 'Inactif') . "</td></tr>";
        }
        $html .= "</table>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="lieux_touristiques.pdf"']
        );
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

            $this->addFlash('success', 'Lieu touristique crÃ©Ã© avec succÃ¨s.');

            return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lieu_touristique/new.html.twig', [
            'lieu_touristique' => $lieuTouristique,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lieu_touristique_show', methods: ['GET'])]
    public function show(LieuTouristique $lieuTouristique): Response
    {
        return $this->render('lieu_touristique/show.html.twig', [
            'lieu_touristique' => $lieuTouristique,
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

            $this->addFlash('success', 'Lieu touristique mis Ã  jour avec succÃ¨s.');

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
            $this->addFlash('success', 'Lieu touristique supprimÃ© avec succÃ¨s.');
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

            $statusName = $lieuTouristique->getStatut() ? 'activÃ©' : 'dÃ©sactivÃ©';
            $this->addFlash('success', "Le statut du lieu a Ã©tÃ© $statusName avec succÃ¨s.");
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

            $this->addFlash('success', 'Lieu touristique dupliquÃ© avec succÃ¨s. Veuillez modifier la copie.');
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
                'answer' => "DÃ©solÃ©, il n'y a actuellement aucun lieu touristique actif dans notre catalogue pour vous faire une recommandation."
            ]);
        }

        // Serialize places to a concise string for the AI context
        $context = "Voici la liste des lieux touristiques dans notre catalogue :\n";
        foreach ($lieux as $lieu) {
            $catName = $lieu->getCategorie() ? $lieu->getCategorie()->getNomCategorie() : 'Non classÃ©';
            $context .= sprintf(
                "- %s situÃ© Ã  %s (CatÃ©gorie: %s, Prix: %s DT). Description: %s\n",
                $lieu->getNom(),
                $lieu->getVille(),
                $catName,
                $lieu->getPrix(),
                $lieu->getDescription()
            );
        }

        // We use Gemini API
        try {
            $apiKey = 'AIzaSyD_vZ9iE2Xpw_3Y5OjYLA13619p6jGK-kE';
            
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






