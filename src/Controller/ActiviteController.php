<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Form\ActiviteType;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

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

        $categories = $entityManager
            ->createQuery('SELECT DISTINCT a.categorie FROM App\\Entity\\Activite a WHERE a.categorie IS NOT NULL ORDER BY a.categorie ASC')
            ->getSingleColumnResult();

        return $this->render('activite/index.html.twig', [
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
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $activite = new Activite();
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/activites',
                        $newFilename
                    );
                    $activite->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image de l\'activité.');
                }
            }

            $entityManager->persist($activite);
            $entityManager->flush();

            return $this->redirectToRoute('app_activite_create_success', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activite/new.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ]);
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
    public function edit(Request $request, Activite $activite, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/activites',
                        $newFilename
                    );
                    $activite->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image de l\'activité.');
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activite/edit.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ]);
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
