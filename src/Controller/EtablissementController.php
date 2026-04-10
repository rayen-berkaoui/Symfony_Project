<?php

namespace App\Controller;

use App\Entity\Etablissement;
use App\Form\EtablissementType;
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
    public function index(Request $request, EntityManagerInterface $entityManager): Response
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
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $etablissements = $qb->getQuery()->getResult();
        $imageUrls = $this->buildGalleryImageUrls($etablissements, $entityManager);

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
        $ids = [];
        foreach ($etablissements as $etablissement) {
            $id = $etablissement->getIdEtablissement();
            if ($id !== null) {
                $ids[] = $id;
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

        return $imageUrls;
    }

    #[Route('/dashboard', name: 'app_etablissement_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager): Response
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
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $etablissements = $qb->getQuery()->getResult();

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
                $this->storeUploadedImages($form->get('images')->getData(), $etablissement, $entityManager);

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

    #[Route('/{idEtablissement}/export-pdf', name: 'app_etablissement_export_pdf', methods: ['GET'])]
    public function exportPdf(Etablissement $etablissement): Response
    {
        $html = (string) $this->runPdfSafely(function () use ($etablissement): string {
            return $this->renderView('etablissement/pdf.html.twig', [
                'etablissement' => $etablissement,
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

        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($etablissement->getNom() ?? 'etablissement'));
        $fileName = sprintf('etablissement-%s.pdf', trim((string) $safeName, '-'));

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

    #[Route('/{idEtablissement}', name: 'app_etablissement_show', methods: ['GET'])]
    public function show(Etablissement $etablissement): Response
    {
        return $this->render('etablissement/show.html.twig', [
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{idEtablissement}/edit', name: 'app_etablissement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EtablissementType::class, $etablissement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->storeUploadedImages($form->get('images')->getData(), $etablissement, $entityManager);

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
}
