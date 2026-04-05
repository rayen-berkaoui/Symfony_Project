<?php

namespace App\Controller;

use App\Entity\Utilisateur;
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

        if ($request->isMethod('POST') && $user instanceof Utilisateur) {
            if ($this->isCsrfTokenValid('profile_update', (string) $request->request->get('_token'))) {
                $nom = trim((string) $request->request->get('nom', $user->getNom() ?? ''));
                $prenom = trim((string) $request->request->get('prenom', $user->getPrenom() ?? ''));
                $email = trim((string) $request->request->get('email', $user->getEmail() ?? ''));
                $numTel = trim((string) $request->request->get('num_tel', $user->getNumTel() ?? ''));

                if ($nom !== '') {
                    $user->setNom($nom);
                }

                if ($prenom !== '') {
                    $user->setPrenom($prenom);
                }

                if ($email !== '') {
                    $user->setEmail($email);
                }

                if ($numTel !== '' && ctype_digit($numTel)) {
                    $user->setNumTel((int) $numTel);
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

                $entityManager->flush();
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

        return new JsonResponse(['success' => true, 'redirect' => $this->generateUrl('app_home')]);
    }
}
