<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/reservation')]
final class ReservationController extends AbstractController
{
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search') ?? '';
        $queryBuilder = $reservationRepository->searchByQuery($search);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            6
        );

        $stats = $reservationRepository->getStats();

        return $this->render('reservation/index.html.twig', [
            'reservations' => $pagination,
            'searchQuery' => $search,
            'stats' => $stats,
        ]);
    }

    #[Route('/pdf', name: 'app_reservation_pdf', methods: ['GET'])]
    public function generatePdf(ReservationRepository $reservationRepository): Response
    {
        $reservations = $reservationRepository->findAll();

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = "<h1 style='color: #D4AF37;'>Liste des Réservations</h1>";
        $html .= "<p>Généré le " . date('d/m/Y à H:i') . "</p>";
        $html .= "<table border='1' width='100%' cellpadding='8' style='border-collapse: collapse;'>";
        $html .= "<tr style='background-color: #1A1A1A; color: #FFD700;'>";
        $html .= "<th>Code</th><th>Lieu</th><th>Date Paiement</th><th>Montant</th><th>Mode</th><th>Statut</th><th>Note</th>";
        $html .= "</tr>";

        foreach ($reservations as $reservation) {
            $lieu = $reservation->getPanier()?->getLieuTouristique();
            $html .= "<tr>";
            $html .= "<td>" . $reservation->getCodeConfirmation() . "</td>";
            $html .= "<td>" . ($lieu ? $lieu->getNom() : 'N/A') . "</td>";
            $html .= "<td>" . ($reservation->getDatePaiement() ? $reservation->getDatePaiement()->format('d/m/Y H:i') : '') . "</td>";
            $html .= "<td>" . number_format((float)$reservation->getMontantTotal(), 2) . " TND</td>";
            $html .= "<td>" . $reservation->getModePaiement() . "</td>";
            $html .= "<td>" . $reservation->getStatutPaiement() . "</td>";
            $html .= "<td>" . ($reservation->getRating() ? $reservation->getStarRating() : '-') . "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="reservations.pdf"']
        );
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reservation = new Reservation();
        $reservation->setDatePaiement(new \DateTime());
        $reservation->setCodeConfirmation(strtoupper(substr(uniqid('RES-'), 0, 10)));

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($reservation);
            $entityManager->flush();

            // Auto update Panier status if Paid
            if ($reservation->getPanier() && $reservation->getStatutPaiement() === 'PayÃ©') {
                $reservation->getPanier()->setStatutItem('confirmÃ©');
                $entityManager->flush();
            }

            $this->addFlash('success', 'La rÃ©servation a Ã©tÃ© crÃ©Ã©e avec succÃ¨s.');
            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update panier status based on payment status
            $panier = $reservation->getPanier();
            if ($panier) {
                if ($reservation->getStatutPaiement() === 'Payé') {
                    $panier->setStatutItem('confirmé');
                } elseif ($reservation->getStatutPaiement() === 'Annulé') {
                    $panier->setStatutItem('annulé');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'La réservation a été modifiée avec succès.');
            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($reservation);
            $entityManager->flush();
            $this->addFlash('success', 'La réservation a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }
}
