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

    /**
     * This method reproduces exactly the complex logic of your Java code
     */
    public function execute(string $ribSrc, string $ribDest, float $amount, string $type, int $currencyId): void 
    {
        // 1. Entity Retrieval
        $srcWallet = $this->walletRepo->findOneBy(['rib' => $ribSrc]);
        $destWallet = $this->walletRepo->findOneBy(['rib' => $ribDest]);
        $currency = $this->currencyRepo->find($currencyId);

        // --- INPUT CONTROLS ---
        if (!$srcWallet) throw new \Exception("Source wallet not found!");
        if (!$destWallet) throw new \Exception("Recipient wallet not found!");
        if (!$currency) throw new \Exception("Currency not found!");

        $isConversion = (strtolower($type) === 'conversion');
        $isTrade = (strtolower($type) === 'achat' || strtolower($type) === 'vente' || strtolower($type) === 'buy' || strtolower($type) === 'sell');

       // --- INPUT CONTROLS ---
        if (!$srcWallet) throw new \Exception("Source wallet not found!");
        if (!$destWallet) throw new \Exception("Recipient wallet not found!");
        if (!$currency) throw new \Exception("Currency not found!");

        $srcType = strtolower($srcWallet->getTypeWallet());
        $destType = strtolower($destWallet->getTypeWallet());
        $isConversion = (strtolower($type) === 'conversion');
        $isTrade = in_array(strtolower($type), ['achat', 'vente', 'buy', 'sell']);

        // 1. RÈGLE : FIAT vers AUTRE (Nécessite une conversion)
        if ($srcType === 'fiat' && $destType !== 'fiat') {
            if (!$isConversion) {
                throw new \Exception("Transactions from Fiat to other wallets must be a 'conversion'.");
            }
        }

        // 2. RÈGLE : CRYPTO <-> TRADING (Autorisé avec vérification isTrading)
        $isCryptoTradingPair = ($srcType === 'crypto' && $destType === 'trading') || 
                               ($srcType === 'trading' && $destType === 'crypto');

        if ($isCryptoTradingPair) {
            // Vérifier si la devise est autorisée pour le trading
            if (!$currency->getIsTrading()) {
                throw new \Exception("This currency is not authorized for trading transactions.");
            }
        }

        // 3. RÈGLE PAR DÉFAUT : Si types différents et pas de cas spécifique ci-dessus
        if ($srcType !== $destType && !$isCryptoTradingPair && $srcType !== 'fiat') {
            if (!$isConversion && !$isTrade) {
                throw new \Exception("Forbidden transaction: Wallet types do not match.");
            }
        }

        // Status Check
        if ($srcWallet->getStatut() === 'bloque') {
            throw new \Exception("The source wallet is blocked!");
        }
        // Status Check
        if ($srcWallet->getStatut() === 'bloque') {
            throw new \Exception("The source wallet is blocked!");
        }

        // --- START SQL TRANSACTION ---
        $this->em->getConnection()->beginTransaction();

        try {
            // 2. Debit Management
            $srcWC = $this->em->getRepository(WalletCurrency::class)->findOneBy([
                'wallet' => $srcWallet,
                'currency' => $currency
            ]);

            if (!$srcWC || $srcWC->getSolde() < $amount) {
                throw new \Exception("Insufficient balance in the source wallet for this currency.");
            }

            $srcWC->setSolde($srcWC->getSolde() - $amount);

            // 3. Credit Management
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

            // 4. Create Transaction Object (Corrected Setters)
            $t = new Transaction();
            $t->setWalletSource($srcWallet);
            $t->setWalletDestination($destWallet);
            $t->setMontant($amount);
            $t->setType($type);
            $t->setStatut('VALID');
            $t->setDateTransaction(new \DateTime());
            
            // Use the object $currency
            $t->setCurrency($currency); 
            
            $this->em->persist($t);

            // 5. Update Modification Dates
            if (method_exists($srcWallet, 'setDateDerniereModification')) {
                $srcWallet->setDateDerniereModification(new \DateTime());
            }
            if (method_exists($destWallet, 'setDateDerniereModification')) {
                $destWallet->setDateDerniereModification(new \DateTime());
            }

            // 6. SYNC DOCTRINE (Flush to get the Transaction ID for the Blockchain)
            $this->em->flush();

            // 7. BLOCKCHAIN SECURITY
            $this->blockchain->addBlock($t);
            
            // Final flush for the Blockchain entry
            $this->em->flush();

            // 8. FINAL VALIDATION
            $this->em->getConnection()->commit();

        } catch (\Exception $e) {
            // 🚨 ROLLBACK
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->getConnection()->rollBack();
            }
            throw new \Exception("Transaction failed: " . $e->getMessage());
        }
    }
}