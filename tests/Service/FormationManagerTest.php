<?php

namespace App\Tests\Service;

use App\Entity\Formation;
use App\Service\FormationManager;
use PHPUnit\Framework\TestCase;

class FormationManagerTest extends TestCase
{
    private FormationManager $manager;

    protected function setUp(): void
    {
        // Si FormationManager nécessite des arguments (ex: EntityManager), 
        // il faudra utiliser $this->createMock().
        $this->manager = new FormationManager();
    }

    /**
     * Scénario idéal : Tout est correct
     */
    public function testFullValidationSuccess(): void
    {
        $formation = new Formation();
        $formation->setPrix(150.0);
        $formation->setDateDebut(new \DateTime('tomorrow'));
        $formation->setDateFin(new \DateTime('+2 days'));
        $formation->setDescription("Découvrez le développement Symfony 6.4 de A à Z.");

        $result = $this->manager->validate($formation);

        $this->assertTrue($result, "La validation devrait réussir pour une formation valide.");
    }

    /**
     * Erreur : Dates inversées
     */
    public function testInvertedDatesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Optionnel : vérifier le message exact attendu
        // $this->expectExceptionMessage("La date de fin doit être postérieure à la date de début");

        $formation = new Formation();
        $formation->setDateDebut(new \DateTime('+5 days'));
        $formation->setDateFin(new \DateTime('+2 days')); // Erreur !
        $formation->setPrix(50.0);
        $formation->setDescription("Une description suffisamment longue pour le test.");

        $this->manager->validate($formation);
    }

    /**
     * Erreur : Description trop courte
     */
    public function testShortDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La description est trop courte");

        $formation = new Formation();
        $formation->setPrix(50.0);
        $formation->setDateDebut(new \DateTime('now'));
        $formation->setDateFin(new \DateTime('+1 day'));
        $formation->setDescription("Trop court"); // Moins de 20 caractères ?

        $this->manager->validate($formation);
    }

    /**
     * Erreur : Prix négatif (Test supplémentaire suggéré)
     */
    public function testNegativePriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $formation = new Formation();
        $formation->setPrix(-10.0);
        $formation->setDateDebut(new \DateTime('tomorrow'));
        $formation->setDateFin(new \DateTime('+2 days'));
        $formation->setDescription("Description valide de plus de vingt caractères.");

        $this->manager->validate($formation);
    }
}