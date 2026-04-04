<?php

namespace App\Controller;

use App\Repository\AdresseRepository;
use App\Repository\CategorieRepository;
use App\Repository\LieuTouristiqueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HomeController extends AbstractController
{
    private function getStats(
        LieuTouristiqueRepository $lieuRepository,
        CategorieRepository $categorieRepository,
        AdresseRepository $adresseRepository
    ): array {
        $stats = [
            'total_lieux' => $lieuRepository->count([]),
            'total_categories' => $categorieRepository->count([]),
            'total_adresses' => $adresseRepository->count([]),
        ];

        $avgPrice = $lieuRepository->createQueryBuilder('l')
            ->select('AVG(l.prix)')
            ->getQuery()
            ->getSingleScalarResult();
        $stats['avg_price'] = $avgPrice ? round((float)$avgPrice, 2) : 0;

        $stats['active_lieux'] = $lieuRepository->count(['statut' => true]);
        $stats['inactive_lieux'] = $stats['total_lieux'] - $stats['active_lieux'];

        return $stats;
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        LieuTouristiqueRepository $lieuRepository,
        CategorieRepository $categorieRepository,
        AdresseRepository $adresseRepository
    ): Response {
        $stats = $this->getStats($lieuRepository, $categorieRepository, $adresseRepository);

        // Places by Category Data for Pie Chart
        $lieuxParCategorie = $lieuRepository->createQueryBuilder('l')
            ->select('c.nomCategorie as category', 'COUNT(l.id) as count')
            ->join('l.categorie', 'c')
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $chartLabels = [];
        $chartData = [];
        foreach ($lieuxParCategorie as $item) {
            $chartLabels[] = $item['category'];
            $chartData[] = $item['count'];
        }

        // Adresses by Ville Data for Pie Chart
        $adressesParVille = $adresseRepository->createQueryBuilder('a')
            ->select('a.ville as ville', 'COUNT(a.id) as count')
            ->groupBy('ville')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $adresseLabels = [];
        $adresseData = [];
        foreach ($adressesParVille as $item) {
            $adresseLabels[] = $item['ville'];
            $adresseData[] = $item['count'];
        }

        // Get 5 most recently updated or added places (using ID as a proxy for recency)
        $recentLieux = $lieuRepository->findBy([], ['id' => 'DESC'], 5);

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'recent_lieux' => $recentLieux,
            'chart_labels' => json_encode($chartLabels),
            'chart_data' => json_encode($chartData),
            'adresse_labels' => json_encode($adresseLabels),
            'adresse_data' => json_encode($adresseData),
        ]);
    }

    #[Route('/dashboard/pdf', name: 'app_dashboard_pdf', methods: ['GET'])]
    public function exportPdf(
        LieuTouristiqueRepository $lieuRepository,
        CategorieRepository $categorieRepository,
        AdresseRepository $adresseRepository
    ): Response {
        $stats = $this->getStats($lieuRepository, $categorieRepository, $adresseRepository);
        $recentLieux = $lieuRepository->findBy([], ['id' => 'DESC'], 10); // Fetch up to 10 for report

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = "<h1>Rapport du Tableau de Bord Tabaany</h1>";
        $html .= "<h2>Statistiques Globales</h2>";
        $html .= "<ul>";
        $html .= "<li>Total des Lieux Touristiques : {$stats['total_lieux']} (Actifs: {$stats['active_lieux']}, Inactifs: {$stats['inactive_lieux']})</li>";
        $html .= "<li>Total des Catégories : {$stats['total_categories']}</li>";
        $html .= "<li>Total des Adresses : {$stats['total_adresses']}</li>";
        $html .= "<li>Prix Moyen : {$stats['avg_price']} DT</li>";
        $html .= "</ul>";

        $html .= "<h2>Derniers Lieux Ajoutés</h2>";
        $html .= "<table border='1' width='100%' cellpadding='5'><tr><th>Nom</th><th>Catégorie</th><th>Ville</th><th>Prix</th></tr>";
        foreach ($recentLieux as $l) {
            $catNom = $l->getCategorie() ? $l->getCategorie()->getNomCategorie() : 'N/A';
            $html .= "<tr><td>{$l->getNom()}</td><td>{$catNom}</td><td>{$l->getVille()}</td><td>{$l->getPrix()} DT</td></tr>";
        }
        $html .= "</table>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="dashboard_rapport.pdf"']
        );
    }

    #[Route('/dashboard/excel', name: 'app_dashboard_excel', methods: ['GET'])]
    public function exportExcel(
        LieuTouristiqueRepository $lieuRepository,
        CategorieRepository $categorieRepository,
        AdresseRepository $adresseRepository
    ): Response {
        $stats = $this->getStats($lieuRepository, $categorieRepository, $adresseRepository);
        $recentLieux = $lieuRepository->findBy([], ['id' => 'DESC'], 20);

        $response = new StreamedResponse(function () use ($stats, $recentLieux) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            // Sheet 1: Stats summary
            $writer->addRow(Row::fromValues(['Rapport du Tableau de Bord Tabaany']));
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues(['Statistique', 'Valeur']));
            $writer->addRow(Row::fromValues(['Total des Lieux', $stats['total_lieux']]));
            $writer->addRow(Row::fromValues(['Lieux Actifs', $stats['active_lieux']]));
            $writer->addRow(Row::fromValues(['Lieux Inactifs', $stats['inactive_lieux']]));
            $writer->addRow(Row::fromValues(['Total des Catégories', $stats['total_categories']]));
            $writer->addRow(Row::fromValues(['Total des Adresses', $stats['total_adresses']]));
            $writer->addRow(Row::fromValues(['Prix Moyen (DT)', $stats['avg_price']]));
            
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues(['Derniers Lieux Ajoutés']));
            $writer->addRow(Row::fromValues(['ID', 'Nom', 'Catégorie', 'Ville', 'Prix', 'Statut']));
            
            foreach ($recentLieux as $l) {
                $catNom = $l->getCategorie() ? $l->getCategorie()->getNomCategorie() : 'N/A';
                $statut = $l->getStatut() ? 'Actif' : 'Inactif';
                $writer->addRow(Row::fromValues([
                    $l->getId(),
                    $l->getNom(),
                    $catNom,
                    $l->getVille(),
                    $l->getPrix(),
                    $statut
                ]));
            }

            $writer->close();
        });

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'dashboard_rapport.xlsx'
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        return $response;
    }
}
