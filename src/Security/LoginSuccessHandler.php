<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use App\Entity\Utilisateur;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private RouterInterface $router) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        /** @var Utilisateur $user */
        $user = $token->getUser();

        if ($user->getRole() === 'ADMIN') {
            return new RedirectResponse($this->router->generate('app_admin_panel'));
        }

        // Only active users reach here (pending/banned are blocked by UserChecker)
        return new RedirectResponse($this->router->generate('app_projet'));
    }
}