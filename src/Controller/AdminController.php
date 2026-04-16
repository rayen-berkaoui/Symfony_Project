<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(UtilisateurRepository $utilisateurRepository, EntityManagerInterface $entityManager): Response
    {
        $totalUsers = $utilisateurRepository->count([]);
        $activeUsers = $utilisateurRepository->count(['statut' => Utilisateur::STATUT_ACTIF]);
        $blockedUsers = $utilisateurRepository->count(['statut' => Utilisateur::STATUT_BLOQUE]);
        $pendingUsers = $utilisateurRepository->count(['statut' => Utilisateur::STATUT_EN_ATTENTE]);

        $recentUsers = $utilisateurRepository->findBy([], ['dateCreation' => 'DESC'], 10);

        $roleStats = $entityManager->createQuery(
            'SELECT r.nom, COUNT(u.id) as userCount 
             FROM App\Entity\Role r 
             LEFT JOIN r.utilisateurs u 
             GROUP BY r.id'
        )->getResult();

        // Get recent registrations (simplified - no grouping)
        $registrationStats = [];

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'blockedUsers' => $blockedUsers,
            'pendingUsers' => $pendingUsers,
            'recentUsers' => $recentUsers,
            'roleStats' => $roleStats,
            'registrationStats' => $registrationStats,
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request, UtilisateurRepository $utilisateurRepository): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $role = $request->query->get('role', '');
        $sort = $request->query->get('sort', 'date'); // 'date' or 'alpha'
        
        $qb = $utilisateurRepository->createQueryBuilder('u')
            ->leftJoin('u.role', 'r');

        if ($search !== '') {
            $qb->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($status !== '') {
            $qb->andWhere('u.statut = :status')
               ->setParameter('status', $status);
        }

        if ($role !== '') {
            $qb->andWhere('r.nom = :role')
               ->setParameter('role', $role);
        }

        // Apply sorting
        if ($sort === 'alpha') {
            $qb->orderBy('u.nom', 'ASC')
               ->addOrderBy('u.prenom', 'ASC');
        } else {
            $qb->orderBy('u.dateCreation', 'DESC');
        }

        $users = $qb->getQuery()->getResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'status' => $status,
            'role' => $role,
            'sort' => $sort,
        ]);
    }

    #[Route('/users/export', name: 'app_admin_users_export')]
    public function exportUsers(UtilisateurRepository $utilisateurRepository): Response
    {
        $users = $utilisateurRepository->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);

        // Create HTML content for PDF
        $html = $this->renderView('admin/users_export.html.twig', [
            'users' => $users,
            'exportDate' => new \DateTime(),
        ]);

        // Configure Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="users-export-' . date('Y-m-d') . '.pdf"',
        ]);
    }

    #[Route('/users/{id}/status', name: 'app_admin_user_status', methods: ['POST'])]
    public function updateUserStatus(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode((string) $request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!in_array($newStatus, [Utilisateur::STATUT_ACTIF, Utilisateur::STATUT_BLOQUE, Utilisateur::STATUT_EN_ATTENTE])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
        }

        $utilisateur->setStatut($newStatus);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Status updated successfully']);
    }

    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('delete_user_' . $utilisateur->getId(), (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Utilisateur && $currentUser->getId() === $utilisateur->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'Cannot delete your own account'], 400);
        }

        $entityManager->remove($utilisateur);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'User deleted successfully']);
    }

    #[Route('/users/{id}/view', name: 'app_admin_user_view')]
    public function viewUser(Utilisateur $utilisateur): Response
    {
        return $this->render('admin/user_detail.html.twig', [
            'user' => $utilisateur,
        ]);
    }
}
