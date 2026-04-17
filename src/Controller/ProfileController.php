<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if ($user instanceof Utilisateur && $request->isMethod('GET') && $user->needsPhoneUpdate()) {
            $this->addFlash('warning', 'Veuillez renseigner votre vrai numero de telephone pour finaliser votre compte.');
        }

        if ($request->isMethod('POST') && $user instanceof Utilisateur) {
            if ($this->isCsrfTokenValid('profile_update', (string) $request->request->get('_token'))) {
                $nom = trim((string) $request->request->get('nom', $user->getNom() ?? ''));
                $prenom = trim((string) $request->request->get('prenom', $user->getPrenom() ?? ''));
                $email = trim((string) $request->request->get('email', $user->getEmail() ?? ''));
                $numTel = trim((string) $request->request->get('num_tel', $user->getNumTel() ?? ''));
                $totpAction = $request->request->get('totp_action');
                $totpWasEnabled = $user->isTotpEnabled() === true;
                $openTotpModal = false;

                if ($nom !== '') {
                    $user->setNom($nom);
                }

                if ($prenom !== '') {
                    $user->setPrenom($prenom);
                }

                if ($email !== '') {
                    $user->setEmail($email);
                }

                if ($numTel !== '' && ctype_digit($numTel) && strlen($numTel) === 8) {
                    $user->setNumTel($numTel);
                    $user->setNeedsPhoneUpdate(false);
                } elseif ($user->needsPhoneUpdate()) {
                    $this->addFlash('error', 'Le numero de telephone doit contenir exactement 8 chiffres.');

                    return $this->redirectToRoute('app_profile', ['phone_required' => 1]);
                }

                $uploadedFile = $request->files->get('profile_picture');
                if ($uploadedFile && $uploadedFile->isValid()) {
                    $mimeType = (string) $uploadedFile->getMimeType();
                    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                    if (in_array($mimeType, $allowed, true)) {
                        $contents = file_get_contents($uploadedFile->getPathname());
                        if ($contents !== false) {
                            $dataUri = sprintf('data:%s;base64,%s', $mimeType, base64_encode($contents));
                            $user->setProfilePicture($dataUri);
                        }
                    }
                }

                if ($totpAction === 'enable') {
                    if ($user->getTotpSecret() === null || $user->getTotpSecret() === '') {
                        $user->setTotpSecret($this->generateTotpSecret());
                    }

                    $user->setTotpEnabled(true);

                    if (!$totpWasEnabled) {
                        $openTotpModal = true;
                        $this->addFlash('success', 'La double authentification a été activée.');
                    }
                } elseif ($totpAction === 'disable' && $totpWasEnabled) {
                    $user->setTotpEnabled(false);
                    $user->setTotpSecret(null);
                    $this->addFlash('success', 'La double authentification a été désactivée.');
                }

                try {
                    $entityManager->flush();
                } catch (DbalException $exception) {
                    $this->addFlash('error', 'Database connection lost while saving. Please retry in a few seconds.');

                    return $this->redirectToRoute('app_profile', ['phone_required' => $user->needsPhoneUpdate() ? 1 : 0]);
                }
            }

            if ($openTotpModal) {
                return $this->redirectToRoute('app_profile', ['show_2fa' => 1]);
            }

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig');
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof Utilisateur) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        if (!$this->isCsrfTokenValid('delete_profile', (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->container->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        return new JsonResponse(['success' => true, 'redirect' => $this->generateUrl('app_login')]);
    }

    private function generateTotpSecret(int $length = 32): string
    {
        $secret = '';
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $alphabetLength = strlen($alphabet);

        for ($index = 0; $index < $length; $index++) {
            $secret .= $alphabet[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }
}
