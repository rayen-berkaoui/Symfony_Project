<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, UtilisateurRepository $utilisateurRepository): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            return $this->redirectToRoute('app_home');
        }

        $lastUsername = $authenticationUtils->getLastUsername();
        $requiresTotp = false;

        if ($lastUsername !== '') {
            $user = $utilisateurRepository->findByEmailOrNumTel($lastUsername);
            $requiresTotp = $user instanceof Utilisateur && $user->isTotpEnabled() === true;
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'requires_totp' => $requiresTotp,
        ]);
    }

    #[Route('/login/totp-check', name: 'app_login_totp_check', methods: ['POST'])]
    public function checkTotp(Request $request, UtilisateurRepository $utilisateurRepository): JsonResponse
    {
        $identifier = trim((string) $request->request->get('identifier', ''));
        $requiresTotp = false;

        if ($identifier !== '') {
            $user = $utilisateurRepository->findByEmailOrNumTel($identifier);
            $requiresTotp = $user instanceof Utilisateur && $user->isTotpEnabled() === true;
        }

        return new JsonResponse(['requiresTotp' => $requiresTotp]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method is blank and handled by the firewall logout key.');
    }

    #[Route('/signup', name: 'app_signup', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        RoleRepository $roleRepository,
        MailerService $mailerService
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $utilisateur = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $utilisateur->setMotDePasse($passwordHasher->hashPassword($utilisateur, $plainPassword));
            $utilisateur->setStatut(Utilisateur::STATUT_ACTIF);

            $defaultRole = $roleRepository->findOneBy(['nom' => 'TOURISTE'])
                ?? $roleRepository->findOneBy([], ['id' => 'ASC']);

            if ($defaultRole !== null) {
                $utilisateur->setRole($defaultRole);
            }

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            // Send welcome email
            try {
                $mailerService->sendWelcomeEmail($utilisateur);
                $this->addFlash('success', 'Registration successful! A welcome email has been sent to your inbox.');
            } catch (\Exception $e) {
                // Log the error but don't block registration if email fails
                $this->addFlash('warning', 'Registration successful! Welcome email could not be sent, but you can still log in.');
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
