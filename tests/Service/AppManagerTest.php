<?php

namespace App\Tests\Service;

use App\Entity\Projet;
use App\Entity\Credit;
use App\Service\AppManager;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class AppManagerTest extends TestCase
{
    private AppManager $appManager;

    protected function setUp(): void
    {
        $this->appManager = new AppManager();
    }

    /**
     * Teste que la méthode validateProject() ne lance aucune exception
     * si les données sont valides.
     */
    public function testProjectDatesValidity(): void
    {
        $projet = new Projet();
        $projet->setTitle("Projet Innovant");
        $projet->setStartDate(new \DateTime('2024-01-01'));
        $projet->setEndDate(new \DateTime('2024-12-31'));

        // Si le code ne lance aucune exception, le test est considéré succès
        $this->appManager->validateProject($projet);
       
        // Assert true just to silence "risky test" warning in PHPUnit
        $this->assertTrue(true);
    }

    /**
     * Teste l'échec de validateProject() si la date de fin
     * n'est pas strictement postérieure à la date de début.
     */
    public function testProjectDatesInvalidityThrowsException(): void
    {
        $projet = new Projet();
        $projet->setTitle("Projet Innovant");
        // Dates inversées (incohérence logique)
        $projet->setStartDate(new \DateTime('2024-12-31'));
        $projet->setEndDate(new \DateTime('2024-01-01'));

        $this->expectException(InvalidArgumentException::class);
        $this->appManager->validateProject($projet);
    }

    /**
     * Teste l'échec de validateProject() si le titre est trop court (< 5 caractères).
     */
    public function testProjectShortTitleThrowsException(): void
    {
        $projet = new Projet();
        $projet->setTitle("A"); // Trop court
        $projet->setStartDate(new \DateTime('2024-01-01'));
        $projet->setEndDate(new \DateTime('2024-12-31'));

        $this->expectException(InvalidArgumentException::class);
        $this->appManager->validateProject($projet);
    }

    /**
     * Teste que la méthode validateCredit() ne lance aucune exception
     * si le montant est <= 80% du budget du projet.
     */
    public function testCreditRatioLogic(): void
    {
        $projet = new Projet();
        $projet->setTargetAmount(1000); // Budget : 1000

        $credit = new Credit();
        $credit->setProjet($projet);
        $credit->setMontant("800"); // 80% exact, donc valide

        $this->appManager->validateCredit($credit);
       
        $this->assertTrue(true);
    }

    /**
     * Teste l'échec de validateCredit() si le montant est > 80% du projet.
     */
    public function testCreditRatioLogicExceededThrowsException(): void
    {
        $projet = new Projet();
        $projet->setTargetAmount(1000); // Budget : 1000

        $credit = new Credit();
        $credit->setProjet($projet);
        // 850 dépasse la limite autorisée de 800 (80% de 1000)
        $credit->setMontant("850");

        $this->expectException(InvalidArgumentException::class);
        $this->appManager->validateCredit($credit);
    }

    /**
     * Teste l'échec de validateCredit() si le montant est négatif ou nul.
     */
    public function testCreditNegativeAmountThrowsException(): void
    {
        $projet = new Projet();
        $projet->setTargetAmount(1000);

        $credit = new Credit();
        $credit->setProjet($projet);
        // Montant négatif invalide
        $credit->setMontant("-50");

        $this->expectException(InvalidArgumentException::class);
        $this->appManager->validateCredit($credit);
    }
}