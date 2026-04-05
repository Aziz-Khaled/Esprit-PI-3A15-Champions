<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Entity\WalletCurrency;
use App\Entity\Currency;
use App\Repository\WalletRepository;
use App\Repository\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;

class TransactionManager 
{
    private $em;
    private $walletRepo;
    private $currencyRepo;
    private $blockchain;

    public function __construct(
        EntityManagerInterface $em, 
        WalletRepository $wr, 
        CurrencyRepository $cr,
        BlockchainService $bs
    ) {
        $this->em = $em;
        $this->walletRepo = $wr;
        $this->currencyRepo = $cr;
        $this->blockchain = $bs;
    }

    
    public function execute(string $ribSrc, string $ribDest, float $amount, string $type, int $currencyId): void 
    {
        $srcWallet = $this->walletRepo->findOneBy(['rib' => $ribSrc]);
        $destWallet = $this->walletRepo->findOneBy(['rib' => $ribDest]);
        $currency = $this->currencyRepo->find($currencyId);

        if (!$srcWallet) throw new \Exception("Source wallet not found!");
        if (!$destWallet) throw new \Exception("Recipient wallet not found!");
        if (!$currency) throw new \Exception("Currency not found!");

        $isConversion = (strtolower($type) === 'conversion');
        $isTrade = (strtolower($type) === 'achat' || strtolower($type) === 'vente' || strtolower($type) === 'buy' || strtolower($type) === 'sell');

        if (!$srcWallet) throw new \Exception("Source wallet not found!");
        if (!$destWallet) throw new \Exception("Recipient wallet not found!");
        if (!$currency) throw new \Exception("Currency not found!");

        $srcType = strtolower($srcWallet->getTypeWallet());
        $destType = strtolower($destWallet->getTypeWallet());
        $isConversion = (strtolower($type) === 'conversion');
        $isTrade = in_array(strtolower($type), ['achat', 'vente', 'buy', 'sell']);

        if ($srcType === 'fiat' && $destType !== 'fiat') {
            if (!$isConversion) {
                throw new \Exception("Transactions from Fiat to other wallets must be a 'conversion'.");
            }
        }

        $isCryptoTradingPair = ($srcType === 'crypto' && $destType === 'trading') || 
                               ($srcType === 'trading' && $destType === 'crypto');

        if ($isCryptoTradingPair) {
            if (!$currency->isTrading()) {
                throw new \Exception("This currency is not authorized for trading transactions.");
            }
        }

        if ($srcType !== $destType && !$isCryptoTradingPair && $srcType !== 'fiat') {
            if (!$isConversion && !$isTrade) {
                throw new \Exception("Forbidden transaction: Wallet types do not match.");
            }
        }

        if ($srcWallet->getStatut() === 'bloque') {
            throw new \Exception("The source wallet is blocked!");
        }
        if ($srcWallet->getStatut() === 'bloque') {
            throw new \Exception("The source wallet is blocked!");
        }

        $this->em->getConnection()->beginTransaction();

        try {
            $srcWC = $this->em->getRepository(WalletCurrency::class)->findOneBy([
                'wallet' => $srcWallet,
                'currency' => $currency
            ]);

            if (!$srcWC || $srcWC->getSolde() < $amount) {
                throw new \Exception("Insufficient balance in the source wallet for this currency.");
            }

            $srcWC->setSolde($srcWC->getSolde() - $amount);

            $destWC = $this->em->getRepository(WalletCurrency::class)->findOneBy([
                'wallet' => $destWallet,
                'currency' => $currency
            ]);

            if (!$destWC) {
                $destWC = new WalletCurrency();
                $destWC->setWallet($destWallet);
                $destWC->setCurrency($currency);
                $destWC->setNomCurrency($currency->getNom());
                $destWC->setSolde(0.0);
                $this->em->persist($destWC);
            }

            $destWC->setSolde($destWC->getSolde() + $amount);

            $t = new Transaction();
            $t->setWalletSource($srcWallet);
            $t->setWalletDestination($destWallet);
            $t->setMontant($amount);
            $t->setType($type);
            $t->setStatut('VALID');
            $t->setDateTransaction(new \DateTime());
            
            $t->setCurrency($currency); 
            
            $this->em->persist($t);

            if (method_exists($srcWallet, 'setDateDerniereModification')) {
                $srcWallet->setDateDerniereModification(new \DateTime());
            }
            if (method_exists($destWallet, 'setDateDerniereModification')) {
                $destWallet->setDateDerniereModification(new \DateTime());
            }

            $this->em->flush();

            $this->blockchain->addBlock($t);
            
            $this->em->flush();

            $this->em->getConnection()->commit();

        } catch (\Exception $e) {
            
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->getConnection()->rollBack();
            }
            throw new \Exception("Transaction failed: " . $e->getMessage());
        }
    }
}