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
    $lastBlock = $this->repository->findOneBy([], ['id_block' => 'DESC']);
    
    $previousHashOnly = $lastBlock ? $lastBlock->getCurrent_hash() : "0000";
    $newIndex = $lastBlock ? $lastBlock->getBlock_index() + 1 : 1;

    // Préparation des données communes
    $amount = number_format($t->getMontant(), 2, '.', '');
    $destRib = $t->getWalletDestination() ? $t->getWalletDestination()->getRib() : 'NO_DEST';
    $currencyName = $t->getCurrency() ? $t->getCurrency()->getNom() : 'UNKNOWN';

    $hashData = "";

    // --- LOGIQUE DE HACHAGE PERSONNALISÉE ---
    if (strtoupper($t->getType()) === 'RECHARGE') {
        // Recharge : Last4 + RIB Destination + Montant
        $last4 = ($t->getCreditCard()) ? $t->getCreditCard()->getLast4Digits() : '0000';
        $hashData = $last4 . "|" . $destRib . "|" . $amount;
    } else {
        // Transfert : RIB Destination + RIB Source + Montant + Nom Currency
        $srcRib = $t->getWalletSource() ? $t->getWalletSource()->getRib() : 'NO_SRC';
        $hashData = $destRib . "|" . $srcRib . "|" . $amount . "|" . $currencyName;
    }

    // Calcul du hash SHA-256
    $newHash = hash('sha256', $t->getIdTransaction() . "|" . $previousHashOnly . "|" . $hashData);

    // --- INSERTION DANS LA TABLE BLOCKCHAIN ---
    $block = new Blockchain();
    $block->setTransaction($t);
    $block->setBlock_index($newIndex);
    $block->setPrevious_hash($previousHashOnly . ";" . $this->encrypt($hashData));
    $block->setCurrent_hash($newHash);
    
    // On copie les valeurs de la transaction
    $block->setMontant($t->getMontant());
    $block->setType($t->getType());
    
    // ICI : Si c'est une recharge, walletSource sera NULL et creditCard sera remplie
    // car tu les as déjà set dans ton contrôleur avant d'appeler ce service.
    $block->setWalletSource($t->getWalletSource()); 
    $block->setWalletDestination($t->getWalletDestination());
    $block->setCreditCard($t->getCreditCard());

    $this->em->persist($block);
    $this->em->flush();
}
}