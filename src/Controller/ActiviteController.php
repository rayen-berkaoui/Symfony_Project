<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Form\ActiviteType;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\PdfService;
use Endroid\QrCode\Builder\BuilderInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/activite')]
class ActiviteController extends AbstractController
{
    #[Route('/', name: 'app_activite_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $statut = trim((string) $request->query->get('statut', ''));
        $categorie = trim((string) $request->query->get('categorie', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 6;

        $qb = $entityManager
            ->getRepository(Activite::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.etablissement', 'e')
            ->addSelect('e')
            ->orderBy('a.idActivite', 'DESC');

        if ($query !== '') {
            $qb->andWhere('a.nomActivite LIKE :q OR a.niveau LIKE :q OR a.categorie LIKE :q OR e.nom LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if ($statut !== '') {
            $qb->andWhere('a.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($categorie !== '') {
            $qb->andWhere('a.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(a.idActivite)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }

        $activites = $paginator->paginate($qb, $page, $perPage);
        $imageUrls = $this->buildGalleryImageUrls((array) $activites->getItems(), $entityManager);

        $categories = $entityManager
            ->createQuery('SELECT DISTINCT a.categorie FROM App\\Entity\\Activite a WHERE a.categorie IS NOT NULL ORDER BY a.categorie ASC')
            ->getSingleColumnResult();

        return $this->render('activite/index.html.twig', [
            'activites' => $activites,
            'imageUrls' => $imageUrls,
            'filters' => [
                'q' => $query,
                'statut' => $statut,
                'categorie' => $categorie,
            ],
            'categories' => $categories,
            'statuts' => ['disponible', 'complete', 'annulee'],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    #[Route('/gallery-image/{idImage}', name: 'app_activite_gallery_image', methods: ['GET'])]
    public function galleryImage(int $idImage, EntityManagerInterface $entityManager): Response
    {
        $row = $entityManager->getConnection()->fetchAssociative(
            'SELECT image_path FROM activite_image WHERE idImage = :id LIMIT 1',
            ['id' => $idImage]
        );

        if (!$row || !isset($row['image_path'])) {
            throw $this->createNotFoundException('Image not found.');
        }

        $path = $this->resolveActivityImagePath((string) $row['image_path']);
        if ($path === null || !is_file($path)) {
            throw $this->createNotFoundException('Image file missing on disk.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $this->guessMimeTypeFromPath($path));
        $response->setContentDisposition('inline', basename($path));

        return $response;
    }

    /**
     * @param list<Activite> $activites
     * @return array<int, string>
     */
    private function buildGalleryImageUrls(array $activites, EntityManagerInterface $entityManager): array
    {
        $fallbackImageUrls = [];
        $ids = [];
        foreach ($activites as $activite) {
            $id = $activite->getIdActivite();
            if ($id !== null) {
                $ids[] = $id;

                $fallbackImageUrl = $this->buildCoverImageUrl($activite);
                if ($fallbackImageUrl !== null) {
                    $fallbackImageUrls[$id] = $fallbackImageUrl;
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        $rows = $entityManager->getConnection()->executeQuery(
            'SELECT idActivite, idImage
             FROM activite_image
             WHERE idActivite IN (?)
             ORDER BY idActivite ASC, ordre_affichage ASC, idImage ASC',
            [$ids],
            [ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $imageUrls = [];
        foreach ($rows as $row) {
            $idActivite = isset($row['idActivite']) ? (int) $row['idActivite'] : 0;
            $idImage = isset($row['idImage']) ? (int) $row['idImage'] : 0;

            if ($idActivite <= 0 || $idImage <= 0 || isset($imageUrls[$idActivite])) {
                continue;
            }

            $imageUrls[$idActivite] = $this->generateUrl(
                'app_activite_gallery_image',
                ['idImage' => $idImage],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
        }

        foreach ($fallbackImageUrls as $idActivite => $fallbackImageUrl) {
            if (!isset($imageUrls[$idActivite])) {
                $imageUrls[$idActivite] = $fallbackImageUrl;
            }
        }

        return $imageUrls;
    }

    private function buildCoverImageUrl(Activite $activite): ?string
    {
        $imageName = $activite->getImageName();
        if ($imageName === null || $imageName === '') {
            return null;
        }

        $imagePath = $this->getParameter('kernel.project_dir').'/public/uploads/activites/'.$imageName;
        if (!is_file($imagePath)) {
            return null;
        }

        return '/uploads/activites/'.$imageName;
    }

    private function resolveActivityImagePath(string $rawPath): ?string
    {
        if ($rawPath === '') {
            return null;
        }

        if (is_file($rawPath)) {
            return $rawPath;
        }

        $projectDir = dirname(__DIR__, 2);
        $normalized = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $rawPath), DIRECTORY_SEPARATOR);

        $candidates = [
            $projectDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'activites'.DIRECTORY_SEPARATOR.$normalized,
            $projectDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'activities'.DIRECTORY_SEPARATOR.$normalized,
            $projectDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.$normalized,
            $projectDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'activites'.DIRECTORY_SEPARATOR.$normalized,
            $projectDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.$normalized,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
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

    #[Route('/dashboard', name: 'app_activite_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $statut = trim((string) $request->query->get('statut', ''));
        $categorie = trim((string) $request->query->get('categorie', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 8;

        $qb = $entityManager
            ->getRepository(Activite::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.etablissement', 'e')
            ->addSelect('e')
            ->orderBy('a.idActivite', 'DESC');

        if ($query !== '') {
            $qb->andWhere('a.nomActivite LIKE :q OR a.niveau LIKE :q OR a.categorie LIKE :q OR e.nom LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if ($statut !== '') {
            $qb->andWhere('a.statut = :statut')
                ->setParameter('statut', $statut);
        }

        if ($categorie !== '') {
            $qb->andWhere('a.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(a.idActivite)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }

        $activites = $paginator->paginate($qb, $page, $perPage);

        $categories = $entityManager
            ->createQuery('SELECT DISTINCT a.categorie FROM App\\Entity\\Activite a WHERE a.categorie IS NOT NULL ORDER BY a.categorie ASC')
            ->getSingleColumnResult();

        return $this->render('activite/dashboard.html.twig', [
            'activites' => $activites,
            'filters' => [
                'q' => $query,
                'statut' => $statut,
                'categorie' => $categorie,
            ],
            'categories' => $categories,
            'statuts' => ['disponible', 'complete', 'annulee'],
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    #[Route('/analytics', name: 'app_activite_analytics', methods: ['GET'])]
    public function analytics(Request $request, EntityManagerInterface $entityManager): Response
    {
        $activiteRepo = $entityManager->getRepository(Activite::class);
        
        $trendingCategories = $activiteRepo->findTrendingCategories();
        $distributionStatut = $activiteRepo->getDistributionByStatut();
        $distributionNiveau = $activiteRepo->getDistributionByNiveau();
        $totalActivites = $activiteRepo->count([]);
        
        $searchResults = [];
        $searchQuery = $request->query->get('q');
        if ($searchQuery) {
            $searchResults = $activiteRepo->intelligentSearch($searchQuery);
        }

        $topCategory = $trendingCategories[0]['categorie'] ?? 'N/A';
        $topCategoryCount = (int) ($trendingCategories[0]['total'] ?? 0);

        return $this->render('activite/analytics.html.twig', [
            'trendingCategories' => $trendingCategories,
            'distributionStatut' => $distributionStatut,
            'distributionNiveau' => $distributionNiveau,
            'totalActivites' => $totalActivites,
            'totalCategories' => count($trendingCategories),
            'totalNiveaux' => count(array_filter(array_column($distributionNiveau, 'niveau'))),
            'totalSearchResults' => count($searchResults),
            'topCategory' => $topCategory,
            'topCategoryCount' => $topCategoryCount,
            'searchResults' => $searchResults,
            'searchQuery' => $searchQuery
        ]);
    }

    #[Route('/new', name: 'app_activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $activite = new Activite();
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($activite);
            $entityManager->flush();

            return $this->redirectToRoute('app_activite_create_success', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $origin = $error->getOrigin();
                $label = $origin ? $origin->getName() : 'formulaire';
                $this->addFlash('error', sprintf('%s: %s', $label, $error->getMessage()));
            }
        }

        return $this->render('activite/new.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/success/create', name: 'app_activite_create_success', methods: ['GET'])]
    public function createSuccess(): Response
    {
        return $this->render('shared/success_animated.html.twig', [
            'title' => 'Activite créée',
            'message' => 'Votre activité a été ajoutée avec succès.',
            'nextUrl' => $this->generateUrl('app_activite_dashboard'),
            'nextLabel' => 'Aller au dashboard activités',
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

    #[Route('/{idActivite}', name: 'app_activite_show', methods: ['GET'])]
    public function show(Activite $activite): Response
    {
        return $this->render('activite/show.html.twig', [
            'activite' => $activite,
            'coverImageUrl' => $this->buildCoverImageUrl($activite),
        ]);
    }

    #[Route('/{idActivite}/edit', name: 'app_activite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Activite $activite, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $origin = $error->getOrigin();
                $label = $origin ? $origin->getName() : 'formulaire';
                $this->addFlash('error', sprintf('%s: %s', $label, $error->getMessage()));
            }
        }

        return $this->render('activite/edit.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/{idActivite}', name: 'app_activite_delete', methods: ['POST'])]
    public function delete(Request $request, Activite $activite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$activite->getIdActivite(), $request->request->get('_token'))) {
            $entityManager->remove($activite);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{idActivite}/pdf/export', name: 'app_activite_export_pdf', methods: ['GET'])]
    public function exportPdf(Activite $activite, PdfService $pdfService, BuilderInterface $customQrCodeBuilder): Response
    {
        // 1. URL Map
        $mapUrl = '';
        if ($activite->getAdresseDepart()) {
            $mapUrl = sprintf('https://www.google.com/maps/search/?api=1&query=%s', urlencode($activite->getAdresseDepart()));
        } elseif ($activite->getEtablissement() && $activite->getEtablissement()->getLatitude() && $activite->getEtablissement()->getLongitude()) {
            $mapUrl = sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $activite->getEtablissement()->getLatitude(), $activite->getEtablissement()->getLongitude());
        } elseif ($activite->getEtablissement() && $activite->getEtablissement()->getAdresse()) {
            $mapUrl = sprintf('https://www.google.com/maps/search/?api=1&query=%s', urlencode($activite->getEtablissement()->getAdresse() . ', ' . $activite->getEtablissement()->getVille()));
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
        if ($activite->getImageName()) {
            $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/activites/' . $activite->getImageName();
            if (file_exists($imagePath)) {
                $type = pathinfo($imagePath, PATHINFO_EXTENSION);
                $data = file_get_contents($imagePath);
                $imageBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $html = $this->renderView('activite/pdf.html.twig', [
            'activite' => $activite,
            'qrCode_base64' => $qrCodeBase64,
            'image_base64' => $imageBase64
        ]);

        $pdfContent = $pdfService->generatePdf($html);

        $response = new Response($pdfContent);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'activite-' . $activite->getIdActivite() . '.pdf'
        ));
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }
}
