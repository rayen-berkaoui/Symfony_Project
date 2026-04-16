<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Form\AddToCartType;
use App\Form\CheckoutType;
use App\Repository\LieuTouristiqueRepository;
use App\Repository\PanierRepository;
use App\Repository\ReservationRepository;
use App\Service\BookingCatalogService;
use App\Service\BundleRecommendationService;
use App\Service\CartPricingService;
use App\Service\FlightInspirationService;
use App\Service\PaymeeService;
use App\Service\CartInsightsService;
use App\Service\PlacesReviewService;
use App\Service\TranslationService;
use App\Service\TransportAdvisorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use DateTime;

#[Route('/cart')]
final class CartController extends AbstractController
{
    #[Route(name: 'app_cart_index', methods: ['GET'])]
    public function index(
        PanierRepository $panierRepository,
        BookingCatalogService $catalogService,
        BundleRecommendationService $bundleRecommendationService,
        PlacesReviewService $placesReviewService,
        TranslationService $translationService,
        FlightInspirationService $flightInspirationService,
        TransportAdvisorService $transportAdvisorService,
    ): Response {
        $user = $this->requireUser();
        $cartItems = $panierRepository->findPendingByUser($user);
        $catalogService->enrichMany($cartItems);
        $totalPrice = $panierRepository->totalPendingByUser($user);
        $itemCount = $panierRepository->countPendingByUser($user);
        $averagePrice = $itemCount > 0 ? $totalPrice / $itemCount : 0;

        $bundleCards = $bundleRecommendationService->buildForCart($cartItems);
        $reviewHighlights = $placesReviewService->enrich($cartItems);
        $translations = $translationService->translateSummarySet('Votre panier regroupe déjà plusieurs services cohérents.');
        $flightSuggestions = $flightInspirationService->getSuggestions($cartItems);
        $transportAdvice = $transportAdvisorService->adviseForCart($cartItems);

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'totalPrice' => $totalPrice,
            'itemCount' => $itemCount,
            'averagePrice' => $averagePrice,
            'insightUrl' => $this->generateUrl('app_cart_ai_purchase_insight'),
            'weatherAdviceUrl' => $this->generateUrl('app_cart_ai_weather_advice'),
            'bundleCards' => $bundleCards,
            'reviewHighlights' => $reviewHighlights,
            'translatedSummaries' => $translations,
            'flightSuggestions' => $flightSuggestions,
            'placesApiConfigured' => $placesReviewService->isConfigured(),
            'translationConfigured' => $translationService->isConfigured(),
            'flightApiConfigured' => $flightInspirationService->isConfigured(),
            'transportAdvice' => $transportAdvice,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['GET', 'POST'])]
    public function add(
        int $id,
        Request $request,
        LieuTouristiqueRepository $lieuRepository,
        EntityManagerInterface $entityManager,
        CartPricingService $pricingService
    ): Response {
        $user = $this->requireUser();
        $lieu = $lieuRepository->find($id);
        if (!$lieu) {
            $this->addFlash('danger', 'Lieu touristique non trouvé.');
            return $this->redirectToRoute('app_lieu_touristique_index');
        }

        $form = $this->createForm(AddToCartType::class, null, ['service_kind' => 'voyage', 'show_rooms' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $panier = (new Panier())
                ->setUtilisateur($user)
                ->setServiceId($lieu->getId())
                ->setTypeService('Voyage')
                ->setDateDebut($data['dateDebut'])
                ->setDateFin($data['dateFin'])
                ->setNbAdultes($data['nbAdultes'])
                ->setNbEnfants($data['nbEnfants'])
                ->setNbChambres(1)
                ->syncPeopleCount();

            $panier->setPrixEstime($pricingService->estimate($panier));
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

    #[Route('/add-etablissement/{id}', name: 'app_cart_etablissement_add', methods: ['GET', 'POST'])]
    public function addEtablissement(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CartPricingService $pricingService
    ): Response {
        $user = $this->requireUser();
        $etablissement = $entityManager->getRepository(\App\Entity\Etablissement::class)->find($id);
        if (!$etablissement) {
            $this->addFlash('danger', 'Établissement non trouvé.');
            return $this->redirectToRoute('app_etablissement_index');
        }

        $form = $this->createForm(AddToCartType::class, null, ['service_kind' => strtolower((string) $etablissement->getType()), 'show_rooms' => strtolower((string) $etablissement->getType()) === 'hotel']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $typeService = match (strtolower((string) $etablissement->getType())) {
                'hotel' => 'Hotel',
                'restaurant' => 'Restaurant',
                'cafe' => 'Cafe',
                'spa' => 'Spa',
                default => 'Lieu',
            };

            $adultes = (int) $data['nbAdultes'];
            $enfants = (int) $data['nbEnfants'];
            $chambres = $typeService === 'Hotel' ? max(1, (int) ($data['nbChambres'] ?? 1)) : 1;

            $panier = (new Panier())
                ->setUtilisateur($user)
                ->setServiceId($etablissement->getId())
                ->setTypeService($typeService)
                ->setDateDebut($data['dateDebut'])
                ->setDateFin($data['dateFin'])
                ->setNbAdultes($adultes)
                ->setNbEnfants($enfants)
                ->setNbChambres($chambres)
                ->syncPeopleCount();

            $panier->setPrixEstime($pricingService->estimate($panier));
            $entityManager->persist($panier);
            $entityManager->flush();

            $this->addFlash('success', 'Le service a été ajouté à votre panier.');
            return $this->redirectToRoute('app_cart_index');
        }

        return $this->render('cart/add_etablissement.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form,
        ]);
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(Panier $panier, Request $request, EntityManagerInterface $entityManager, CartPricingService $pricingService): Response
    {
        $this->assertOwnership($panier);

        $nbAdultes = max(1, (int) $request->request->get('nbAdultes', 1));
        $nbEnfants = max(0, (int) $request->request->get('nbEnfants', 0));
        $nbChambres = max(1, (int) $request->request->get('nbChambres', $panier->getNbChambres() ?? 1));
        $dateDebutValue = (string) $request->request->get('dateDebut', '');
        $dateFinValue = (string) $request->request->get('dateFin', '');

        if ($dateDebutValue !== '' && $dateFinValue !== '') {
            try {
                $dateDebut = new DateTime($dateDebutValue);
                $dateFin = new DateTime($dateFinValue);
                if ($dateFin <= $dateDebut) {
                    $this->addFlash('danger', 'La date de fin doit être postérieure à la date de début.');
                    return $this->redirectToRoute('app_cart_index');
                }
                $panier->setDateDebut($dateDebut)->setDateFin($dateFin);
            } catch (Throwable) {
                $this->addFlash('danger', 'Les dates fournies sont invalides.');
                return $this->redirectToRoute('app_cart_index');
            }
        }

        if ($panier->getTypeService() === 'Hotel' && ($nbAdultes + $nbEnfants) > ($nbChambres * 4)) {
            $this->addFlash('danger', 'La capacité sélectionnée dépasse la limite recommandée de 4 voyageurs par chambre.');
            return $this->redirectToRoute('app_cart_index');
        }

        $panier->setNbAdultes($nbAdultes)
            ->setNbEnfants($nbEnfants)
            ->setNbChambres($nbChambres)
            ->syncPeopleCount()
            ->setPrixEstime($pricingService->estimate($panier));

        $entityManager->flush();
        $this->addFlash('success', 'Le panier a été mis à jour.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(Panier $panier, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwnership($panier);

        if ($this->isCsrfTokenValid('remove' . $panier->getId(), (string) $request->request->get('_token'))) {
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
        ReservationRepository $reservationRepository,
        EntityManagerInterface $entityManager,
        BookingCatalogService $catalogService,
        PaymeeService $paymeeService
    ): Response {
        $user = $this->requireUser();
        $cartItems = $panierRepository->findPendingByUser($user);
        if ($cartItems === []) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $catalogService->enrichMany($cartItems);
        $totalPrice = $panierRepository->totalPendingByUser($user);
        $form = $this->createForm(CheckoutType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $modePaiement = (string) $form->get('modePaiement')->getData();
            $reservations = [];

            foreach ($cartItems as $panier) {
                $reservation = $reservationRepository->findOneBy(['panier' => $panier]);
                if (!$reservation) {
                    $reservation = (new Reservation())
                        ->setPanier($panier)
                        ->setDatePaiement(new \DateTime())
                        ->setMontantTotal($panier->getPrixEstime())
                        ->setModePaiement($modePaiement)
                        ->setStatutPaiement($modePaiement === 'Paymee' ? 'En cours de paiement' : 'En attente');
                    $entityManager->persist($reservation);
                } else {
                    $reservation
                        ->setDatePaiement(new \DateTime())
                        ->setMontantTotal($panier->getPrixEstime())
                        ->setModePaiement($modePaiement)
                        ->setStatutPaiement($modePaiement === 'Paymee' ? 'En cours de paiement' : 'En attente');
                }
                if ($modePaiement !== 'Paymee') {
                    $panier->setStatutItem('confirme');
                }
                $reservations[] = $reservation;
            }

            $entityManager->flush();

            if ($modePaiement === 'Paymee') {
                if ($totalPrice <= 0) {
                    $this->addFlash('danger', 'Le montant total est nul. Vérifiez les articles du panier avant de lancer Paymee.');
                    return $this->redirectToRoute('app_cart_index');
                }

                $orderId = 'TAB-' . implode('-', array_map(static fn (Reservation $r) => (string) $r->getId(), $reservations));
                $buyer = [
                    'first_name' => (string) $user->getPrenom(),
                    'last_name' => (string) $user->getNom(),
                    'email' => (string) $user->getEmail(),
                    'phone' => (string) $user->getNumTel(),
                ];

                try {
                    $checkout = $paymeeService->createCheckout(
                        $buyer,
                        $totalPrice,
                        sprintf('Tabaany panier %s', $orderId),
                        $orderId
                    );

                    $token = (string) ($checkout['token'] ?? '');
                    foreach ($reservations as $index => $reservation) {
                        $panier = $cartItems[$index] ?? null;
                        $reviewComment = trim((string) $reservation->getReviewComment() . "
[PAYMEE_ORDER:" . $orderId . "]
[PAYMEE_TOKEN:" . $token . "]
[USER_ID:" . $user->getId() . "]
[SNAPSHOT_NAME:" . ($panier?->getDisplayName() ?: ('Service #' . ($panier?->getServiceId() ?? 'N/A'))) . "]
[SNAPSHOT_CITY:" . ($panier?->getDisplayCity() ?: 'Tunisie') . "]
[SNAPSHOT_TYPE:" . ($panier?->getTypeService() ?: 'Lieu') . "]
[SNAPSHOT_SERVICE_ID:" . ($panier?->getServiceId() ?? 0) . "]
[SNAPSHOT_START:" . ($panier?->getDateDebut()?->format('Y-m-d H:i:s') ?: '') . "]
[SNAPSHOT_END:" . ($panier?->getDateFin()?->format('Y-m-d H:i:s') ?: '') . "]
[SNAPSHOT_PEOPLE:" . ($panier?->getNbPersonnes() ?? 1) . "]
[SNAPSHOT_ADULTS:" . ($panier?->getNbAdultes() ?? 1) . "]
[SNAPSHOT_CHILDREN:" . ($panier?->getNbEnfants() ?? 0) . "]
[SNAPSHOT_ROOMS:" . ($panier?->getNbChambres() ?? 1) . "]");
                        $reservation->setReviewComment($reviewComment);
                    }
                    $entityManager->flush();

                    $request->getSession()->set('pending_paymee_reservation_ids', array_map(static fn (Reservation $r) => $r->getId(), $reservations));
                    $request->getSession()->set('pending_paymee_order_id', $orderId);
                    $request->getSession()->set('pending_paymee_checkout_url', $checkout['payment_url']);
                    $request->getSession()->set('pending_paymee_token', $token);

                    return $this->render('cart/paymee_redirect.html.twig', [
                        'paymentUrl' => $checkout['payment_url'],
                        'orderId' => $orderId,
                        'sandboxEnabled' => $paymeeService->isSandboxEnabled(),
                    ]);
                } catch (\Throwable $throwable) {
                    foreach ($reservations as $reservation) {
                        $reservation->setStatutPaiement('En attente');
                    }
                    $entityManager->flush();
                    $this->addFlash('danger', 'Le paiement Paymee n\'a pas pu être lancé : ' . $throwable->getMessage());
                }
            } else {
                $this->addFlash('success', 'Votre réservation a été confirmée et déplacée dans votre espace réservations.');
                return $this->redirectToRoute('app_user_reservation_index');
            }
        }

        return $this->render('cart/checkout.html.twig', [
            'cartItems' => $cartItems,
            'totalPrice' => $totalPrice,
            'form' => $form,
            'paymeeAccount' => $paymeeService->getAccountNumber(),
            'sandboxEnabled' => $paymeeService->isSandboxEnabled(),
        ]);
    }

    #[Route('/paymee/return', name: 'app_cart_paymee_return', methods: ['GET', 'POST'])]
    public function paymeeReturn(Request $request, PaymeeService $paymeeService, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager): Response
    {
        $payload = $paymeeService->extractCallbackPayload($request);
        $updated = $this->applyPaymeePayload($request, $payload, $paymeeService, $reservationRepository, $entityManager);

        if ($updated) {
            $this->addFlash('success', 'Le paiement Paymee a été confirmé. Les services sont maintenant visibles dans vos réservations.');
        } else {
            $this->addFlash('warning', 'Le paiement n\'a pas été confirmé.');
        }

        return $this->redirectToRoute('app_user_reservation_index');
    }

    #[Route('/paymee/cancel', name: 'app_cart_paymee_cancel', methods: ['GET'])]
    public function paymeeCancel(Request $request, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager): Response
    {
        $ids = array_map('intval', (array) $request->getSession()->get('pending_paymee_reservation_ids', []));
        if ($ids !== []) {
            foreach ($reservationRepository->findBy(['id' => $ids]) as $reservation) {
                $reservation->setStatutPaiement('En attente');
                if ($panier = $this->safePanier($reservation)) {
                    $panier->setStatutItem('en_attente');
                }
            }
            $entityManager->flush();
        }
        $this->clearPaymeeSession($request);
        $this->addFlash('warning', 'Le paiement Paymee a été annulé. Vos articles restent dans le panier.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/paymee/status', name: 'app_cart_paymee_status', methods: ['GET'])]
    public function paymeeStatus(Request $request, PaymeeService $paymeeService, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = ['token' => (string) $request->getSession()->get('pending_paymee_token', '')];
        $updated = $this->applyPaymeePayload($request, $payload, $paymeeService, $reservationRepository, $entityManager);

        return new JsonResponse([
            'success' => $updated,
            'redirect' => $updated ? $this->generateUrl('app_user_reservation_index') : null,
        ]);
    }

    #[Route('/paymee/webhook', name: 'app_cart_paymee_webhook', methods: ['POST'])]
    public function paymeeWebhook(Request $request, PaymeeService $paymeeService, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $paymeeService->extractCallbackPayload($request);
        $updated = $this->applyPaymeePayload($request, $payload, $paymeeService, $reservationRepository, $entityManager);

        return new JsonResponse(['success' => $updated]);
    }

    #[Route('/ai/purchase-insight', name: 'app_cart_ai_purchase_insight', methods: ['GET'])]
    public function purchaseInsight(PanierRepository $panierRepository, BookingCatalogService $catalogService, CartInsightsService $cartInsightsService): JsonResponse
    {
        $user = $this->requireUser();
        $cartItems = $panierRepository->findPendingByUser($user);
        $catalogService->enrichMany($cartItems);

        return new JsonResponse($cartInsightsService->buildPurchaseInsight($cartItems));
    }

    #[Route('/ai/weather-advice', name: 'app_cart_ai_weather_advice', methods: ['GET'])]
    public function weatherAdvice(PanierRepository $panierRepository, BookingCatalogService $catalogService, CartInsightsService $cartInsightsService): JsonResponse
    {
        $user = $this->requireUser();
        $cartItems = $panierRepository->findPendingByUser($user);
        $catalogService->enrichMany($cartItems);

        return new JsonResponse($cartInsightsService->buildWeatherInsight($cartItems));
    }

    #[Route('/count', name: 'app_cart_count', methods: ['GET'])]
    public function count(PanierRepository $panierRepository): JsonResponse
    {
        $user = $this->requireUser();
        return new JsonResponse(['count' => $panierRepository->countPendingByUser($user)]);
    }

    private function applyPaymeePayload(Request $request, array $payload, PaymeeService $paymeeService, ReservationRepository $reservationRepository, EntityManagerInterface $entityManager): bool
    {
        $token = (string) ($payload['token'] ?? $payload['payment_token'] ?? $request->query->get('token', $request->query->get('payment_token', $request->getSession()->get('pending_paymee_token', ''))));
        $checksum = (string) ($payload['check_sum'] ?? $payload['checksum'] ?? '');
        $rawStatus = $payload['payment_status'] ?? $payload['status'] ?? $request->query->get('payment_status');
        $paymentStatus = filter_var($rawStatus, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($paymentStatus === null && is_string($rawStatus)) {
            $normalized = mb_strtolower(trim($rawStatus));
            if (in_array($normalized, ['paid', 'paye', 'success', 'successful', 'completed', 'complete'], true)) {
                $paymentStatus = true;
            } elseif (in_array($normalized, ['failed', 'cancelled', 'canceled', 'pending', 'en attente'], true)) {
                $paymentStatus = false;
            }
        }
        $orderId = (string) ($payload['order_id'] ?? $request->query->get('order_id', $request->getSession()->get('pending_paymee_order_id', '')));
        $note = (string) ($payload['note'] ?? $request->query->get('note', ''));

        if ($paymentStatus === null && $token !== '') {
            $check = $paymeeService->checkPayment($token);
            $checkedStatus = $check['data']['payment_status'] ?? $check['data']['status'] ?? null;
            $paymentStatus = filter_var($checkedStatus, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($paymentStatus === null && is_string($checkedStatus)) {
                $normalized = mb_strtolower(trim($checkedStatus));
                if (in_array($normalized, ['paid', 'paye', 'success', 'successful', 'completed', 'complete'], true)) {
                    $paymentStatus = true;
                } elseif (in_array($normalized, ['failed', 'cancelled', 'canceled', 'pending', 'en attente'], true)) {
                    $paymentStatus = false;
                }
            }
            $orderId = (string) ($check['data']['order_id'] ?? $orderId);
            $note = (string) ($check['data']['note'] ?? $note);
            $checksum = $checksum !== '' ? $checksum : (string) (($check['data']['check_sum'] ?? $check['data']['checksum'] ?? $check['check_sum'] ?? $check['checksum'] ?? ''));
            if ($rawStatus === null) {
                $rawStatus = $check['data']['payment_status'] ?? $check['data']['status'] ?? $check['payment_status'] ?? $check['status'] ?? null;
            }
        }

        if ($paymentStatus === null && isset($payload['transaction'])) {
            $paymentStatus = true;
        }

        if ($paymentStatus === null) {
            return false;
        }

        if ($token !== '' && !$paymeeService->validateChecksum($token, (bool) $paymentStatus, $checksum)) {
            return false;
        }

        preg_match_all('/\d+/', $orderId . ' ' . $note, $matches);
        $ids = array_values(array_unique(array_map('intval', $matches[0] ?? [])));
        if ($ids === []) {
            $ids = array_map('intval', (array) $request->getSession()->get('pending_paymee_reservation_ids', []));
        }

        $reservations = $ids !== [] ? $reservationRepository->findBy(['id' => $ids]) : [];
        if ($reservations === [] && $token !== '') {
            $reservations = $reservationRepository->findByPaymeeToken($token);
        }
        if ($reservations === [] && $orderId !== '') {
            $reservations = $reservationRepository->findByPaymeeOrderId($orderId);
        }
        if ($reservations === []) {
            return false;
        }

        foreach ($reservations as $reservation) {
            $reservation->setModePaiement('Paymee');
            $reservation->setDatePaiement(new DateTime());
            $reservation->setStatutPaiement($paymentStatus ? 'Paye' : 'En attente');
            $this->syncPanierStatusFromReservation($reservation, $paymentStatus ? 'confirme' : 'en_attente', $request, $entityManager);
        }

        $entityManager->flush();
        if ($paymentStatus) {
            $this->clearPaymeeSession($request);
        }
        return true;
    }

    private function safePanier(Reservation $reservation): ?Panier
    {
        try {
            return $reservation->getPanier();
        } catch (Throwable) {
            return null;
        }
    }

    private function restoreMissingPanier(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $row = $connection->fetchAssociative('SELECT id_panier FROM reservation WHERE id_reservation = ?', [(int) $reservation->getId()]);
        $panierId = isset($row['id_panier']) ? (int) $row['id_panier'] : 0;
        if ($panierId <= 0) {
            return;
        }
        $exists = $connection->fetchOne('SELECT id_panier FROM panier WHERE id_panier = ?', [$panierId]);
        if ($exists) {
            return;
        }

        $comment = (string) ($reservation->getReviewComment() ?? '');
        $user = $this->getUser();
        $userId = (int) ($this->extractMarker($comment, 'USER_ID') ?: ($user instanceof Utilisateur ? $user->getId() : 0));
        if ($userId <= 0) {
            return;
        }

        $datePaiement = $reservation->getDatePaiement() instanceof \DateTimeInterface ? clone $reservation->getDatePaiement() : new DateTime();
        $start = $this->markerDate($comment, 'SNAPSHOT_START') ?: clone $datePaiement;
        $end = $this->markerDate($comment, 'SNAPSHOT_END') ?: (clone $start)->modify('+1 day');
        if ($end <= $start) {
            $end = (clone $start)->modify('+1 day');
        }
        $people = max(1, (int) ($this->extractMarker($comment, 'SNAPSHOT_PEOPLE') ?: 1));
        $adults = max(1, (int) ($this->extractMarker($comment, 'SNAPSHOT_ADULTS') ?: $people));
        $children = max(0, (int) ($this->extractMarker($comment, 'SNAPSHOT_CHILDREN') ?: 0));
        $rooms = max(1, (int) ($this->extractMarker($comment, 'SNAPSHOT_ROOMS') ?: 1));
        $serviceId = max(0, (int) ($this->extractMarker($comment, 'SNAPSHOT_SERVICE_ID') ?: 0));
        $typeService = (string) ($this->extractMarker($comment, 'SNAPSHOT_TYPE') ?: 'Lieu');

        $connection->insert('panier', [
            'id_panier' => $panierId,
            'id_client' => $userId,
            'id_etablissement' => $serviceId,
            'type_service' => $typeService,
            'date_debut' => $start->format('Y-m-d H:i:s'),
            'date_fin' => $end->format('Y-m-d H:i:s'),
            'nb_personnes' => $people,
            'prix_estime' => (float) $reservation->getMontantTotal(),
            'statut_item' => 'confirme',
            'nb_adultes' => $adults,
            'nb_enfants' => $children,
            'nb_chambres' => $rooms,
        ]);
    }

    private function syncPanierStatusFromReservation(Reservation $reservation, string $status, Request $request, EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $row = $connection->fetchAssociative('SELECT id_panier FROM reservation WHERE id_reservation = ?', [(int) $reservation->getId()]);
        $panierId = isset($row['id_panier']) ? (int) $row['id_panier'] : 0;
        if ($panierId <= 0) {
            return;
        }

        $exists = $connection->fetchOne('SELECT id_panier FROM panier WHERE id_panier = ?', [$panierId]);
        if (!$exists) {
            $this->restoreMissingPanier($reservation, $request, $entityManager);
        }

        $exists = $connection->fetchOne('SELECT id_panier FROM panier WHERE id_panier = ?', [$panierId]);
        if ($exists) {
            $connection->update('panier', ['statut_item' => $status], ['id_panier' => $panierId]);
            return;
        }

        if ($panier = $this->safePanier($reservation)) {
            $panier->setStatutItem($status);
        }
    }

    private function extractMarker(string $text, string $key): ?string
    {
        if (preg_match('/\[' . preg_quote($key, '/') . ':(.*?)\]/', $text, $match)) {
            return trim((string) ($match[1] ?? ''));
        }

        return null;
    }

    private function markerDate(string $text, string $key): ?\DateTimeInterface
    {
        $value = $this->extractMarker($text, $key);
        if (!$value) {
            return null;
        }

        try {
            return new DateTime($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function clearPaymeeSession(Request $request): void
    {
        $request->getSession()->remove('pending_paymee_reservation_ids');
        $request->getSession()->remove('pending_paymee_order_id');
        $request->getSession()->remove('pending_paymee_checkout_url');
        $request->getSession()->remove('pending_paymee_token');
    }

    private function requireUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertOwnership(Panier $panier): void
    {
        $user = $this->requireUser();
        if ($panier->getUtilisateur()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
