<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Form\ActiviteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
