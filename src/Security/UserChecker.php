<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }
        
        // Admins bypass all status checks
        if ($user->getRole() === 'ADMIN') {
            return;
        }

        if ($user->getStatut() === 'PENDING') {
            throw new CustomUserMessageAuthenticationException('ACCOUNT_PENDING');
        }

        if ($user->getStatut() === 'BANNED') {
            throw new CustomUserMessageAuthenticationException('ACCOUNT_BANNED');
        }
        

        
    }

    public function checkPostAuth(UserInterface $user): void {}
}