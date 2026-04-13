<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Form\PanierType;
use App\Repository\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/panier')]
final class PanierController extends AbstractController
{
    #[Route(name: 'app_panier_index', methods: ['GET'])]
    public function index(Request $request, PanierRepository $panierRepository, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search') ?? '';
        $queryBuilder = $panierRepository->searchByQuery($search);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            6
        );

        $stats = $panierRepository->getStats();

        return $this->render('panier/index.html.twig', [
            'paniers' => $pagination,
            'searchQuery' => $search,
            'stats' => $stats,
        ]);
    }

    #[Route('/pdf', name: 'app_panier_pdf', methods: ['GET'])]
    public function generatePdf(PanierRepository $panierRepository): Response
    {
        $paniers = $panierRepository->findAll();

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = "<h1 style='color: #D4AF37;'>Liste des Paniers</h1>";
        $html .= "<p>Généré le " . date('d/m/Y à H:i') . "</p>";
        $html .= "<table border='1' width='100%' cellpadding='8' style='border-collapse: collapse;'>";
        $html .= "<tr style='background-color: #1A1A1A; color: #FFD700;'>";
        $html .= "<th>ID</th><th>Lieu</th><th>Type</th><th>Date Début</th><th>Date Fin</th><th>Personnes</th><th>Prix</th><th>Statut</th>";
        $html .= "</tr>";

        foreach ($paniers as $panier) {
            $html .= "<tr>";
            $html .= "<td>" . $panier->getId() . "</td>";
            $html .= "<td>" . ($panier->getLieuTouristique() ? $panier->getLieuTouristique()->getNom() : 'N/A') . "</td>";
            $html .= "<td>" . $panier->getTypeService() . "</td>";
            $html .= "<td>" . ($panier->getDateDebut() ? $panier->getDateDebut()->format('d/m/Y') : '') . "</td>";
            $html .= "<td>" . ($panier->getDateFin() ? $panier->getDateFin()->format('d/m/Y') : '') . "</td>";
            $html .= "<td>" . $panier->getNbPersonnes() . "</td>";
            $html .= "<td>" . number_format((float)$panier->getPrixEstime(), 2) . " TND</td>";
            $html .= "<td>" . $panier->getStatutLabel() . "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="paniers.pdf"']
        );
    }

    #[Route('/new', name: 'app_panier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $panier = new Panier();
        $panier->setSessionId($request->getSession()->getId());
        
        if ($this->getUser()) {
            $panier->setUtilisateur($this->getUser());
        }

        $form = $this->createForm(PanierType::class, $panier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculate total persons
            $panier->setNbPersonnes($panier->getNbAdultes() + $panier->getNbEnfants());
            // Auto-calculate price if lieu is set and no explicit price is provided
            if ($panier->getLieuTouristique() && empty($panier->getPrixEstime())) {
                $lieuPrice = (float) $panier->getLieuTouristique()->getPrix();
                $prixEstime = $lieuPrice * $panier->getNbPersonnes() * $panier->getNbJours();
                $panier->setPrixEstime((string) $prixEstime);
            }
            $entityManager->persist($panier);
            $entityManager->flush();

            $this->addFlash('success', 'Le panier a été créé avec succès.');
            return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('panier/new.html.twig', [
            'panier' => $panier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_panier_show', methods: ['GET'])]
    public function show(Panier $panier): Response
    {
        return $this->render('panier/show.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_panier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Panier $panier, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PanierType::class, $panier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true, true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculate total persons
            $panier->setNbPersonnes($panier->getNbAdultes() + $panier->getNbEnfants());
            // Auto-recalculate price if needed
            if ($panier->getLieuTouristique() && empty($panier->getPrixEstime())) {
                $lieuPrice = (float) $panier->getLieuTouristique()->getPrix();
                $prixEstime = $lieuPrice * $panier->getNbPersonnes() * $panier->getNbJours();
                $panier->setPrixEstime((string) $prixEstime);
            }
            $entityManager->flush();

            $this->addFlash('success', 'Le panier a été modifié avec succès.');
            return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('panier/edit.html.twig', [
            'panier' => $panier,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_panier_delete', methods: ['POST'])]
    public function delete(Request $request, Panier $panier, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$panier->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($panier);
            $entityManager->flush();
            $this->addFlash('success', 'Le panier a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
    }
}

