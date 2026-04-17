<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class Auth0Authenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private RouterInterface $router,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_auth0_check';
    }

    public function authenticate(Request $request): Passport
{
    $client      = $this->clientRegistry->getClient('auth0_google');
    $accessToken = $this->fetchAccessToken($client);

    

    return new SelfValidatingPassport(
        new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
            $auth0User = $client->fetchUserFromToken($accessToken);

            
            $userData = $auth0User->toArray();
            $email    = $userData['email'] ?? null;

            if (!$email) {
                throw new \RuntimeException('No email returned from Auth0. Make sure the "email" scope is granted and email is verified.');
            }

            $user = $this->em->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new Utilisateur();
                $user->setEmail($email);
                $user->setPrenom($userData['given_name'] ?? '');
                $user->setNom($userData['family_name'] ?? '');
                $user->setUserImage($userData['picture'] ?? 'default_avatar.png');
                $user->setStatut('pending');
                $user->setRole('CLIENT');
                $user->setMotDePasse('');
                $user->setTelephone('');
                $user->setDateCreation(new \DateTime());
                $user->setDateDerniereConnexion(new \DateTime());
                $this->em->persist($user);
                $this->em->flush();
            }

            return $user;
        })
    );
}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($user instanceof Utilisateur && $user->getStatut() === 'pending') {
            return new RedirectResponse($this->router->generate('app_login', ['step' => 'pending']));
        }

        return new RedirectResponse($this->router->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}