<?php

namespace App\Controller\frontOffice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Form\LoginType;
use App\Entity\Utilisateur;

final class AuthController extends AbstractController
{
    #[Route('/auth', name: 'app_auth')]
    public function index(): Response
    {
        // Redirect directly to login page
        return $this->redirectToRoute('app_login');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Get login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastEmail = $authenticationUtils->getLastUsername();

        // Create an empty Utilisateur object
        $user = new Utilisateur();
        $user->setEmail($lastEmail);

        // Create the login form with the Utilisateur object
        $loginForm = $this->createForm(LoginType::class);

        return $this->render('auth/index.html.twig', [
            'loginForm' => $loginForm->createView(),
            'error' => $error,
            'last_email' => $lastEmail
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}