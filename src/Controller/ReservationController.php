<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use App\Service\BookingCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservation')]
final class ReservationController extends AbstractController
{
    #[Route(name: 'app_reservation_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository, PaginatorInterface $paginator, BookingCatalogService $catalogService): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $mode = $request->query->get('mode', '');

        $pagination = $paginator->paginate(
            $reservationRepository->searchByFilters($search, $status, $mode),
            $request->query->getInt('page', 1),
            10
        );

        foreach ($pagination as $reservation) {
            if ($panier = $this->safePanier($reservation)) {
                $catalogService->enrichPanier($panier);
            }
        }

        $stats = $reservationRepository->getStats();
        $modeBreakdown = $reservationRepository->getModeBreakdown();
        $paidRate = $stats['total'] > 0 ? round(($stats['paye'] / max(1, $stats['total'])) * 100, 1) : 0;
        $inProgressRate = $stats['total'] > 0 ? round(($stats['en_cours'] / max(1, $stats['total'])) * 100, 1) : 0;
        $avgRevenue = $stats['paye'] > 0 ? round(((float) $stats['revenue']) / max(1, $stats['paye']), 2) : 0;

        return $this->render('reservation/index.html.twig', [
            'reservations' => $pagination,
            'searchQuery' => $search,
            'statusFilter' => $status,
            'modeFilter' => $mode,
            'stats' => $stats,
            'modeBreakdown' => $modeBreakdown,
            'recentRatings' => $reservationRepository->getRecentRatings(),
            'kpi' => [
                'paidRate' => $paidRate,
                'inProgressRate' => $inProgressRate,
                'avgRevenue' => $avgRevenue,
            ],
        ]);
    }

    #[Route('/pdf', name: 'app_reservation_pdf', methods: ['GET'])]
    public function generatePdf(ReservationRepository $reservationRepository): Response
    {
        $rows = $reservationRepository->getExportRows();

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);
        $html = $this->renderView('reservation/pdf.html.twig', [
            'rows' => $rows,
            'stats' => $reservationRepository->getStats(),
            'generatedAt' => new \DateTimeImmutable(),
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reservations-dashboard.pdf"',
        ]);
    }

    #[Route('/excel', name: 'app_reservation_excel', methods: ['GET'])]
    public function generateExcel(ReservationRepository $reservationRepository): StreamedResponse
    {
        $rows = $reservationRepository->getExportRows();

        $response = new StreamedResponse(function () use ($rows) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(['ID', 'Code', 'Client', 'Email', 'Service', 'Date paiement', 'Montant', 'Mode', 'Statut', 'Note']));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues([
                    $row['id'],
                    $row['codeConfirmation'],
                    $row['clientName'],
                    $row['clientEmail'],
                    $row['displayName'],
                    $row['datePaiement']?->format('Y-m-d H:i'),
                    $row['montantTotal'],
                    $row['modePaiementLabel'],
                    $row['normalizedStatutPaiement'],
                    $row['rating'],
                ]));
            }
            $writer->close();
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'reservations-dashboard.xlsx'));
        return $response;
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        $this->addFlash('warning', 'La création manuelle de réservations est désactivée. Les réservations doivent provenir du panier client.');
        return $this->redirectToRoute('app_reservation_index');
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation, BookingCatalogService $catalogService): Response
    {
        if ($panier = $this->safePanier($reservation)) {
            $catalogService->enrichPanier($panier);
        }
        return $this->render('reservation/show.html.twig', ['reservation' => $reservation]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Reservation $reservation, BookingCatalogService $catalogService): Response
    {
        if ($panier = $this->safePanier($reservation)) {
            $catalogService->enrichPanier($panier);
        }
        $this->addFlash('warning', "Vous ne pouvez plus modifier directement une réservation. Utilisez l'action de validation espèces quand elle est disponible.");
        return $this->render('reservation/show.html.twig', ['reservation' => $reservation]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(): Response
    {
        $this->addFlash('warning', 'La suppression de réservations est désactivée dans le backoffice.');
        return $this->redirectToRoute('app_reservation_index');
    }

    #[Route('/{id}/cash-paid', name: 'app_reservation_cash_paid', methods: ['POST'])]
    public function markCashPaid(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('cash_paid' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Action non autorisée.');
            return $this->redirectToRoute('app_reservation_index');
        }

        if (!in_array($reservation->getModePaiementLabel(), ['Espèces', 'Paymee'], true)) {
            $this->addFlash('warning', 'Seules les réservations Paymee ou en espèces peuvent être validées manuellement.');
            return $this->redirectToRoute('app_reservation_index');
        }

        if ($reservation->getNormalizedStatutPaiement() === 'Paye') {
            $this->addFlash('info', 'Cette réservation est déjà payée.');
            return $this->redirectToRoute('app_reservation_index');
        }

        $reservation->setStatutPaiement('Paye');
        if (!$reservation->getDatePaiement()) {
            $reservation->setDatePaiement(new \DateTime());
        }
        $this->syncPanierStatus($reservation);
        $entityManager->flush();
        $this->addFlash('success', sprintf('Le paiement %s a été validé.', strtolower($reservation->getModePaiementLabel())));

        return $this->redirectToRoute('app_reservation_index');
    }

    private function syncPanierStatus(Reservation $reservation): void
    {
        if (!$panier = $this->safePanier($reservation)) {
            return;
        }

        $panier->setStatutItem(match ($reservation->getNormalizedStatutPaiement()) {
            'Paye' => 'confirme',
            'Rembourse', 'Annule' => 'annul?',
            default => 'en_attente',
        });
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
