<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Reservation;
use App\Form\AddToCartType;
use App\Form\CheckoutType;
use App\Repository\LieuTouristiqueRepository;
use App\Repository\EtablissementRepository;
use App\Repository\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends AbstractController
{
    #[Route(name: 'app_cart_index', methods: ['GET'])]
    public function index(Request $request, PanierRepository $panierRepository): Response
    {
        $sessionId = $request->getSession()->getId();
        $cartItems = $panierRepository->findBySessionId($sessionId);
        $totalPrice = $panierRepository->totalBySessionId($sessionId);
        $itemCount = $panierRepository->countBySessionId($sessionId);

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'totalPrice' => $totalPrice,
            'itemCount' => $itemCount,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['GET', 'POST'])]
    public function add(
        int $id,
        Request $request,
        LieuTouristiqueRepository $lieuRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $lieu = $lieuRepository->find($id);
        if (!$lieu) {
            $this->addFlash('danger', 'Lieu touristique non trouvé.');
            return $this->redirectToRoute('app_lieu_touristique_index');
        }

        $form = $this->createForm(AddToCartType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $panier = new Panier();
            $panier->setLieuTouristique($lieu);
            $panier->setSessionId($request->getSession()->getId());
            
            if ($this->getUser()) {
                $panier->setUtilisateur($this->getUser());
            }
            
            $panier->setTypeService('Visite');
            $panier->setDateDebut($data['dateDebut']);
            $panier->setDateFin($data['dateFin']);
            $panier->setNbAdultes($data['nbAdultes']);
            $panier->setNbEnfants($data['nbEnfants']);
            $panier->setNbPersonnes($data['nbAdultes'] + $data['nbEnfants']);

            // Calculate price: lieu price * number of persons * number of days
            $lieuPrice = (float) $lieu->getPrix();
            $nbJours = $panier->getNbJours();
            $prixEstime = $lieuPrice * $panier->getNbPersonnes() * $nbJours;
            $panier->setPrixEstime((string) $prixEstime);

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

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(
        Panier $panier,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verify ownership via session
        if ($panier->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_cart_index');
        }

        $nbAdultes = (int) $request->request->get('nbAdultes', 1);
        $nbEnfants = (int) $request->request->get('nbEnfants', 0);

        $panier->setNbAdultes(max(1, $nbAdultes));
        $panier->setNbEnfants(max(0, $nbEnfants));
        $panier->setNbPersonnes($panier->getNbAdultes() + $panier->getNbEnfants());

        // Recalculate price
        $lieuPrice = (float) $panier->getLieuTouristique()?->getPrix();
        $prixEstime = $lieuPrice * $panier->getNbPersonnes() * $panier->getNbJours();
        $panier->setPrixEstime((string) $prixEstime);

        $entityManager->flush();

        $this->addFlash('success', 'Le panier a été mis à jour.');
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(
        Panier $panier,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Verify ownership via session
        if ($panier->getSessionId() !== $request->getSession()->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('app_cart_index');
        }

        if ($this->isCsrfTokenValid('remove' . $panier->getId(), $request->request->get('_token'))) {
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
        EntityManagerInterface $entityManager
    ): Response {
        $sessionId = $request->getSession()->getId();
        $cartItems = $panierRepository->findBySessionId($sessionId);

        if (empty($cartItems)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $totalPrice = $panierRepository->totalBySessionId($sessionId);

        $form = $this->createForm(CheckoutType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Create reservations for all cart items
            foreach ($cartItems as $panier) {
                $reservation = new Reservation();
                $reservation->setPanier($panier);
                $reservation->setDatePaiement(new \DateTime());
                $reservation->setMontantTotal($panier->getPrixEstime());
                $reservation->setModePaiement($data['modePaiement']);
                $reservation->setStatutPaiement('En cours de paiement');

                // Update panier status
                $panier->setStatutItem('confirmé');

                $entityManager->persist($reservation);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Votre réservation a été confirmée ! Vous recevrez bientôt un email de confirmation.');
            return $this->redirectToRoute('app_user_reservation_index');
        }

        return $this->render('cart/checkout.html.twig', [
            'cartItems' => $cartItems,
            'totalPrice' => $totalPrice,
            'form' => $form,
        ]);
    }

    #[Route('/count', name: 'app_cart_count', methods: ['GET'])]
    public function count(Request $request, PanierRepository $panierRepository): JsonResponse
    {
        $sessionId = $request->getSession()->getId();
        $count = $panierRepository->countBySessionId($sessionId);

        return new JsonResponse(['count' => $count]);
    }

    #[Route('/add-etablissement/{id}', name: 'app_cart_etablissement_add', methods: ['GET', 'POST'])]
    public function addEtablissement(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $etablissement = $entityManager->getRepository(\App\Entity\Etablissement::class)->find($id);
        if (!$etablissement) {
            $this->addFlash('danger', 'Établissement non trouvé.');
            return $this->redirectToRoute('app_etablissement_index');
        }

        $form = $this->createForm(AddToCartType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $panier = new Panier();
            $panier->setEtablissement($etablissement);
            $panier->setSessionId($request->getSession()->getId());

            if ($this->getUser()) {
                $panier->setUtilisateur($this->getUser());
            }

            $panier->setTypeService('Visite');
            $panier->setDateDebut($data['dateDebut']);
            $panier->setDateFin($data['dateFin']);
            $panier->setNbAdultes($data['nbAdultes']);
            $panier->setNbEnfants($data['nbEnfants']);
            $panier->setNbPersonnes($data['nbAdultes'] + $data['nbEnfants']);

            // Arbitrary price for etablissement if it has no price
            $prixEstime = 0;
            $panier->setPrixEstime((string) $prixEstime);

            $entityManager->persist($panier);
            $entityManager->flush();

            $this->addFlash('success', 'L\'établissement a été ajouté à votre panier.');

            return $this->redirectToRoute('app_cart_index');
        }

        return $this->render('cart/add_etablissement.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
        ]);
    }
}
