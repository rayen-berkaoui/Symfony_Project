<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use App\Service\BookingCatalogService;
use App\Service\CalendarExperienceService;
use App\Service\PlacesReviewService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mes-reservations')]
final class UserReservationController extends AbstractController
{
    #[Route(name: 'app_user_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository): Response
    {
        $user = $this->requireUser();
        $rows = $reservationRepository->getUserRows((int) $user->getId());

        $totalSpent = 0.0;
        $paidCount = 0;
        $pendingCount = 0;
        foreach ($rows as $row) {
            if (($row['normalizedStatutPaiement'] ?? '') === 'Paye') {
                $paidCount++;
                $totalSpent += (float) ($row['montantTotal'] ?? 0);
            } elseif (in_array($row['normalizedStatutPaiement'] ?? '', ['En cours de paiement', 'En attente'], true)) {
                $pendingCount++;
            }
        }

        return $this->render('user_reservation/index.html.twig', [
            'rows' => $rows,
            'stats' => [
                'total' => count($rows),
                'paye' => $paidCount,
                'en_cours' => $pendingCount,
                'total_spent' => $totalSpent,
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_user_reservation_show', methods: ['GET'])]
    public function show(
        Reservation $reservation,
        ReservationRepository $reservationRepository,
        BookingCatalogService $catalogService,
        CalendarExperienceService $calendarExperienceService,
        PlacesReviewService $placesReviewService,
        TranslationService $translationService,
    ): Response {
        $this->assertOwnership($reservation, $reservationRepository);
        $row = $reservationRepository->getUserFlexibleRow((int) $reservation->getId(), (int) $this->requireUser()->getId());
        if (!$row) {
            throw $this->createNotFoundException();
        }

        $review = [];
        if ($panier = $this->safePanier($reservation)) {
            $catalogService->enrichPanier($panier);
            $reviewMap = $placesReviewService->enrich([$panier]);
            $review = array_values($reviewMap)[0] ?? [];
        }

        $translated = $translationService->translateSummarySet((string) ($review['summary'] ?? ''));

        return $this->render('user_reservation/show.html.twig', [
            'reservation' => $reservation,
            'row' => $row,
            'calendarUrl' => $calendarExperienceService->buildGoogleCalendarUrl($reservation),
            'reviewInfo' => $review,
            'reviewTranslations' => $translated,
            'translationConfigured' => $translationService->isConfigured(),
        ]);
    }

    #[Route('/{id}/calendar.ics', name: 'app_user_reservation_calendar_ics', methods: ['GET'])]
    public function calendarIcs(Reservation $reservation, ReservationRepository $reservationRepository, CalendarExperienceService $calendarExperienceService): Response
    {
        $this->assertOwnership($reservation, $reservationRepository);
        $ics = $calendarExperienceService->buildIcs($reservation);
        if ($ics === null) {
            throw $this->createNotFoundException();
        }

        return new Response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="reservation-' . ($reservation->getCodeConfirmation() ?: $reservation->getId()) . '.ics"',
        ]);
    }

    #[Route('/{id}/pdf', name: 'app_user_reservation_pdf', methods: ['GET'])]
    public function generatePdf(Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $this->assertOwnership($reservation, $reservationRepository);
        $row = $reservationRepository->getUserFlexibleRow((int) $reservation->getId(), (int) $this->requireUser()->getId());
        if (!$row) {
            throw $this->createNotFoundException();
        }

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);
        $html = $this->renderView('user_reservation/pdf.html.twig', [
            'row' => $row,
            'generatedAt' => new \DateTimeImmutable(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reservation-' . $reservation->getCodeConfirmation() . '.pdf"',
        ]);
    }

    #[Route('/{id}/rate', name: 'app_user_reservation_rate', methods: ['POST'])]
    public function rate(Reservation $reservation, ReservationRepository $reservationRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwnership($reservation, $reservationRepository);
        if (!$reservation->isPaid()) {
            $this->addFlash('warning', 'Vous ne pouvez évaluer que les réservations payées.');
            return $this->redirectToRoute('app_user_reservation_show', ['id' => $reservation->getId()]);
        }

        $rating = (int) $request->request->get('rating');
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('danger', 'La note doit être comprise entre 1 et 5.');
            return $this->redirectToRoute('app_user_reservation_show', ['id' => $reservation->getId()]);
        }

        $reservation->setRating($rating);
        $reservation->setReviewComment((string) $request->request->get('comment', ''));
        $entityManager->flush();
        $this->addFlash('success', 'Merci pour votre retour.');

        return $this->redirectToRoute('app_user_reservation_show', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_user_reservation_cancel', methods: ['POST'])]
    public function cancel(Reservation $reservation, ReservationRepository $reservationRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwnership($reservation, $reservationRepository);
        if (!in_array($reservation->getNormalizedStatutPaiement(), ['En cours de paiement', 'En attente'], true)) {
            $this->addFlash('warning', 'Cette réservation ne peut plus être annulée.');
            return $this->redirectToRoute('app_user_reservation_show', ['id' => $reservation->getId()]);
        }

        if ($this->isCsrfTokenValid('cancel' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $reservation->setStatutPaiement('Rembourse');
            if ($panier = $this->safePanier($reservation)) {
                $panier->setStatutItem('annul?');
            }
            $entityManager->flush();
            $this->addFlash('success', 'Votre réservation a été annulée.');
        }

        return $this->redirectToRoute('app_user_reservation_index');
    }

    private function requireUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertOwnership(Reservation $reservation, ReservationRepository $reservationRepository): void
    {
        $user = $this->requireUser();
        if (!$reservationRepository->belongsToUser((int) $reservation->getId(), (int) $user->getId())) {
            throw $this->createAccessDeniedException();
        }
    }

    private function safePanier(Reservation $reservation): ?\App\Entity\Panier
    {
        try {
            return $reservation->getPanier();
        } catch (\Throwable) {
            return null;
        }
    }
}
