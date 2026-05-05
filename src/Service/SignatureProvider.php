<?php

namespace App\Service;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Entity\Negociation;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Classe interne "Adapter" pour rendre l'entité Utilisateur compatible 
 * avec LexikJWT sans modifier le module de ton collègue.
 */
class UserSignatureWrapper implements UserInterface
{
    private Utilisateur $user;
    public function __construct(Utilisateur $user) { $this->user = $user; }
    public function getRoles(): array { return ['ROLE_USER']; }
    public function eraseCredentials(): void {}
    public function getUserIdentifier(): string { return (string) $this->user->getEmail(); }
    public function getPassword(): ?string { return $this->user->getMotDePasse(); }
}

class SignatureProvider
{
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }

    public function signContract(Negociation $negociation, string $content): string
    {
        $userEntity = $negociation->getUtilisateur();

        if (!$userEntity) {
            throw new \RuntimeException("Aucun utilisateur associé à cette négociation.");
        }

        // On enveloppe l'entité Utilisateur dans notre Wrapper compatible UserInterface
        $wrappedUser = new UserSignatureWrapper($userEntity);

        // Création du certificat numérique (Payload)
        $payload = [
            'contract_ref' => 'SC-' . strtoupper(uniqid()),
            'amount'       => $negociation->getMontant(),
            'taux'         => $negociation->getTauxPropose() . '%',
            'hash_content' => hash('sha256', $content), 
            'signed_at'    => date('Y-m-d H:i:s'),
            'app_name'     => 'Champions Fintech'
        ];

        // LexikJWT accepte maintenant $wrappedUser car il implémente UserInterface
        return $this->jwtManager->createFromPayload($wrappedUser, $payload);
    }
}