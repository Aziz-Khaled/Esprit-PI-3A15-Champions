<?php

namespace App\Service;

use App\Entity\Projet;
use App\Entity\Credit;
use InvalidArgumentException;

class AppManager
{
    /**
     * Valide qu'un projet respecte les règles simples et métier (CRUD + Métier).
     *
     * @param Projet $projet
     * @throws InvalidArgumentException
     */
    public function validateProject(Projet $projet): void
    {
        $titre = $projet->getTitle();
        
        // Règle Simple : Le titre est obligatoire et doit faire au moins 5 caractères.
        if (empty($titre) || mb_strlen(trim($titre)) < 5) {
            throw new InvalidArgumentException("Le titre du projet est obligatoire et doit faire au moins 5 caractères.");
        }

        $dateDebut = $projet->getStartDate();
        $dateFin = $projet->getEndDate();

        // Métier Avancé 1 : La dateFin doit être strictement postérieure à la dateDebut.
        if ($dateDebut && $dateFin) {
            if ($dateFin <= $dateDebut) {
                throw new InvalidArgumentException("La date de fin doit être strictement postérieure à la date de début.");
            }
        } else {
            throw new InvalidArgumentException("Les dates de début et de fin sont obligatoires.");
        }
    }

    /**
     * Valide qu'un crédit respecte les règles simples et métier (CRUD + Liaison Métier).
     *
     * @param Credit $credit
     * @throws InvalidArgumentException
     */
    public function validateCredit(Credit $credit): void
    {
        $montant = (float) $credit->getMontant();

        // Règle Simple : Le montant doit être un nombre positif supérieur à zéro.
        if ($montant <= 0) {
            throw new InvalidArgumentException("Le montant du crédit doit être strictement positif.");
        }

        $projet = $credit->getProjet();

        if (!$projet) {
            throw new InvalidArgumentException("Le crédit doit être lié à un projet.");
        }

        $budgetProjet = (float) $projet->getTargetAmount();

        // Métier Avancé 2 (Liaison) : Le montant du Credit ne peut pas dépasser 80% du budget du Projet associé.
        $limiteBudget = $budgetProjet * 0.80;

        if ($montant > $limiteBudget) {
            throw new InvalidArgumentException("Le montant du crédit ne peut pas dépasser 80% du budget du projet associé.");
        }
    }
}