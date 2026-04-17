<?php

namespace App\Controller\frontOffice;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class Auth0Controller extends AbstractController
{
    #[Route('/connect/google', name: 'connect_auth0_start')]
    public function connect(ClientRegistry $registry): RedirectResponse
    {
        return $registry->getClient('auth0_google')
        ->redirect(
        ['openid', 'profile', 'email'],
        [
            'prompt' => 'select_account'
        ]
    );


         //   dd($url->getTargetUrl());
    }

    #[Route('/connect/google/check', name: 'connect_auth0_check')]
    public function connectCheck(): void
    {
        
    }
}
