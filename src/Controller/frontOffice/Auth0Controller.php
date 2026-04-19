<?php

namespace App\Controller\frontOffice;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class Auth0Controller extends AbstractController
{
   #[Route('/connect/google', name: 'connect_auth0_start')]
public function connect(ClientRegistry $registry): RedirectResponse
{
    return $registry->getClient('auth0_google')->redirect(
        ['openid', 'profile', 'email'],
        [
            'connection' => 'google-oauth2',  // ← skips Auth0 UI, goes straight to Google picker
            'prompt'     => 'select_account',
            'approval_prompt' => null // ← forces account chooser even if already logged in
        ]
    );
}

    #[Route('/connect/google/check', name: 'connect_auth0_check')]
    public function connectCheck(): void
    {
        
    }

    #[Route('/connect/google/logout', name: 'app_logout_google')]
public function googleLogout(Request $request): RedirectResponse
{
    // Invalidate the session
    $request->getSession()->invalidate();
    
    return $this->redirectToRoute('app_login', ['step' => 'pending']);
}
}
