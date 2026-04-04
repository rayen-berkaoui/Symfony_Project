<?php

namespace App\Controller;

use App\Entity\LieuTouristique;
use App\Form\LieuTouristiqueType;
use App\Repository\LieuTouristiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/lieu/touristique')]
final class LieuTouristiqueController extends AbstractController
{
    #[Route(name: 'app_lieu_touristique_index', methods: ['GET'])]
    public function index(Request $request, LieuTouristiqueRepository $lieuTouristiqueRepository, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search') ?? '';
        $queryBuilder = $lieuTouristiqueRepository->searchByQuery($search);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            6 // items per page
        );

        return $this->render('lieu_touristique/index.html.twig', [
            'lieu_touristiques' => $pagination,
            'searchQuery' => $search
        ]);
    }

    #[Route('/pdf', name: 'app_lieu_touristique_pdf', methods: ['GET'])]
    public function generatePdf(LieuTouristiqueRepository $lieuTouristiqueRepository): Response
    {
        $lieux = $lieuTouristiqueRepository->findAll();

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        // Very basic simple style for PDF
        $html = "<h1>Liste des Lieux Touristiques</h1>";
        $html .= "<table border='1' width='100%' cellpadding='5'><tr><th>Nom</th><th>Ville</th><th>Prix</th><th>Statut</th></tr>";
        foreach ($lieux as $l) {
            $html .= "<tr><td>{$l->getNom()}</td><td>{$l->getVille()}</td><td>{$l->getPrix()} TND</td><td>" . ($l->getStatut() ? 'Actif' : 'Inactif') . "</td></tr>";
        }
        $html .= "</table>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="lieux_touristiques.pdf"']
        );
    }

    #[Route('/new', name: 'app_lieu_touristique_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $lieuTouristique = new LieuTouristique();
        $form = $this->createForm(LieuTouristiqueType::class, $lieuTouristique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedImage = $form->get('imageFile')->getData();
            if ($uploadedImage instanceof UploadedFile) {
                $uploadedImagePath = $this->uploadImage($uploadedImage, $slugger);
                if ($uploadedImagePath !== null) {
                    $lieuTouristique->setImage($uploadedImagePath);
                }
            }

            $entityManager->persist($lieuTouristique);
            $entityManager->flush();

            return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lieu_touristique/new.html.twig', [
            'lieu_touristique' => $lieuTouristique,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lieu_touristique_show', methods: ['GET'])]
    public function show(LieuTouristique $lieuTouristique): Response
    {
        return $this->render('lieu_touristique/show.html.twig', [
            'lieu_touristique' => $lieuTouristique,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_lieu_touristique_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, LieuTouristique $lieuTouristique, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(LieuTouristiqueType::class, $lieuTouristique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedImage = $form->get('imageFile')->getData();
            if ($uploadedImage instanceof UploadedFile) {
                $uploadedImagePath = $this->uploadImage($uploadedImage, $slugger);
                if ($uploadedImagePath !== null) {
                    $lieuTouristique->setImage($uploadedImagePath);
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lieu_touristique/edit.html.twig', [
            'lieu_touristique' => $lieuTouristique,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_lieu_touristique_delete', methods: ['POST'])]
    public function delete(Request $request, LieuTouristique $lieuTouristique, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lieuTouristique->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($lieuTouristique);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_lieu_touristique_index', [], Response::HTTP_SEE_OTHER);
    }

    private function uploadImage(UploadedFile $uploadedFile, SluggerInterface $slugger): ?string
    {
        $uploadDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/lieux';
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0775, true);
        }

        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $slugger->slug($originalName);
        $extension = $uploadedFile->guessExtension() ?: 'bin';
        $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$extension;

        try {
            $uploadedFile->move($uploadDirectory, $newFilename);
        } catch (FileException) {
            $this->addFlash('error', 'Echec du telechargement de l\'image.');

            return null;
        }

        return 'uploads/lieux/'.$newFilename;
    }
}
