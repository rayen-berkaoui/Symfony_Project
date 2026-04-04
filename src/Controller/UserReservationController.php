<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/mes-reservations')]
final class UserReservationController extends AbstractController
{
    #[Route(name: 'app_user_reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository): Response
    {
        $sessionId = $request->getSession()->getId();
        $reservations = $reservationRepository->findBySessionId($sessionId);

        // Calculate stats
        $totalReservations = count($reservations);
        $paidCount = 0;
        $pendingCount = 0;
        $totalSpent = 0;

        foreach ($reservations as $reservation) {
            if ($reservation->getStatutPaiement() === 'Payé') {
                $paidCount++;
                $totalSpent += (float) $reservation->getMontantTotal();
            } elseif ($reservation->getStatutPaiement() === 'En cours de paiement') {
                $pendingCount++;
            }
        }

        return $this->render('user_reservation/index.html.twig', [
            'reservations' => $reservations,
            'stats' => [
                'total' => $totalReservations,
                'paye' => $paidCount,
                'en_cours' => $pendingCount,
                'total_spent' => $totalSpent,
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_user_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation, Request $request): Response
    {
        // Verify ownership via session
        if ($reservation->getPanier()?->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_user_reservation_index');
        }

        return $this->render('user_reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/pdf', name: 'app_user_reservation_pdf', methods: ['GET'])]
    public function generatePdf(Reservation $reservation, Request $request): Response
    {
        // Verify ownership via session
        if ($reservation->getPanier()?->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_user_reservation_index');
        }

        $panier = $reservation->getPanier();
        $lieu = $panier?->getLieuTouristique();

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = "
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .header { background: #1A1A1A; color: #FFD700; padding: 20px; text-align: center; }
            .content { padding: 30px; }
            .info-row { margin: 10px 0; border-bottom: 1px solid #eee; padding: 10px 0; }
            .label { font-weight: bold; color: #666; }
            .code { background: #f5f5f5; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; margin: 20px 0; }
        </style>
        <div class='header'>
            <h1>TABAANY</h1>
            <p>Confirmation de Réservation</p>
        </div>
        <div class='content'>
            <div class='code'>Code: {$reservation->getCodeConfirmation()}</div>

            <div class='info-row'>
                <span class='label'>Lieu:</span> " . ($lieu ? $lieu->getNom() : 'N/A') . "
            </div>
            <div class='info-row'>
                <span class='label'>Ville:</span> " . ($lieu ? $lieu->getVille() : 'N/A') . "
            </div>
            <div class='info-row'>
                <span class='label'>Date de début:</span> " . ($panier?->getDateDebut()?->format('d/m/Y') ?? 'N/A') . "
            </div>
            <div class='info-row'>
                <span class='label'>Date de fin:</span> " . ($panier?->getDateFin()?->format('d/m/Y') ?? 'N/A') . "
            </div>
            <div class='info-row'>
                <span class='label'>Nombre de personnes:</span> " . ($panier?->getNbPersonnes() ?? 0) . " ({$panier?->getNbAdultes()} adultes, {$panier?->getNbEnfants()} enfants)
            </div>
            <div class='info-row'>
                <span class='label'>Mode de paiement:</span> {$reservation->getModePaiement()}
            </div>
            <div class='info-row'>
                <span class='label'>Statut:</span> {$reservation->getStatutPaiement()}
            </div>
            <div class='info-row'>
                <span class='label'>Montant total:</span> <strong>" . number_format((float)$reservation->getMontantTotal(), 2) . " TND</strong>
            </div>

            <p style='margin-top: 30px; color: #666; font-size: 12px;'>
                Généré le " . date('d/m/Y à H:i') . "<br>
                Merci d'avoir choisi Tabaany!
            </p>
        </div>
        ";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="reservation-' . $reservation->getCodeConfirmation() . '.pdf"'
            ]
        );
    }

    #[Route('/{id}/rate', name: 'app_user_reservation_rate', methods: ['POST'])]
    public function rate(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verify ownership via session
        if ($reservation->getPanier()?->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_user_reservation_index');
        }

        // Only allow rating for paid reservations
        if ($reservation->getStatutPaiement() !== 'Payé') {
            $this->addFlash('warning', 'Vous ne pouvez évaluer que les réservations payées.');
            return $this->redirectToRoute('app_user_reservation_show', ['id' => $reservation->getId()]);
        }

        $rating = (int) $request->request->get('rating');
        $comment = $request->request->get('comment', '');

        if ($rating >= 1 && $rating <= 5) {
            $reservation->setRating($rating);
            $reservation->setReviewComment($comment);
            $entityManager->flush();

            $this->addFlash('success', 'Merci pour votre évaluation !');
        } else {
            $this->addFlash('danger', 'La note doit être entre 1 et 5.');
        }

        return $this->redirectToRoute('app_user_reservation_show', ['id' => $reservation->getId()]);
    }

    #[Route('/{id}/cancel', name: 'app_user_reservation_cancel', methods: ['POST'])]
    public function cancel(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verify ownership via session
        if ($reservation->getPanier()?->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_user_reservation_index');
        }

        // Only allow cancellation for pending payments
        if ($reservation->getStatutPaiement() !== 'En cours de paiement') {
            $this->addFlash('warning', 'Vous ne pouvez annuler que les réservations en cours de paiement.');
            return $this->redirectToRoute('app_user_reservation_show', ['id' => $reservation->getId()]);
        }

        if ($this->isCsrfTokenValid('cancel' . $reservation->getId(), $request->request->get('_token'))) {
            $reservation->setStatutPaiement('Annulé');
            $reservation->getPanier()?->setStatutItem('annulé');
            $entityManager->flush();

            $this->addFlash('success', 'Votre réservation a été annulée.');
        }

        return $this->redirectToRoute('app_user_reservation_index');
    }
}
