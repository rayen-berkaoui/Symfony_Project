<?php

namespace App\Controller;

use App\Entity\Etablissement;
use App\Form\EtablissementType;
use App\Repository\EtablissementRepository;
use App\Repository\ActiviteRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\PdfService;
use Endroid\QrCode\Builder\BuilderInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/etablissement')]
class EtablissementController extends AbstractController
{
    private const VILLES_PAR_GOUVERNORAT = [
        'Tunis' => ['Tunis', 'Le Bardo', 'La Marsa', 'Carthage', 'Sidi Bou Saïd', 'La Goulette', 'Le Kram'],
        'Ariana' => ['Ariana', 'La Soukra', 'Raoued', 'Ettadhamen', 'Kalaat Landlous'],
        'Ben Arous' => ['Ben Arous', 'Hammam Lif', 'Hammam Chott', 'Radès', 'Mégrine', 'Fouchana', 'Mornag'],
        'Manouba' => ['Manouba', 'Den Den', 'Douar Hicher', 'Oued Ellil', 'Tebourba'],
        'Nabeul' => ['Nabeul', 'Hammamet', 'Kelibia', 'Korba', 'Menzel Temime', 'Dar Chaabane', 'Soliman'],
        'Zaghouan' => ['Zaghouan', 'Zriba', 'Bir Mcherga', 'El Fahs'],
        'Bizerte' => ['Bizerte', 'Menzel Bourguiba', 'Mateur', 'Ras Jebel', 'Sejnane'],
        'Béja' => ['Béja', 'Testour', 'Medjez el Bab', 'Nefza', 'Amdoun'],
        'Jendouba' => ['Jendouba', 'Tabarka', 'Ain Draham', 'Fernana', 'Ghardimaou'],
        'Le Kef' => ['Le Kef', 'Tajerouine', 'Kalaat Senan', 'Dahmani'],
        'Siliana' => ['Siliana', 'Bouarada', 'Gaafour', 'Makthar'],
        'Sousse' => ['Sousse', 'Hammam Sousse', 'Akouda', 'Msaken', 'Kantaoui'],
        'Monastir' => ['Monastir', 'Skanes', 'Jemmal', 'Moknine', 'Ksar Hellal'],
        'Mahdia' => ['Mahdia', 'El Jem', 'Chebba', 'Ksour Essef'],
        'Sfax' => ['Sfax', 'Sakiet Ezzit', 'Sakiet Eddaier', 'Mahres', 'Kerkennah'],
        'Kairouan' => ['Kairouan', 'Haffouz', 'Chebika', 'Sbikha'],
        'Kasserine' => ['Kasserine', 'Sbeitla', 'Thala', 'Feriana'],
        'Sidi Bouzid' => ['Sidi Bouzid', 'Regueb', 'Meknassi', 'Jilma'],
        'Gabès' => ['Gabès', 'Métouia', 'Mareth', 'Ghannouch'],
        'Médenine' => ['Médenine', 'Djerba', 'Zarzis', 'Ben Guerdane'],
        'Tataouine' => ['Tataouine', 'Ghomrassen', 'Bir Lahmar', 'Remada'],
        'Gafsa' => ['Gafsa', 'Metlaoui', 'Redeyef', 'Moulares'],
        'Tozeur' => ['Tozeur', 'Nefta', 'Degache'],
        'Kébili' => ['Kébili', 'Douz', 'Souk Lahad'],
    ];

    #[Route('/', name: 'app_etablissement_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $type = trim((string) $request->query->get('type', ''));
        $ville = trim((string) $request->query->get('ville', ''));
        $gammePrix = trim((string) $request->query->get('gammePrix', ''));
        $sort = trim((string) $request->query->get('sort', 'recent'));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 6;

        $qb = $entityManager
            ->getRepository(Etablissement::class)
            ->createQueryBuilder('e');

        if ($query !== '') {
            $qb->andWhere('e.nom LIKE :q OR e.ville LIKE :q OR e.adresse LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if ($type !== '') {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $type);
        }

        if ($ville !== '') {
            $qb->andWhere('e.ville = :ville')
                ->setParameter('ville', $ville);
        }

        if ($gammePrix !== '') {
            $qb->andWhere('e.gammePrix = :gammePrix')
                ->setParameter('gammePrix', $gammePrix);
        }

        switch ($sort) {
            case 'ancien':
                $qb->orderBy('e.idEtablissement', 'ASC');
                break;
            case 'nom_asc':
                $qb->orderBy('e.nom', 'ASC');
                break;
            case 'ville_asc':
                $qb->orderBy('e.ville', 'ASC');
                break;
            case 'recent':
            default:
                $sort = 'recent';
                $qb->orderBy('e.idEtablissement', 'DESC');
                break;
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(e.idEtablissement)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }

        $etablissements = $paginator->paginate($qb, $page, $perPage);
        $imageUrls = $this->buildGalleryImageUrls((array) $etablissements->getItems(), $entityManager);

        $types = $entityManager
            ->createQuery('SELECT DISTINCT e.type FROM App\\Entity\\Etablissement e WHERE e.type IS NOT NULL ORDER BY e.type ASC')
            ->getSingleColumnResult();

        $villes = $entityManager
            ->createQuery('SELECT DISTINCT e.ville FROM App\\Entity\\Etablissement e WHERE e.ville IS NOT NULL ORDER BY e.ville ASC')
            ->getSingleColumnResult();

        $gammesPrix = $entityManager
            ->createQuery('SELECT DISTINCT e.gammePrix FROM App\\Entity\\Etablissement e WHERE e.gammePrix IS NOT NULL ORDER BY e.gammePrix ASC')
            ->getSingleColumnResult();

        return $this->render('etablissement/index.html.twig', [
            'etablissements' => $etablissements,
            'imageUrls' => $imageUrls,
            'filters' => [
                'q' => $query,
                'type' => $type,
                'ville' => $ville,
                'gammePrix' => $gammePrix,
                'sort' => $sort,
            ],
            'types' => $types,
            'villes' => $villes,
            'villesParGouvernorat' => self::VILLES_PAR_GOUVERNORAT,
            'gammesPrix' => $gammesPrix,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    #[Route('/gallery-image/{idImage}', name: 'app_etablissement_gallery_image', methods: ['GET'])]
    public function galleryImage(int $idImage, EntityManagerInterface $entityManager): Response
    {
        $row = $entityManager->getConnection()->fetchAssociative(
            'SELECT image_path FROM etablissement_image WHERE idImage = :id LIMIT 1',
            ['id' => $idImage]
        );

        if (!$row || !isset($row['image_path'])) {
            throw $this->createNotFoundException('Image not found.');
        }

        $path = (string) $row['image_path'];
        if (!is_file($path)) {
            throw $this->createNotFoundException('Image file missing on disk.');
        }

        $response = new BinaryFileResponse($path);
        $mimeType = $this->guessMimeTypeFromPath($path);
        $response->headers->set('Content-Type', $mimeType);
        $response->setContentDisposition('inline', basename($path));

        return $response;
    }

    /**
     * @param list<Etablissement> $etablissements
     * @return array<int, string>
     */
    private function buildGalleryImageUrls(array $etablissements, EntityManagerInterface $entityManager): array
    {
        $fallbackImageUrls = [];
        $ids = [];
        foreach ($etablissements as $etablissement) {
            $id = $etablissement->getIdEtablissement();
            if ($id !== null) {
                $ids[] = $id;

                $fallbackImageUrl = $this->buildCoverImageUrl($etablissement);
                if ($fallbackImageUrl !== null) {
                    $fallbackImageUrls[$id] = $fallbackImageUrl;
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        $rows = $entityManager->getConnection()->executeQuery(
            'SELECT idEtablissement, idImage
             FROM etablissement_image
             WHERE idEtablissement IN (?)
             ORDER BY idEtablissement ASC, ordre_affichage ASC, idImage ASC',
            [$ids],
            [ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $imageUrls = [];
        foreach ($rows as $row) {
            $idEtablissement = isset($row['idEtablissement']) ? (int) $row['idEtablissement'] : 0;
            $idImage = isset($row['idImage']) ? (int) $row['idImage'] : 0;

            if ($idEtablissement <= 0 || $idImage <= 0 || isset($imageUrls[$idEtablissement])) {
                continue;
            }

            $imageUrls[$idEtablissement] = $this->generateUrl(
                'app_etablissement_gallery_image',
                ['idImage' => $idImage],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
        }

        foreach ($fallbackImageUrls as $idEtablissement => $fallbackImageUrl) {
            if (!isset($imageUrls[$idEtablissement])) {
                $imageUrls[$idEtablissement] = $fallbackImageUrl;
            }
        }

        return $imageUrls;
    }

    private function buildCoverImageUrl(Etablissement $etablissement): ?string
    {
        $imageName = $etablissement->getImageName();
        if ($imageName === null || $imageName === '') {
            return null;
        }

        $imagePath = $this->getParameter('kernel.project_dir').'/public/uploads/etablissements/'.$imageName;
        if (!is_file($imagePath)) {
            return null;
        }

        return '/uploads/etablissements/'.$imageName;
    }

    #[Route('/dashboard', name: 'app_etablissement_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $type = trim((string) $request->query->get('type', ''));
        $ville = trim((string) $request->query->get('ville', ''));
        $gammePrix = trim((string) $request->query->get('gammePrix', ''));
        $sort = trim((string) $request->query->get('sort', 'recent'));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 8;

        $qb = $entityManager
            ->getRepository(Etablissement::class)
            ->createQueryBuilder('e');

        if ($query !== '') {
            $qb->andWhere('e.nom LIKE :q OR e.ville LIKE :q OR e.adresse LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if ($type !== '') {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $type);
        }

        if ($ville !== '') {
            $qb->andWhere('e.ville = :ville')
                ->setParameter('ville', $ville);
        }

        if ($gammePrix !== '') {
            $qb->andWhere('e.gammePrix = :gammePrix')
                ->setParameter('gammePrix', $gammePrix);
        }

        switch ($sort) {
            case 'ancien':
                $qb->orderBy('e.idEtablissement', 'ASC');
                break;
            case 'nom_asc':
                $qb->orderBy('e.nom', 'ASC');
                break;
            case 'ville_asc':
                $qb->orderBy('e.ville', 'ASC');
                break;
            case 'recent':
            default:
                $sort = 'recent';
                $qb->orderBy('e.idEtablissement', 'DESC');
                break;
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(e.idEtablissement)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }

        $etablissements = $paginator->paginate($qb, $page, $perPage);

        $types = $entityManager
            ->createQuery('SELECT DISTINCT e.type FROM App\\Entity\\Etablissement e WHERE e.type IS NOT NULL ORDER BY e.type ASC')
            ->getSingleColumnResult();

        $villes = $entityManager
            ->createQuery('SELECT DISTINCT e.ville FROM App\\Entity\\Etablissement e WHERE e.ville IS NOT NULL ORDER BY e.ville ASC')
            ->getSingleColumnResult();

        $gammesPrix = $entityManager
            ->createQuery('SELECT DISTINCT e.gammePrix FROM App\\Entity\\Etablissement e WHERE e.gammePrix IS NOT NULL ORDER BY e.gammePrix ASC')
            ->getSingleColumnResult();

        return $this->render('etablissement/dashboard.html.twig', [
            'etablissements' => $etablissements,
            'filters' => [
                'q' => $query,
                'type' => $type,
                'ville' => $ville,
                'gammePrix' => $gammePrix,
                'sort' => $sort,
            ],
            'types' => $types,
            'villes' => $villes,
            'villesParGouvernorat' => self::VILLES_PAR_GOUVERNORAT,
            'gammesPrix' => $gammesPrix,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    #[Route('/new', name: 'app_etablissement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $etablissement = new Etablissement();
        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                // dump($form->getErrors(true));
            } else {
                $entityManager->persist($etablissement);
                $entityManager->flush();

                return $this->redirectToRoute('app_etablissement_create_success', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('etablissement/new.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/success/create', name: 'app_etablissement_create_success', methods: ['GET'])]
    public function createSuccess(): Response
    {
        return $this->render('shared/success_animated.html.twig', [
            'title' => 'Etablissement créé',
            'message' => 'Votre établissement a été ajouté avec succès.',
            'nextUrl' => $this->generateUrl('app_etablissement_dashboard'),
            'nextLabel' => 'Aller au dashboard établissements',
        ]);
    }

    private function sanitizeUtf8(string $value): string
    {
        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        $converted = $this->safeIconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($converted !== false && preg_match('//u', $converted) === 1) {
            return $converted;
        }

        $converted = $this->safeIconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if ($converted !== false && preg_match('//u', $converted) === 1) {
            return $converted;
        }

        $converted = $this->safeIconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
        if ($converted !== false && preg_match('//u', $converted) === 1) {
            return $converted;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
    }

    private function safeIconv(string $fromEncoding, string $toEncoding, string $value): string|false
    {
        return $this->runPdfSafely(static function () use ($fromEncoding, $toEncoding, $value): string|false {
            return iconv($fromEncoding, $toEncoding, $value);
        });
    }

    private function runPdfSafely(callable $callback): mixed
    {
        set_error_handler(static function (int $severity, string $message): bool {
            if (
                ($severity === E_NOTICE || $severity === E_WARNING)
                && str_contains(strtolower($message), 'incomplete multibyte character')
            ) {
                return true;
            }

            return false;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    #[Route('/analytics', name: 'app_etablissement_analytics', methods: ['GET'])]
    public function analytics(Request $request, EtablissementRepository $etablissementRepo, ActiviteRepository $activiteRepo): Response
    {
        // Récupérations des tendances
        $popularCities = $etablissementRepo->findPopularCities();
        $trendingActivities = $activiteRepo->findTrendingCategories();
        $distributionTypes = $etablissementRepo->getDistributionByType(); // Nouvelle Stat
        $totalEtablissements = $etablissementRepo->count([]);
        $totalActivites = $activiteRepo->count([]);

        // Système de recherche simulée depuis la barre de recherche
        $searchResults = [];
        $searchQuery = $request->query->get('q');
        if ($searchQuery) {
            $searchResults = $etablissementRepo->intelligentSearch($searchQuery);
        }

        $topCity = $popularCities[0]['ville'] ?? 'N/A';
        $topCityCount = (int) ($popularCities[0]['total'] ?? 0);

        return $this->render('shared/analytics.html.twig', [
            'popularCities' => $popularCities,
            'trendingActivities' => $trendingActivities,
            'distributionTypes' => $distributionTypes,
            'totalEtablissements' => $totalEtablissements,
            'totalActivites' => $totalActivites,
            'totalVilles' => count($popularCities),
            'totalCategories' => count(array_filter(array_column($distributionTypes, 'type'))),
            'totalSearchResults' => count($searchResults),
            'topCity' => $topCity,
            'topCityCount' => $topCityCount,
            'searchResults' => $searchResults,
            'searchQuery' => $searchQuery
        ]);
    }

    #[Route('/{idEtablissement}', name: 'app_etablissement_show', methods: ['GET'])]
    public function show(Etablissement $etablissement): Response
    {
        return $this->render('etablissement/show.html.twig', [
            'etablissement' => $etablissement,
            'coverImageUrl' => $this->buildCoverImageUrl($etablissement),
        ]);
    }

    #[Route('/{idEtablissement}/edit', name: 'app_etablissement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_etablissement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('etablissement/edit.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/{idEtablissement}', name: 'app_etablissement_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$etablissement->getIdEtablissement(), $request->request->get('_token'))) {
            $entityManager->remove($etablissement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_etablissement_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $uploadedImages
     */
    private function storeUploadedImages(array|UploadedFile|null $uploadedImages, Etablissement $etablissement, EntityManagerInterface $entityManager): void
    {
        if ($uploadedImages === null) {
            return;
        }

        $files = $uploadedImages instanceof UploadedFile ? [$uploadedImages] : $uploadedImages;
        if ($files === []) {
            return;
        }

        $idEtablissement = $etablissement->getIdEtablissement();
        if ($idEtablissement === null) {
            return;
        }

        $uploadDir = dirname(__DIR__, 2).'/public/uploads/etablissements';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $connection = $entityManager->getConnection();
        $maxOrder = (int) $connection->fetchOne(
            'SELECT COALESCE(MAX(ordre_affichage), 0) FROM etablissement_image WHERE idEtablissement = :id',
            ['id' => $idEtablissement]
        );

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $safeExt = $this->resolveUploadedFileExtension($file);
            $fileName = sprintf('etab_%d_%d.%s', $idEtablissement, time().random_int(1000, 9999), $safeExt);
            $file->move($uploadDir, $fileName);

            $maxOrder++;
            $connection->insert('etablissement_image', [
                'idEtablissement' => $idEtablissement,
                'image_path' => $uploadDir.'/'.$fileName,
                'ordre_affichage' => $maxOrder,
            ]);
        }
    }

    private function resolveUploadedFileExtension(UploadedFile $file): string
    {
        try {
            $guessed = $file->guessExtension();
            if (is_string($guessed) && $guessed !== '') {
                return strtolower($guessed);
            }
        } catch (\Throwable) {
            // Fall back to original client extension when MIME guessers are not available.
        }

        $clientExt = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($clientExt !== '') {
            return preg_replace('/[^a-z0-9]+/', '', $clientExt) ?: 'bin';
        }

        return 'bin';
    }

    private function guessMimeTypeFromPath(string $path): string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }

    #[Route('/{idEtablissement}/pdf/export', name: 'app_etablissement_export_pdf', methods: ['GET'])]
    public function exportPdf(Etablissement $etablissement, PdfService $pdfService, BuilderInterface $customQrCodeBuilder): Response
    {
        // 1. URL Map
        $mapUrl = '';
        if ($etablissement->getLatitude() && $etablissement->getLongitude()) {
            $mapUrl = sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $etablissement->getLatitude(), $etablissement->getLongitude());
        } elseif ($etablissement->getAdresse()) {
            $mapUrl = sprintf('https://www.google.com/maps/search/?api=1&query=%s', urlencode($etablissement->getAdresse() . ', ' . $etablissement->getVille()));
        }

        // 2. Base 64 QR Code
        $qrCodeBase64 = null;
        if ($mapUrl) {
            $result = $customQrCodeBuilder->build(
                data: $mapUrl,
                size: 150,
                margin: 0
            );
            $qrCodeBase64 = $result->getDataUri();
        }

        // 3. Base 64 Image de profil
        $imageBase64 = null;
        if ($etablissement->getImageName()) {
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/etablissements/' . $etablissement->getImageName();
            if (file_exists($imagePath)) {
                $type = pathinfo($imagePath, PATHINFO_EXTENSION);
                $data = file_get_contents($imagePath);
                $imageBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $html = $this->renderView('etablissement/pdf.html.twig', [
            'etablissement' => $etablissement,
            'qrCode_base64' => $qrCodeBase64,
            'image_base64' => $imageBase64
        ]);

        $pdfContent = $pdfService->generatePdf($html);

        $response = new Response($pdfContent);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'etablissement-' . $etablissement->getIdEtablissement() . '.pdf'
        ));
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }
}
