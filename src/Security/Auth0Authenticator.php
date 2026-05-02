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
    // Flag to track whether this is a brand-new user needing KYC



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
        new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $request) {
            $auth0User = $client->fetchUserFromToken($accessToken);
            $userData  = $auth0User->toArray();
            $email     = $userData['email'] ?? null;

            if (!$email) {
                throw new \RuntimeException('No email returned from Auth0/Google.');
            }

            $user = $this->em->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new Utilisateur();
                $user->setEmail($email);
                $user->setPrenom($userData['given_name']  ?? '');
                $user->setNom($userData['family_name']    ?? '');
                $user->setUserImage($userData['picture']  ?? 'default_avatar.png');
                $user->setStatut('PENDING');
                $user->setRole('CLIENT');
                $user->setMotDePasse('');
                $user->setTelephone('');
                $user->setPieceIdentite('pending_kyc');
                $user->setDateCreation(new \DateTime());
                $user->setDateDerniereConnexion(new \DateTime());

                $this->em->persist($user);
                $this->em->flush();

                // ← This flag is what tells us KYC is still needed
                $request->getSession()->set('google_kyc_pending', true);
                $request->getSession()->set('google_register_data', [
                    'email'     => $email,
                    'prenom'    => $userData['given_name']  ?? '',
                    'nom'       => $userData['family_name'] ?? '',
                    'userImage' => $userData['picture']     ?? 'default_avatar.png',
                    'telephone' => '',
                ]);

            } else {
                $user->setDateDerniereConnexion(new \DateTime());
                $this->em->flush();
            }

            return $user;
        })
    );
}

public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
{
    $user = $token->getUser();

    if (!$user instanceof Utilisateur) {
        return new RedirectResponse($this->router->generate('app_login'));
    }

    // ── KYC incomplete: session flag OR DB marker ──
    $kycPending = $request->getSession()->get('google_kyc_pending') === true
        || $user->getPieceIdentite() === 'pending_kyc';

    if ($kycPending) {
        // Refresh session flag in case it was lost
        $request->getSession()->set('google_kyc_pending', true);
        $request->getSession()->set('google_register_data', [
            'email'     => $user->getEmail(),
            'prenom'    => $user->getPrenom(),
            'nom'       => $user->getNom(),
            'userImage' => $user->getUserImage(),
            'telephone' => $user->getTelephone(),
        ]);
        return new RedirectResponse($this->router->generate('app_google_kyc'));
    }

    return match ($user->getStatut()) {
        'ACTIVE'  => new RedirectResponse($this->router->generate('app_projet')),
        'PENDING' => new RedirectResponse($this->router->generate('app_login', ['step' => 'pending'])),
        'BANNED'  => new RedirectResponse($this->router->generate('app_login', ['step' => 'banned'])),
        default   => new RedirectResponse($this->router->generate('app_login')),
    };
}

public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
{
    file_put_contents(
        sys_get_temp_dir() . '/auth0_error.txt',
        $exception->getMessage() . "\n" .
        ($exception->getPrevious()?->getMessage() ?? 'no previous')
    );

    $request->getSession()->remove('google_register_data');
    $request->getSession()->remove('google_kyc_pending');
    return new RedirectResponse($this->router->generate('app_login'));
}
}