<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\Blockchain;
use App\Repository\BlockchainRepository;
use Doctrine\ORM\EntityManagerInterface;

class BlockchainService 
{
    private $em;
    private $repository;
    private const AES_KEY = "Champions_Secure";

    public function __construct(EntityManagerInterface $em, BlockchainRepository $repository) {
        $this->em = $em;
        $this->repository = $repository;
    }

    private function encrypt($data): string {
        return base64_encode(openssl_encrypt($data, 'AES-128-ECB', self::AES_KEY));
    }

   public function addBlock(Transaction $t): void {
    // 1. Trouver le dernier bloc
    $lastBlock = $this->repository->findOneBy([], ['id_block' => 'DESC']);
    
    // 2. Définir le hash précédent et l'index
    $previousHashOnly = $lastBlock ? $lastBlock->getCurrent_hash() : "0000";
    $newIndex = $lastBlock ? $lastBlock->getBlock_index() + 1 : 1;

    // 3. Préparer les données de sauvegarde (Backup)
    $dataToBackup = sprintf("ID:%d|MT:%.2f|TYP:%s", $t->getIdTransaction(), $t->getMontant(), $t->getType());
    $encryptedBackup = $this->encrypt($dataToBackup);

    // 4. Générer le nouveau Hash SHA-256
    $newHash = hash('sha256', $t->getIdTransaction() . "|" . $previousHashOnly . "|" . $t->getMontant());

    $block = new Blockchain();
    $block->setTransaction($t);
    $block->setBlock_index($newIndex);

    // --- CORRECTION ICI ---
    // Si c'est le premier bloc, on met juste "0000". 
    // Sinon, on met "HashPrecedent;BackupEncrypté"
    if (!$lastBlock) {
        $block->setPrevious_hash("0000");
    } else {
        $block->setPrevious_hash($previousHashOnly . ";" . $encryptedBackup);
    }
    // -----------------------

    $block->setCurrent_hash($newHash);
    
    // Remplissage des autres champs
    $block->setMontant($t->getMontant());
    $block->setType($t->getType());
    $block->setWalletSource($t->getWalletSource());
    $block->setWalletDestination($t->getWalletDestination());
    $block->setCreditCard($t->getCreditCard());

    $this->em->persist($block);
}
}