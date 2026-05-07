<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260503222051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wallet CHANGE type_wallet type_wallet ENUM(\'fiat\', \'crypto\', \'trading\'), CHANGE statut statut ENUM(\'bloque\', \'actif\')');
        $this->addSql('ALTER TABLE wallet_currency CHANGE solde solde DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE wallet CHANGE type_wallet type_wallet ENUM(\'fiat\', \'crypto\', \'trading\') DEFAULT NULL, CHANGE statut statut ENUM(\'bloque\', \'actif\') DEFAULT NULL');
        $this->addSql('ALTER TABLE wallet_currency CHANGE solde solde DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }
}
