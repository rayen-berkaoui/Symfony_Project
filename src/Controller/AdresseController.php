<?php

namespace App\Controller;

use App\Entity\Adresse;
use App\Form\AdresseType;
use App\Repository\AdresseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/adresse')]
final class AdresseController extends AbstractController
{
    #[Route(name: 'app_adresse_index', methods: ['GET'])]
    public function index(Request $request, AdresseRepository $adresseRepository, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search') ?? '';
        $queryBuilder = $adresseRepository->searchByQuery($search);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10 // items per page
        );

        return $this->render('adresse/index.html.twig', [
            'adresses' => $pagination,
            'searchQuery' => $search
        ]);
    }

    #[Route('/map', name: 'app_adresse_map', methods: ['GET'])]
    public function map(AdresseRepository $adresseRepository): Response
    {
        $adresses = $adresseRepository->findAll();
        $markers = array_map(function($a) {
            return [
                'lat' => $a->getLatitude(),
                'lng' => $a->getLongitude(),
                'title' => $a->getRue() . ', ' . $a->getVille()
            ];
        }, $adresses);

        return $this->render('adresse/map.html.twig', [
            'markersJson' => json_encode($markers)
        ]);
    }

    #[Route('/pdf', name: 'app_adresse_pdf', methods: ['GET'])]
    public function generatePdf(AdresseRepository $adresseRepository): Response
    {
        $adresses = $adresseRepository->findAll();

                $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('adresse/pdf.html.twig', [
            'adresses' => $adresses
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="adresses.pdf"']
        );
    }

    #[Route('/new', name: 'app_adresse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $adresse = new Adresse();
        $form = $this->createForm(AdresseType::class, $adresse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($adresse);
            $entityManager->flush();

            $this->addFlash('success', 'Adresse crÃ©Ã©e avec succÃ¨s.');

            return $this->redirectToRoute('app_adresse_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('adresse/new.html.twig', [
            'adresse' => $adresse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_adresse_show', methods: ['GET'])]
    public function show(Adresse $adresse): Response
    {
        return $this->render('adresse/show.html.twig', [
            'adresse' => $adresse,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_adresse_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Adresse $adresse, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AdresseType::class, $adresse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Adresse mise Ã  jour avec succÃ¨s.');

            return $this->redirectToRoute('app_adresse_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('adresse/edit.html.twig', [
            'adresse' => $adresse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_adresse_delete', methods: ['POST'])]
    public function delete(Request $request, Adresse $adresse, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$adresse->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($adresse);
            $entityManager->flush();
            $this->addFlash('success', 'Adresse supprimÃ©e avec succÃ¨s.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_adresse_index', [], Response::HTTP_SEE_OTHER);
    }
}

