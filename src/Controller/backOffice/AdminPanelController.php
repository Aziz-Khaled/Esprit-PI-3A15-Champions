<?php

namespace App\Controller\backOffice;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Form\AdminEditUserType;



#[Route('/admin')]
final class AdminPanelController extends AbstractController
{
    #[Route('/panel', name: 'app_admin_panel')]
    public function index(): Response
    {
        return $this->render('admin_panel/index.html.twig', [
            'controller_name' => 'AdminPanelController',
        ]);
    }

    #[Route('/users/pending', name: 'app_admin_pending_users')]
    public function pendingUsers(UtilisateurRepository $repo): Response
    {
        $pendingUsers = $repo->findBy(
            ['statut' => 'pending'],
            ['dateCreation' => 'DESC']
        );

        return $this->render('admin_panel/pending_users.html.twig', [
            'users' => $pendingUsers,
        ]);
    }

    #[Route('/users/approve/{id}', name: 'app_admin_approve_user', methods: ['POST'])]
    public function approveUser(
    Utilisateur $user,
    EntityManagerInterface $em,
    Request $request,
    CsrfTokenManagerInterface $csrf
    ): Response {
    if (!$csrf->isTokenValid(new CsrfToken('approve_' . $user->getIdUser(), $request->request->get('_token')))) {
        throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $user->setStatut('ACTIVE');
    $em->flush();

    $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' has been approved.');
    return $this->redirectToRoute('app_admin_pending_users');
}

    #[Route('/users/reject/{id}', name: 'app_admin_reject_user', methods: ['POST'])]
public function rejectUser(
    Utilisateur $user,
    EntityManagerInterface $em,
    Request $request,
    CsrfTokenManagerInterface $csrf
): Response {
    if (!$csrf->isTokenValid(new CsrfToken('reject_' . $user->getIdUser(), $request->request->get('_token')))) {
        throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $user->setStatut('BANNED');
    $em->flush();

    $this->addFlash('warning', $user->getPrenom() . ' ' . $user->getNom() . ' has been rejected.');
    return $this->redirectToRoute('app_admin_pending_users');
}

#[Route('/users/list', name: 'app_admin_users_list')]
public function usersList(UtilisateurRepository $repo, Request $request): Response
{
    $role   = $request->query->get('role');
    $statut = $request->query->get('statut');

    $criteria = [];
    if ($role)   $criteria['role']   = $role;
    if ($statut) $criteria['statut'] = $statut;

    $users = $criteria ? $repo->findBy($criteria) : $repo->findAll();

    return $this->render('admin_panel/users_list.html.twig', [
        'users'          => $users,
        'selectedRole'   => $role,
        'selectedStatut' => $statut,
    ]);
}

#[Route('/users/edit/{id}', name: 'app_admin_edit_user', methods: ['GET', 'POST'])]
public function editUser(
    Utilisateur $user,
    Request $request,
    EntityManagerInterface $em
): Response {
    $form = $this->createForm(AdminEditUserType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        $this->addFlash('success', $user->getPrenom() . ' ' . $user->getNom() . ' has been updated.');
        return $this->redirectToRoute('app_admin_users_list');
    }

    return $this->render('admin_panel/edit_user.html.twig', [
        'form' => $form,
        'user' => $user,
    ]);
}

#[Route('/users/delete/{id}', name: 'app_admin_delete_user', methods: ['POST'])]
public function deleteUser(
    Utilisateur $user,
    EntityManagerInterface $em,
    Request $request,
    CsrfTokenManagerInterface $csrf
): Response {
    if (!$csrf->isTokenValid(new CsrfToken('delete_' . $user->getIdUser(), $request->request->get('_token')))) {
        throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $em->remove($user);
    $em->flush();

    $this->addFlash('success', 'User has been deleted.');
    return $this->redirectToRoute('app_admin_users_list');
}


    #[Route('/admin/search', name: 'app_admin_search', methods: ['GET'])]
    public function search(Request $request, UtilisateurRepository $repo): Response
    {
        $q     = trim($request->query->get('q', ''));
        $users = strlen($q) >= 2 ? $repo->searchByKeyword($q) : [];

        // Split into active vs pending
        $active  = array_filter($users, fn($u) => $u->getStatut() !== 'pending');
        $pending = array_filter($users, fn($u) => $u->getStatut() === 'pending');

       return $this->render('admin_panel/search.html.twig', [
            'q'       => $q,
            'active'  => array_values($active),
            'pending' => array_values($pending),
            'total'   => count($users),
        ]);
    }

}