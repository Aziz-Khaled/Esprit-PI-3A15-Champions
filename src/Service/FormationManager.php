<?php

namespace App\Service;

use App\Entity\Formation;

class FormationManager
{
    public function validate(Formation $formation): bool
    {
        // 1. Validation du Prix (Positif et >= 10 TND)
        if ($formation->getPrix() < 10) {
            throw new \InvalidArgumentException("Le prix doit être d'au moins 10 TND.");
        }

        // 2. Validation des Dates (Chronologie)
        if ($formation->getDateFin() <= $formation->getDateDebut()) {
            throw new \InvalidArgumentException("La date de fin doit être strictement après la date de début.");
        }

        // 3. Validation de la Description (Qualité du contenu)
        if (strlen($formation->getDescription()) < 20) {
            throw new \InvalidArgumentException("La description est trop courte pour être publiée.");
        }

        return true;
    }
}
