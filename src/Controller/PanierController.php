<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Utilisateur;
use App\Form\PanierType;
use App\Repository\PanierRepository;
use App\Service\BookingCatalogService;
use App\Service\CartPricingService;
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

#[Route('/panier')]
final class PanierController extends AbstractController
{
    #[Route(name: 'app_panier_index', methods: ['GET'])]
    public function index(Request $request, PanierRepository $panierRepository, PaginatorInterface $paginator, BookingCatalogService $catalogService): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $type = $request->query->get('type', '');

        $pagination = $paginator->paginate(
            $panierRepository->searchByFilters($search, $status, $type),
            $request->query->getInt('page', 1),
            10
        );
        $catalogService->enrichMany(iterator_to_array($pagination));

        $stats = $panierRepository->getStats();
        $typeBreakdown = $panierRepository->getTypeBreakdown();
        $fillRate = $stats['total'] > 0 ? round(($stats['confirme'] / max(1, $stats['total'])) * 100, 1) : 0;
        $pendingRate = $stats['total'] > 0 ? round(($stats['en_attente'] / max(1, $stats['total'])) * 100, 1) : 0;
        $avgTicket = $stats['total'] > 0 ? round(((float) $stats['total_prix']) / max(1, $stats['total']), 2) : 0;

        return $this->render('panier/index.html.twig', [
            'paniers' => $pagination,
            'searchQuery' => $search,
            'statusFilter' => $status,
            'typeFilter' => $type,
            'stats' => $stats,
            'topClients' => $panierRepository->getTopClients(),
            'typeBreakdown' => $typeBreakdown,
            'kpi' => [
                'fillRate' => $fillRate,
                'pendingRate' => $pendingRate,
                'avgTicket' => $avgTicket,
            ],
        ]);
    }

    #[Route('/pdf', name: 'app_panier_pdf', methods: ['GET'])]
    public function generatePdf(PanierRepository $panierRepository, BookingCatalogService $catalogService): Response
    {
        $paniers = $panierRepository->findBy([], ['id' => 'DESC']);
        $catalogService->enrichMany($paniers);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('panier/pdf.html.twig', [
            'paniers' => $paniers,
            'stats' => $panierRepository->getStats(),
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="paniers-dashboard.pdf"',
        ]);
    }

    #[Route('/excel', name: 'app_panier_excel', methods: ['GET'])]
    public function generateExcel(PanierRepository $panierRepository, BookingCatalogService $catalogService): StreamedResponse
    {
        $paniers = $panierRepository->findBy([], ['id' => 'DESC']);
        $catalogService->enrichMany($paniers);

        $response = new StreamedResponse(function () use ($paniers) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(['ID', 'Client', 'Email', 'Service', 'Type', 'Date début', 'Date fin', 'Personnes', 'Chambres', 'Prix estimé', 'Statut']));
            foreach ($paniers as $panier) {
                $client = $panier->getUtilisateur();
                $writer->addRow(Row::fromValues([
                    $panier->getId(),
                    trim(($client?->getPrenom() ?? '') . ' ' . ($client?->getNom() ?? '')),
                    $client?->getEmail(),
                    $panier->getDisplayName(),
                    $panier->getServiceTypeLabel(),
                    $panier->getDateDebut()?->format('Y-m-d H:i'),
                    $panier->getDateFin()?->format('Y-m-d H:i'),
                    $panier->getNbPersonnes(),
                    $panier->getNbChambres(),
                    $panier->getPrixEstime(),
                    $panier->getStatutLabel(),
                ]));
            }
            $writer->close();
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'paniers-dashboard.xlsx'));
        return $response;
    }

    #[Route('/new', name: 'app_panier_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        $this->addFlash('warning', 'Le backoffice panier est désormais en lecture seule.');
        return $this->redirectToRoute('app_panier_index');
    }

    #[Route('/{id}', name: 'app_panier_show', methods: ['GET'])]
    public function show(Panier $panier, BookingCatalogService $catalogService): Response
    {
        $catalogService->enrichPanier($panier);
        return $this->render('panier/show.html.twig', ['panier' => $panier]);
    }

    #[Route('/{id}/edit', name: 'app_panier_edit', methods: ['GET', 'POST'])]
    public function edit(Panier $panier, BookingCatalogService $catalogService): Response
    {
        $catalogService->enrichPanier($panier);
        $this->addFlash('warning', 'Le backoffice panier est en lecture seule. Vous pouvez uniquement consulter les paniers clients.');
        return $this->render('panier/show.html.twig', ['panier' => $panier]);
    }

    #[Route('/{id}', name: 'app_panier_delete', methods: ['POST'])]
    public function delete(): Response
    {
        $this->addFlash('warning', 'La suppression de paniers clients est désactivée dans le backoffice.');
        return $this->redirectToRoute('app_panier_index');
    }
}
