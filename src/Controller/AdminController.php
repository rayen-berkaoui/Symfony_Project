<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(
        UtilisateurRepository $utilisateurRepository,
        RoleRepository $roleRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $totalUsers = $utilisateurRepository->count([]);
        $activeUsers = $utilisateurRepository->count(['statut' => Utilisateur::STATUT_ACTIF]);
        $blockedUsers = $utilisateurRepository->count(['statut' => Utilisateur::STATUT_BLOQUE]);
        $pendingUsers = $utilisateurRepository->count(['statut' => Utilisateur::STATUT_EN_ATTENTE]);
        $adminUsers = (int) $utilisateurRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('u.role', 'r')
            ->andWhere('UPPER(r.nom) IN (:roles)')
            ->setParameter('roles', ['ADMIN', 'ROLE_ADMIN'])
            ->getQuery()
            ->getSingleScalarResult();

        $totpEnabledUsers = (int) $utilisateurRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.totpEnabled = true')
            ->getQuery()
            ->getSingleScalarResult();

        $profilePictureUsers = (int) $utilisateurRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.profilePicture IS NOT NULL')
            ->andWhere('u.profilePicture <> :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();

        $faceReadyUsers = (int) $utilisateurRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.faceSamplesCount >= :minimumSamples')
            ->setParameter('minimumSamples', 3)
            ->getQuery()
            ->getSingleScalarResult();

        $totalLoyaltyPoints = (int) $utilisateurRepository->createQueryBuilder('u')
            ->select('COALESCE(SUM(u.loyaltyPoints), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        $averageFaceConfidence = (float) $utilisateurRepository->createQueryBuilder('u')
            ->select('COALESCE(AVG(u.faceConfidence), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        $recentUsers = $utilisateurRepository->findBy([], ['dateCreation' => 'DESC'], 10);

        $roleStats = $entityManager->createQuery(
            'SELECT r.nom AS roleName, r.description AS roleDescription, COUNT(u.id) AS userCount
             FROM App\Entity\Role r
             LEFT JOIN r.utilisateurs u
               GROUP BY r.id, r.nom, r.description
             ORDER BY userCount DESC, roleName ASC'
        )->getArrayResult();

        $availableRoles = $roleRepository->findBy([], ['nom' => 'ASC']);

        $registrationTrend = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $dayStart = (new \DateTimeImmutable(sprintf('-%d days', $daysAgo)))->setTime(0, 0);
            $dayEnd = $dayStart->modify('+1 day');

            $dailyRegistrations = (int) $utilisateurRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->andWhere('u.dateCreation >= :startDate')
                ->andWhere('u.dateCreation < :endDate')
                ->setParameter('startDate', $dayStart)
                ->setParameter('endDate', $dayEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $registrationTrend[] = [
                'label' => $dayStart->format('D'),
                'date' => $dayStart->format('d M'),
                'count' => $dailyRegistrations,
            ];
        }

        $registrationPeak = 0;
        foreach ($registrationTrend as $point) {
            $registrationPeak = max($registrationPeak, (int) $point['count']);
        }

        $controlCenter = [
            [
                'title' => 'Manage users',
                'description' => 'Open the full user directory, search accounts, and update status.',
                'route' => 'app_admin_users',
            ],
            [
                'title' => 'Export report',
                'description' => 'Download the current user roster as a printable report.',
                'route' => 'app_admin_users_export',
            ],
            [
                'title' => 'Security review',
                'description' => 'Check blocked accounts, TOTP adoption, and profile readiness.',
                'route' => 'app_admin_users',
            ],
        ];

        $latestUser = $recentUsers[0] ?? null;

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'blockedUsers' => $blockedUsers,
            'pendingUsers' => $pendingUsers,
            'adminUsers' => $adminUsers,
            'totpEnabledUsers' => $totpEnabledUsers,
            'profilePictureUsers' => $profilePictureUsers,
            'faceReadyUsers' => $faceReadyUsers,
            'totalLoyaltyPoints' => $totalLoyaltyPoints,
            'averageFaceConfidence' => $averageFaceConfidence,
            'recentUsers' => $recentUsers,
            'roleStats' => $roleStats,
            'availableRoles' => $availableRoles,
            'registrationTrend' => $registrationTrend,
            'registrationPeak' => $registrationPeak,
            'controlCenter' => $controlCenter,
            'latestUser' => $latestUser,
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request, UtilisateurRepository $utilisateurRepository, RoleRepository $roleRepository): Response
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
            'availableRoles' => $roleRepository->findBy([], ['nom' => 'ASC']),
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

        // For now, return as HTML (you can add a PDF library later)
        return new Response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="users-export-' . date('Y-m-d') . '.html"',
        ]);
    }

    #[Route('/users/{id}/status', name: 'app_admin_user_status', methods: ['POST'])]
    public function updateUserStatus(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager
    ): Response {
        $submittedToken = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('user_status_' . $utilisateur->getId(), $submittedToken)) {
            return $this->denyAdminAction($request, 'Invalid CSRF token', 403);
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Utilisateur && $currentUser->getId() === $utilisateur->getId()) {
            return $this->denyAdminAction($request, 'You cannot change your own account status', 400);
        }

        $newStatus = (string) ($request->request->get('status') ?? '');
        if ($newStatus === '' && $request->getContentTypeFormat() === 'json') {
            $data = json_decode((string) $request->getContent(), true);
            $newStatus = (string) ($data['status'] ?? '');
        }

        if (!in_array($newStatus, [Utilisateur::STATUT_ACTIF, Utilisateur::STATUT_BLOQUE, Utilisateur::STATUT_EN_ATTENTE], true)) {
            return $this->denyAdminAction($request, 'Invalid status', 400);
        }

        $utilisateur->setStatut($newStatus);
        $entityManager->flush();

        return $this->respondAdminAction($request, 'Status updated successfully', [
            'id' => $utilisateur->getId(),
            'status' => $newStatus,
        ]);
    }

    #[Route('/users/{id}/role', name: 'app_admin_user_role', methods: ['POST'])]
    public function updateUserRole(
        Request $request,
        Utilisateur $utilisateur,
        EntityManagerInterface $entityManager,
        RoleRepository $roleRepository
    ): Response {
        $submittedToken = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('user_role_' . $utilisateur->getId(), $submittedToken)) {
            return $this->denyAdminAction($request, 'Invalid CSRF token', 403);
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Utilisateur && $currentUser->getId() === $utilisateur->getId()) {
            return $this->denyAdminAction($request, 'You cannot change your own role', 400);
        }

        $roleId = (int) $request->request->get('role_id', 0);
        $role = $roleId > 0 ? $roleRepository->find($roleId) : null;

        if ($role === null) {
            return $this->denyAdminAction($request, 'Invalid role selected', 400);
        }

        $utilisateur->setRole($role);
        $entityManager->flush();

        return $this->respondAdminAction($request, 'Role updated successfully', [
            'id' => $utilisateur->getId(),
            'role' => $role->getNom(),
        ]);
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
    public function viewUser(Utilisateur $utilisateur, RoleRepository $roleRepository): Response
    {
        return $this->render('admin/user_detail.html.twig', [
            'user' => $utilisateur,
            'availableRoles' => $roleRepository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    private function denyAdminAction(Request $request, string $message, int $statusCode): Response
    {
        if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => $message], $statusCode);
        }

        $this->addFlash($statusCode >= 400 ? 'error' : 'success', $message);

        return new RedirectResponse($request->headers->get('referer') ?? $this->generateUrl('app_admin_dashboard'));
    }

    private function respondAdminAction(Request $request, string $message, array $payload = []): Response
    {
        if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
            return new JsonResponse(array_merge(['success' => true, 'message' => $message], $payload));
        }

        $this->addFlash('success', $message);

        return new RedirectResponse($request->headers->get('referer') ?? $this->generateUrl('app_admin_dashboard'));
    }
}
