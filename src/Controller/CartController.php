<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Reservation;
use App\Form\AddToCartType;
use App\Form\CheckoutType;
use App\Repository\LieuTouristiqueRepository;
use App\Repository\EtablissementRepository;
use App\Repository\PanierRepository;
use App\Service\ItineraryPlanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends AbstractController
{
    #[Route(name: 'app_cart_index', methods: ['GET'])]
    public function index(Request $request, PanierRepository $panierRepository): Response
    {
        $sessionId = $request->getSession()->getId();
        $cartItems = $panierRepository->findBySessionId($sessionId);
        $totalPrice = $panierRepository->totalBySessionId($sessionId);
        $itemCount = $panierRepository->countBySessionId($sessionId);

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'totalPrice' => $totalPrice,
            'itemCount' => $itemCount,
        ]);
    }

    #[Route('/itinerary', name: 'app_cart_itinerary', methods: ['GET'])]
    public function itinerary(
        Request $request,
        PanierRepository $panierRepository,
        ItineraryPlanner $itineraryPlanner
    ): Response {
        $sessionId = $request->getSession()->getId();
        $cartItems = $panierRepository->findBySessionId($sessionId);

        if ($cartItems === []) {
            $this->addFlash('warning', 'Votre panier est vide. Ajoutez des lieux pour générer un itinéraire.');

            return $this->redirectToRoute('app_cart_index');
        }

        $startAddress = trim((string) $request->query->get('start_address', ''));
        $startLat = $this->parseFloatQuery($request->query->get('start_lat'));
        $startLng = $this->parseFloatQuery($request->query->get('start_lng'));

        if (($startLat === null || $startLng === null) && $startAddress !== '') {
            $parsedCoordinates = $this->parseCoordinatesInput($startAddress);
            if ($parsedCoordinates !== null) {
                $startLat = $parsedCoordinates['lat'];
                $startLng = $parsedCoordinates['lng'];
            }
        }

        $transportMode = strtolower(trim((string) $request->query->get('transport_mode', 'car')));
        if (!in_array($transportMode, ['car', 'walk'], true)) {
            $transportMode = 'car';
        }

        $maxBudget = $this->parseFloatQuery($request->query->get('max_budget'));
        if ($maxBudget !== null && $maxBudget <= 0.0) {
            $maxBudget = null;
        }

        $maxDurationHours = $this->parseFloatQuery($request->query->get('max_duration_hours'));
        if ($maxDurationHours !== null) {
            $maxDurationHours = max(1.0, min(16.0, $maxDurationHours));
        }

        $stops = [];
        $unplannableItems = [];

        foreach ($cartItems as $item) {
            $lieu = $item->getLieuTouristique();
            if ($lieu === null) {
                $unplannableItems[] = [
                    'label' => sprintf('Panier #%d', (int) $item->getId()),
                    'reason' => 'Article sans lieu touristique géolocalisable.',
                ];
                continue;
            }

            $adresse = $lieu->getAdresse();
            if ($adresse === null || $adresse->getLatitude() === null || $adresse->getLongitude() === null) {
                $unplannableItems[] = [
                    'label' => (string) ($lieu->getNom() ?? ('Lieu #' . (string) $lieu->getId())),
                    'reason' => 'Coordonnées GPS manquantes.',
                ];
                continue;
            }

            $stops[] = [
                'panierId' => $item->getId(),
                'lieuId' => $lieu->getId(),
                'name' => (string) ($lieu->getNom() ?? 'Lieu'),
                'ville' => (string) ($lieu->getVille() ?? $adresse->getVille() ?? ''),
                'latitude' => (float) $adresse->getLatitude(),
                'longitude' => (float) $adresse->getLongitude(),
                'estimatedPrice' => (float) ($item->getPrixEstime() ?? 0),
                'visitHours' => $this->estimateVisitHours($item),
            ];
        }

        if ($stops === []) {
            $this->addFlash('warning', 'Aucun lieu du panier ne possède des coordonnées valides pour générer un itinéraire.');

            return $this->redirectToRoute('app_cart_index');
        }

        $startPoint = null;
        if ($startLat !== null && $startLng !== null) {
            $startPoint = [
                'latitude' => $startLat,
                'longitude' => $startLng,
                'label' => $startAddress !== '' ? $startAddress : 'Point de départ',
            ];
        }

        $plan = $itineraryPlanner->plan(
            $stops,
            $startPoint,
            $transportMode,
            $maxBudget,
            $maxDurationHours
        );

        return $this->render('cart/itinerary.html.twig', [
            'cartItems' => $cartItems,
            'plan' => $plan,
            'unplannableItems' => $unplannableItems,
            'startAddress' => $startAddress,
            'startLat' => $startLat,
            'startLng' => $startLng,
            'transportMode' => $transportMode,
            'maxBudget' => $maxBudget,
            'maxDurationHours' => $maxDurationHours,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['GET', 'POST'])]
    public function add(
        int $id,
        Request $request,
        LieuTouristiqueRepository $lieuRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $lieu = $lieuRepository->find($id);
        if (!$lieu) {
            $this->addFlash('danger', 'Lieu touristique non trouvé.');
            return $this->redirectToRoute('app_lieu_touristique_index');
        }

        $form = $this->createForm(AddToCartType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $panier = new Panier();
            $panier->setLieuTouristique($lieu);
            $panier->setSessionId($request->getSession()->getId());
            
            if ($this->getUser()) {
                $panier->setUtilisateur($this->getUser());
            }
            
            $panier->setTypeService('Visite');
            $panier->setDateDebut($data['dateDebut']);
            $panier->setDateFin($data['dateFin']);
            $panier->setNbAdultes($data['nbAdultes']);
            $panier->setNbEnfants($data['nbEnfants']);
            $panier->setNbPersonnes($data['nbAdultes'] + $data['nbEnfants']);

            // Calculate price: lieu price * number of persons * number of days
            $lieuPrice = (float) $lieu->getPrix();
            $nbJours = $panier->getNbJours();
            $prixEstime = $lieuPrice * $panier->getNbPersonnes() * $nbJours;
            $panier->setPrixEstime((string) $prixEstime);

            $entityManager->persist($panier);
            $entityManager->flush();

            $this->addFlash('success', 'Le lieu a été ajouté à votre panier.');
            return $this->redirectToRoute('app_cart_index');
        }

        return $this->render('cart/add.html.twig', [
            'lieu' => $lieu,
            'form' => $form,
        ]);
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(
        Panier $panier,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verify ownership via session
        if ($panier->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_cart_index');
        }

        $nbAdultes = (int) $request->request->get('nbAdultes', 1);
        $nbEnfants = (int) $request->request->get('nbEnfants', 0);

        if ($nbAdultes < 1 || $nbAdultes > 20) {
            $this->addFlash('error', 'Le nombre d\'adultes doit être compris entre 1 et 20.');
            return $this->redirectToRoute('app_cart_index');
        }

        if ($nbEnfants < 0 || $nbEnfants > 20) {
            $this->addFlash('error', 'Le nombre d\'enfants doit être compris entre 0 et 20.');
            return $this->redirectToRoute('app_cart_index');
        }

        $panier->setNbAdultes($nbAdultes);
        $panier->setNbEnfants($nbEnfants);
        $panier->setNbPersonnes($panier->getNbAdultes() + $panier->getNbEnfants());

        // Recalculate price
        $lieuPrice = (float) $panier->getLieuTouristique()?->getPrix();
        $prixEstime = $lieuPrice * $panier->getNbPersonnes() * $panier->getNbJours();
        $panier->setPrixEstime((string) $prixEstime);

        $entityManager->flush();

        $this->addFlash('success', 'Le panier a été mis à jour.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(
        Panier $panier,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verify ownership via session
        if ($panier->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_cart_index');
        }

        if ($this->isCsrfTokenValid('remove' . $panier->getId(), $request->request->get('_token'))) {
            $entityManager->remove($panier);
            $entityManager->flush();
            $this->addFlash('success', 'L\'article a été retiré du panier.');
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/checkout', name: 'app_cart_checkout', methods: ['GET', 'POST'])]
    public function checkout(
        Request $request,
        PanierRepository $panierRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $sessionId = $request->getSession()->getId();
        $cartItems = $panierRepository->findBySessionId($sessionId);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $totalPrice = $panierRepository->totalBySessionId($sessionId);

        $form = $this->createForm(CheckoutType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Create reservations for all cart items
            foreach ($cartItems as $panier) {
                $reservation = new Reservation();
                $reservation->setPanier($panier);
                $reservation->setDatePaiement(new \DateTime());
                $reservation->setMontantTotal($panier->getPrixEstime());
                $reservation->setModePaiement($data['modePaiement']);
                $reservation->setStatutPaiement('En cours de paiement');

                // Update panier status
                $panier->setStatutItem('confirmé');

                $entityManager->persist($reservation);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Votre réservation a été confirmée ! Vous recevrez bientôt un email de confirmation.');
            return $this->redirectToRoute('app_user_reservation_index');
        }

        return $this->render('cart/checkout.html.twig', [
            'cartItems' => $cartItems,
            'totalPrice' => $totalPrice,
            'form' => $form,
        ]);
    }

    #[Route('/count', name: 'app_cart_count', methods: ['GET'])]
    public function count(Request $request, PanierRepository $panierRepository): JsonResponse
    {
        $sessionId = $request->getSession()->getId();
        $count = $panierRepository->countBySessionId($sessionId);

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/add-etablissement/{id}', name: 'app_cart_etablissement_add', methods: ['GET', 'POST'])]
    public function addEtablissement(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $etablissement = $entityManager->getRepository(\App\Entity\Etablissement::class)->find($id);
        if (!$etablissement) {
            $this->addFlash('danger', 'Établissement non trouvé.');
            return $this->redirectToRoute('app_etablissement_index');
        }

        $form = $this->createForm(AddToCartType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $panier = new Panier();
            $panier->setEtablissement($etablissement);
            $panier->setSessionId($request->getSession()->getId());

            if ($this->getUser()) {
                $panier->setUtilisateur($this->getUser());
            }

            $panier->setTypeService('Visite');
            $panier->setDateDebut($data['dateDebut']);
            $panier->setDateFin($data['dateFin']);
            $panier->setNbAdultes($data['nbAdultes']);
            $panier->setNbEnfants($data['nbEnfants']);
            $panier->setNbPersonnes($data['nbAdultes'] + $data['nbEnfants']);

            // Arbitrary price for etablissement if it has no price
            $prixEstime = 0;
            $panier->setPrixEstime((string) $prixEstime);

            $entityManager->persist($panier);
            $entityManager->flush();

            $this->addFlash('success', 'L\'établissement a été ajouté à votre panier.');

            return $this->redirectToRoute('app_cart_index');
        }

        return $this->render('cart/add_etablissement.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
        ]);
    }

    private function parseFloatQuery(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
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

            return ['lat' => $lat, 'lng' => $lng];
        }

        return null;
    }

    private function estimateVisitHours(Panier $panier): float
    {
        $days = max(1, $panier->getNbJours());

        return min(6.0, 1.0 + (($days - 1) * 0.5));
    }
}

