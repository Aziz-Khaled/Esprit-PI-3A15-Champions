<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502161958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(255) NOT NULL, entity VARCHAR(255) NOT NULL, details LONGTEXT DEFAULT NULL, performed_by VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE asset (id INT AUTO_INCREMENT NOT NULL, symbol VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, market VARCHAR(50) NOT NULL, current_price NUMERIC(18, 8) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_2AF5A5CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blockchain (id_block INT AUTO_INCREMENT NOT NULL, block_index INT NOT NULL, previous_hash VARCHAR(255) NOT NULL, current_hash VARCHAR(255) NOT NULL, montant DOUBLE PRECISION NOT NULL, type VARCHAR(255) NOT NULL, id_transaction_id INT DEFAULT NULL, wallet_source_id INT DEFAULT NULL, wallet_destination_id INT DEFAULT NULL, card_id INT DEFAULT NULL, INDEX IDX_2A493AAA12A67609 (id_transaction_id), INDEX IDX_2A493AAAC2A00CF0 (wallet_source_id), INDEX IDX_2A493AAA3CE0B278 (wallet_destination_id), INDEX IDX_2A493AAA4ACC9A20 (card_id), PRIMARY KEY (id_block)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE certificats (id_certificat INT AUTO_INCREMENT NOT NULL, date_emission DATE NOT NULL, mention VARCHAR(255) DEFAULT NULL, url_fichier VARCHAR(255) DEFAULT NULL, participation_id INT NOT NULL, INDEX IDX_D5486F1B6ACE3B73 (participation_id), PRIMARY KEY (id_certificat)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversion (id_conversion INT AUTO_INCREMENT NOT NULL, amount_from NUMERIC(18, 8) NOT NULL, amount_to NUMERIC(18, 8) NOT NULL, exchange_rate NUMERIC(18, 8) NOT NULL, created_at DATETIME NOT NULL, currency_from_id INT DEFAULT NULL, currency_to_id INT DEFAULT NULL, INDEX IDX_BD912744A56723E4 (currency_from_id), INDEX IDX_BD91274467D74803 (currency_to_id), PRIMARY KEY (id_conversion)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE credit (id_credit INT AUTO_INCREMENT NOT NULL, montant NUMERIC(18, 2) NOT NULL, devise VARCHAR(8) DEFAULT \'EUR\' NOT NULL, taux NUMERIC(9, 6) NOT NULL, duree INT UNSIGNED NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(255) DEFAULT \'OPEN\' NOT NULL, contrat_id VARCHAR(36) DEFAULT NULL, date_demande DATETIME NOT NULL, date_contrat DATETIME DEFAULT NULL, project_id INT NOT NULL, borrower_id INT NOT NULL, investisseur_id INT DEFAULT NULL, INDEX IDX_1CC16EFE166D1F9C (project_id), INDEX IDX_1CC16EFE11CE312B (borrower_id), INDEX IDX_1CC16EFEA8F9CCCA (investisseur_id), PRIMARY KEY (id_credit)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE credit_card (id_card INT AUTO_INCREMENT NOT NULL, card_holder_name VARCHAR(100) NOT NULL, last_4_digits VARCHAR(4) NOT NULL, expiry_month INT NOT NULL, expiry_year INT NOT NULL, stripe_customer_id VARCHAR(100) DEFAULT NULL, stripe_payment_method_id VARCHAR(100) DEFAULT NULL, date_ajout DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, user_id INT NOT NULL, INDEX IDX_11D627EEA76ED395 (user_id), PRIMARY KEY (id_card)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE currency (id_currency INT AUTO_INCREMENT NOT NULL, code VARCHAR(10) NOT NULL, nom VARCHAR(255) NOT NULL, type_currency VARCHAR(50) NOT NULL, is_trading TINYINT NOT NULL, PRIMARY KEY (id_currency)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formations (id_formation INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, domaine VARCHAR(255) DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, prix NUMERIC(10, 2) NOT NULL, capacite_max INT NOT NULL, statut VARCHAR(255) DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_40902137A76ED395 (user_id), PRIMARY KEY (id_formation)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE negociation (id_negociation INT AUTO_INCREMENT NOT NULL, montant NUMERIC(18, 2) NOT NULL, taux_propose NUMERIC(9, 6) NOT NULL, status VARCHAR(255) NOT NULL, credit_id INT NOT NULL, investor_id INT DEFAULT NULL, INDEX IDX_B5E137D8CE062FF9 (credit_id), INDEX IDX_B5E137D89AE528DA (investor_id), PRIMARY KEY (id_negociation)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id_notification INT AUTO_INCREMENT NOT NULL, id_transaction INT DEFAULT NULL, type_notification VARCHAR(255) NOT NULL, message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, is_read TINYINT DEFAULT NULL, PRIMARY KEY (id_notification)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, sub_total NUMERIC(10, 2) NOT NULL, discount_applied NUMERIC(10, 2) NOT NULL, product_id INT NOT NULL, order_id INT NOT NULL, INDEX IDX_52EA1F094584665A (product_id), INDEX IDX_52EA1F098D9F6D38 (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, order_date DATETIME NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(255) NOT NULL, shipping_address LONGTEXT NOT NULL, payment_method VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_E52FFDEEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participations (id_participation INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, presence TINYINT DEFAULT NULL, note DOUBLE PRECISION DEFAULT NULL, formation_id INT DEFAULT NULL, utilisateur_id INT DEFAULT NULL, INDEX IDX_FDC6C6E85200282E (formation_id), INDEX IDX_FDC6C6E8FB88E14F (utilisateur_id), PRIMARY KEY (id_participation)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, price NUMERIC(10, 2) NOT NULL, discount_price NUMERIC(10, 2) DEFAULT NULL, brand VARCHAR(255) DEFAULT NULL, avg_rating DOUBLE PRECISION DEFAULT NULL, image_url LONGTEXT DEFAULT NULL, stock INT DEFAULT NULL, category VARCHAR(255) DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_D34A04ADA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE projet (id_projet INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, target_amount NUMERIC(18, 2) DEFAULT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, image_url LONGTEXT DEFAULT NULL, secteur VARCHAR(50) DEFAULT NULL, owner_id INT NOT NULL, INDEX IDX_50159CA97E3C61F9 (owner_id), PRIMARY KEY (id_projet)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE trade (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, trade_type VARCHAR(255) NOT NULL, order_mode VARCHAR(255) NOT NULL, price NUMERIC(18, 2) DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, executed_at DATETIME DEFAULT NULL, asset_id INT NOT NULL, INDEX IDX_7E1A43665DA1941 (asset_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction (id_transaction INT AUTO_INCREMENT NOT NULL, montant DOUBLE PRECISION NOT NULL, type VARCHAR(255) NOT NULL, statut VARCHAR(255) DEFAULT NULL, date_transaction DATETIME DEFAULT NULL, wallet_source_id INT DEFAULT NULL, wallet_destination_id INT DEFAULT NULL, card_id INT DEFAULT NULL, currency_id INT DEFAULT NULL, conversion_id INT DEFAULT NULL, INDEX IDX_723705D1C2A00CF0 (wallet_source_id), INDEX IDX_723705D13CE0B278 (wallet_destination_id), INDEX IDX_723705D14ACC9A20 (card_id), INDEX IDX_723705D138248176 (currency_id), INDEX IDX_723705D14C1FF126 (conversion_id), PRIMARY KEY (id_transaction)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id_user INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, date_de_creation DATETIME NOT NULL, piece_identite VARCHAR(255) NOT NULL, user_image VARCHAR(255) NOT NULL, date_derniere_connexion DATETIME NOT NULL, email VARCHAR(255) NOT NULL, statut VARCHAR(255) NOT NULL, PRIMARY KEY (id_user)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wallet (id_wallet INT AUTO_INCREMENT NOT NULL, rib VARCHAR(8) NOT NULL, type_wallet ENUM(\'fiat\', \'crypto\', \'trading\'), solde NUMERIC(10, 2) DEFAULT NULL, statut ENUM(\'bloque\', \'actif\'), date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_derniere_modification DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, user_id INT NOT NULL, INDEX IDX_7C68921FA76ED395 (user_id), PRIMARY KEY (id_wallet)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wallet_currency (id_wallet_currency INT AUTO_INCREMENT NOT NULL, solde DOUBLE PRECISION DEFAULT 0 NOT NULL, nom_currency VARCHAR(255) NOT NULL, id_wallet_id INT NOT NULL, id_currency_id INT NOT NULL, INDEX IDX_9DF33C4BF1109CD4 (id_wallet_id), INDEX IDX_9DF33C4B20364C81 (id_currency_id), PRIMARY KEY (id_wallet_currency)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE asset ADD CONSTRAINT FK_2AF5A5CA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE blockchain ADD CONSTRAINT FK_2A493AAA12A67609 FOREIGN KEY (id_transaction_id) REFERENCES transaction (id_transaction)');
        $this->addSql('ALTER TABLE blockchain ADD CONSTRAINT FK_2A493AAAC2A00CF0 FOREIGN KEY (wallet_source_id) REFERENCES wallet (id_wallet)');
        $this->addSql('ALTER TABLE blockchain ADD CONSTRAINT FK_2A493AAA3CE0B278 FOREIGN KEY (wallet_destination_id) REFERENCES wallet (id_wallet)');
        $this->addSql('ALTER TABLE blockchain ADD CONSTRAINT FK_2A493AAA4ACC9A20 FOREIGN KEY (card_id) REFERENCES credit_card (id_card)');
        $this->addSql('ALTER TABLE certificats ADD CONSTRAINT FK_D5486F1B6ACE3B73 FOREIGN KEY (participation_id) REFERENCES participations (id_participation)');
        $this->addSql('ALTER TABLE conversion ADD CONSTRAINT FK_BD912744A56723E4 FOREIGN KEY (currency_from_id) REFERENCES currency (id_currency)');
        $this->addSql('ALTER TABLE conversion ADD CONSTRAINT FK_BD91274467D74803 FOREIGN KEY (currency_to_id) REFERENCES currency (id_currency)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFE166D1F9C FOREIGN KEY (project_id) REFERENCES projet (id_projet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFE11CE312B FOREIGN KEY (borrower_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFEA8F9CCCA FOREIGN KEY (investisseur_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE credit_card ADD CONSTRAINT FK_11D627EEA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE formations ADD CONSTRAINT FK_40902137A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE negociation ADD CONSTRAINT FK_B5E137D8CE062FF9 FOREIGN KEY (credit_id) REFERENCES credit (id_credit) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE negociation ADD CONSTRAINT FK_B5E137D89AE528DA FOREIGN KEY (investor_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE participations ADD CONSTRAINT FK_FDC6C6E85200282E FOREIGN KEY (formation_id) REFERENCES formations (id_formation)');
        $this->addSql('ALTER TABLE participations ADD CONSTRAINT FK_FDC6C6E8FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE projet ADD CONSTRAINT FK_50159CA97E3C61F9 FOREIGN KEY (owner_id) REFERENCES utilisateur (id_user) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trade ADD CONSTRAINT FK_7E1A43665DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1C2A00CF0 FOREIGN KEY (wallet_source_id) REFERENCES wallet (id_wallet)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D13CE0B278 FOREIGN KEY (wallet_destination_id) REFERENCES wallet (id_wallet)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D14ACC9A20 FOREIGN KEY (card_id) REFERENCES credit_card (id_card)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D138248176 FOREIGN KEY (currency_id) REFERENCES currency (id_currency)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D14C1FF126 FOREIGN KEY (conversion_id) REFERENCES conversion (id_conversion)');
        $this->addSql('ALTER TABLE wallet ADD CONSTRAINT FK_7C68921FA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id_user)');
        $this->addSql('ALTER TABLE wallet_currency ADD CONSTRAINT FK_9DF33C4BF1109CD4 FOREIGN KEY (id_wallet_id) REFERENCES wallet (id_wallet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wallet_currency ADD CONSTRAINT FK_9DF33C4B20364C81 FOREIGN KEY (id_currency_id) REFERENCES currency (id_currency)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset DROP FOREIGN KEY FK_2AF5A5CA76ED395');
        $this->addSql('ALTER TABLE blockchain DROP FOREIGN KEY FK_2A493AAA12A67609');
        $this->addSql('ALTER TABLE blockchain DROP FOREIGN KEY FK_2A493AAAC2A00CF0');
        $this->addSql('ALTER TABLE blockchain DROP FOREIGN KEY FK_2A493AAA3CE0B278');
        $this->addSql('ALTER TABLE blockchain DROP FOREIGN KEY FK_2A493AAA4ACC9A20');
        $this->addSql('ALTER TABLE certificats DROP FOREIGN KEY FK_D5486F1B6ACE3B73');
        $this->addSql('ALTER TABLE conversion DROP FOREIGN KEY FK_BD912744A56723E4');
        $this->addSql('ALTER TABLE conversion DROP FOREIGN KEY FK_BD91274467D74803');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFE166D1F9C');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFE11CE312B');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFEA8F9CCCA');
        $this->addSql('ALTER TABLE credit_card DROP FOREIGN KEY FK_11D627EEA76ED395');
        $this->addSql('ALTER TABLE formations DROP FOREIGN KEY FK_40902137A76ED395');
        $this->addSql('ALTER TABLE negociation DROP FOREIGN KEY FK_B5E137D8CE062FF9');
        $this->addSql('ALTER TABLE negociation DROP FOREIGN KEY FK_B5E137D89AE528DA');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEA76ED395');
        $this->addSql('ALTER TABLE participations DROP FOREIGN KEY FK_FDC6C6E85200282E');
        $this->addSql('ALTER TABLE participations DROP FOREIGN KEY FK_FDC6C6E8FB88E14F');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADA76ED395');
        $this->addSql('ALTER TABLE projet DROP FOREIGN KEY FK_50159CA97E3C61F9');
        $this->addSql('ALTER TABLE trade DROP FOREIGN KEY FK_7E1A43665DA1941');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1C2A00CF0');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D13CE0B278');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D14ACC9A20');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D138248176');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D14C1FF126');
        $this->addSql('ALTER TABLE wallet DROP FOREIGN KEY FK_7C68921FA76ED395');
        $this->addSql('ALTER TABLE wallet_currency DROP FOREIGN KEY FK_9DF33C4BF1109CD4');
        $this->addSql('ALTER TABLE wallet_currency DROP FOREIGN KEY FK_9DF33C4B20364C81');
        $this->addSql('DROP TABLE admin_log');
        $this->addSql('DROP TABLE asset');
        $this->addSql('DROP TABLE blockchain');
        $this->addSql('DROP TABLE certificats');
        $this->addSql('DROP TABLE conversion');
        $this->addSql('DROP TABLE credit');
        $this->addSql('DROP TABLE credit_card');
        $this->addSql('DROP TABLE currency');
        $this->addSql('DROP TABLE formations');
        $this->addSql('DROP TABLE negociation');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE participations');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE projet');
        $this->addSql('DROP TABLE trade');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE utilisateur');
        $this->addSql('DROP TABLE wallet');
        $this->addSql('DROP TABLE wallet_currency');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
