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
        // Find last block using the correct ID field from your entity
        $lastBlock = $this->repository->findOneBy([], ['id_block' => 'DESC']);
        
        $previousHashOnly = $lastBlock ? $lastBlock->getCurrent_hash() : "0000";
        $newIndex = $lastBlock ? $lastBlock->getBlock_index() + 1 : 1;

        // Data backup string
        $dataToBackup = sprintf("ID:%d|MT:%.2f|TYP:%s", $t->getIdTransaction(), $t->getMontant(), $t->getType());
        $encryptedBackup = $this->encrypt($dataToBackup);

        // Generate SHA-256 Hash
        $newHash = hash('sha256', $t->getIdTransaction() . "|" . $previousHashOnly . "|" . $t->getMontant());

        $block = new Blockchain();
        $block->setTransaction($t);
        
        // Corrected to match your entity methods (with underscores)
        $block->setBlock_index($newIndex);
        $block->setPrevious_hash($previousHashOnly . ";" . $encryptedBackup);
        $block->setCurrent_hash($newHash);
        
        $block->setMontant($t->getMontant());
        $block->setType($t->getType());
        $block->setWalletSource($t->getWalletSource());
        $block->setWalletDestination($t->getWalletDestination());
        $block->setCreditCard($t->getCreditCard());

        $this->em->persist($block);
        // No flush here, the TransactionManager handles the final flush/commit
    }
}