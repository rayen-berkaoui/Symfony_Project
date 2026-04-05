<?php

namespace App\Controller;

use App\Entity\Etablissement;
use App\Form\EtablissementType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($etablissement);
            $entityManager->flush();

            return $this->redirectToRoute('app_etablissement_create_success', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('etablissement/new.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
        ]);
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

            return $this->redirectToRoute('app_etablissement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('etablissement/edit.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(), // using createView for compatibility
        ]);
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
}
