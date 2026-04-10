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

#[Route('/activite')]
class ActiviteController extends AbstractController
{
    #[Route('/', name: 'app_activite_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
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
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $activites = $qb->getQuery()->getResult();
        $imageUrls = $this->buildGalleryImageUrls($activites, $entityManager);

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
        $ids = [];
        foreach ($activites as $activite) {
            $id = $activite->getIdActivite();
            if ($id !== null) {
                $ids[] = $id;
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

        return $imageUrls;
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
    public function dashboard(Request $request, EntityManagerInterface $entityManager): Response
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
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $activites = $qb->getQuery()->getResult();

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

    #[Route('/{idActivite}/export-pdf', name: 'app_activite_export_pdf', methods: ['GET'])]
    public function exportPdf(Activite $activite): Response
    {
        $html = (string) $this->runPdfSafely(function () use ($activite): string {
            return $this->renderView('activite/pdf.html.twig', [
                'activite' => $activite,
            ]);
        });
        $html = $this->sanitizeUtf8($html);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $this->runPdfSafely(static function () use ($dompdf, $html): void {
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
        });

        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($activite->getNomActivite() ?? 'activite'));
        $fileName = sprintf('activite-%s.pdf', trim((string) $safeName, '-'));

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
            ]
        );
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
}
