<?php

namespace App\Tests\Service;

use App\Entity\Currency;
use App\Entity\Wallet;
use App\Entity\WalletCurrency;
use App\Repository\CurrencyRepository;
use App\Repository\WalletRepository;
use App\Service\BlockchainService;
use App\Service\ConversionService;
use App\Service\TransactionManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class TransactionManagerTest extends TestCase
{
    private function makeWallet(string $type, string $statut = 'actif'): Wallet
    {
        $w = new Wallet();
        $w->setTypeWallet($type);
        $w->setStatut($statut);
        $w->setRib('RIB' . uniqid());
        return $w;
    }

    private function makeCurrency(string $type, bool $trading = false): Currency
    {
        $c = new Currency();
        $c->setTypeCurrency($type);
        $c->setNom($type === 'fiat' ? 'TND' : 'BTC');
        $c->setCode($type === 'fiat' ? 'TND' : 'BTC');
        $c->setIsTrading($trading);
        return $c;
    }

    private function buildManager(
        ?Wallet $srcWallet,
        ?Wallet $destWallet,
        ?Currency $currency,
        ?Currency $targetCur = null,
        float $srcBalance = 1000.0
    ): TransactionManager {

        $conn = $this->createMock(Connection::class);
        $conn->method('isTransactionActive')->willReturn(true);

        $srcWC = $this->createMock(WalletCurrency::class);
        $srcWC->method('getSolde')->willReturn($srcBalance);
        $srcWC->method('setSolde')->willReturnSelf();

        $wcRepo = $this->createMock(EntityRepository::class);
        $wcRepo->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($srcWallet, $srcWC) {
                if (isset($criteria['wallet']) && $criteria['wallet'] === $srcWallet) {
                    return $srcWC;
                }
                return null;
            });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $em->method('getRepository')->willReturn($wcRepo);
        // persist() est void — on n'appelle PAS willReturn()
        $em->method('persist');
        // flush() est void — idem
        $em->method('flush');

        $walletRepo = $this->createMock(WalletRepository::class);
        $walletRepo->method('findOneBy')
            ->willReturnOnConsecutiveCalls($srcWallet, $destWallet);

        $currencyRepo = $this->createMock(CurrencyRepository::class);
        $currencyRepo->method('find')
            ->willReturnMap([
                [7, $currency],
                [5, $targetCur],
            ]);

        $blockchain = $this->createMock(BlockchainService::class);
        $blockchain->method('addBlock');

        $conversionService = $this->createMock(ConversionService::class);
        $conversionService->method('getExchangeRate')->willReturn(1.177);

        return new TransactionManager($em, $walletRepo, $currencyRepo, $blockchain, $conversionService);
    }

    // GROUPE 1 — VALIDATIONS D'ENTRÉE

    public function testMontantNegatifLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Amount must be positive');

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('fiat'), $this->makeCurrency('fiat'))
             ->execute('16573838', 'RIB2', -100, 'TRANSFER', 7);
    }

    public function testMontantZeroLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Amount must be positive');

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('fiat'), $this->makeCurrency('fiat'))
             ->execute('16573838', 'RIB2', 0, 'TRANSFER', 7);
    }

    public function testWalletSourceIntrouvableLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source wallet not found');

        $this->buildManager(null, $this->makeWallet('fiat'), $this->makeCurrency('fiat'))
             ->execute('RIB_INEXISTANT', 'RIB2', 100, 'TRANSFER', 7);
    }

    public function testWalletDestinationIntrouvableLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Recipient wallet not found');

        $this->buildManager($this->makeWallet('fiat'), null, $this->makeCurrency('fiat'))
             ->execute('16573838', 'RIB_INEXISTANT', 100, 'TRANSFER', 7);
    }

    public function testDeviseIntrouvableLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Currency not found');

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('fiat'), null)
             ->execute('16573838', 'RIB2', 100, 'TRANSFER', 7);
    }

    // GROUPE 2 — SÉCURITÉ WALLET

    public function testWalletBloqueLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source wallet is blocked');

        $this->buildManager($this->makeWallet('fiat', 'bloque'), $this->makeWallet('fiat'), $this->makeCurrency('fiat'))
             ->execute('16573838', 'RIB2', 100, 'TRANSFER', 7);
    }

    public function testEnumStatutCorrect(): void
    {
        $this->assertEquals('actif',  $this->makeWallet('fiat', 'actif')->getStatut());
        $this->assertEquals('bloque', $this->makeWallet('fiat', 'bloque')->getStatut());
    }

    // GROUPE 3 — RÈGLES INTER-WALLET

    public function testFiatVersCryptoSansConversionLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("CONVERSION");

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('crypto'), $this->makeCurrency('fiat'))
             ->execute('16573838', '24837277', 100, 'TRANSFER', 7);
    }

    public function testFiatVersTradingSansConversionLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("CONVERSION");

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('trading'), $this->makeCurrency('fiat'))
             ->execute('16573838', 'RIB_TRADING', 100, 'TRANSFER', 7);
    }

    public function testDeviseFiatDansWalletCryptoLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Incompatible asset');

        $this->buildManager($this->makeWallet('crypto'), $this->makeWallet('crypto'), $this->makeCurrency('fiat'))
             ->execute('24837277', 'RIB2', 100, 'TRANSFER', 7);
    }

    public function testTypesIncompatiblesSansConversionLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Forbidden transaction');

        $this->buildManager($this->makeWallet('crypto'), $this->makeWallet('fiat'), $this->makeCurrency('crypto'))
             ->execute('24837277', '16573838', 100, 'TRANSFER', 7);
    }

    // GROUPE 4 — SOLDE

    public function testSoldeInsuffisantLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('fiat'), $this->makeCurrency('fiat'), null, 50.0)
             ->execute('16573838', '47220967', 200, 'TRANSFER', 7);
    }

    public function testSoldeExactementSuffisantPasseSansException(): void
    {
        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('fiat'), $this->makeCurrency('fiat'), null, 100.0)
             ->execute('16573838', '47220967', 100, 'TRANSFER', 7);
        $this->assertTrue(true);
    }

    // GROUPE 5 — CONVERSION

    public function testConversionSansTargetCurrencyIdLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Target currency ID is required');

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('crypto'), $this->makeCurrency('fiat'))
             ->execute('16573838', '24837277', 100, 'CONVERSION', 7, null);
    }

    public function testConversionAvecDeviseCibleIntrouvableLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Target currency not found');

        $this->buildManager($this->makeWallet('fiat'), $this->makeWallet('crypto'), $this->makeCurrency('fiat'), null, 1000.0)
             ->execute('16573838', '24837277', 100, 'CONVERSION', 7, 5);
    }

    public function testConversionVersDeviseFiatDansWalletCryptoLeveException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conversion failed');

        $this->buildManager(
            $this->makeWallet('fiat'),
            $this->makeWallet('crypto'),
            $this->makeCurrency('fiat'),
            $this->makeCurrency('fiat'),
            1000.0
        )->execute('16573838', '24837277', 100, 'CONVERSION', 7, 5);
    }

    // GROUPE 6 — CAS NOMINAUX

    public function testTransfertFiatVersFiatValide(): void
    {
        $this->buildManager(
            $this->makeWallet('fiat', 'actif'),
            $this->makeWallet('fiat', 'actif'),
            $this->makeCurrency('fiat'),
            null,
            1000.0
        )->execute('16573838', '47220967', 100, 'TRANSFER', 7);
        $this->assertTrue(true);
    }

    public function testTransfertCryptoVersCryptoValide(): void
    {
        $this->buildManager(
            $this->makeWallet('crypto', 'actif'),
            $this->makeWallet('crypto', 'actif'),
            $this->makeCurrency('crypto', true),
            null,
            694.0
        )->execute('24837277', 'RIB_DEST', 50, 'TRANSFER', 7);
        $this->assertTrue(true);
    }

    public function testConversionFiatVersCryptoValide(): void
    {
        $this->buildManager(
            $this->makeWallet('fiat', 'actif'),
            $this->makeWallet('crypto', 'actif'),
            $this->makeCurrency('fiat'),
            $this->makeCurrency('crypto', true),
            1000.0
        )->execute('16573838', '24837277', 100, 'CONVERSION', 7, 5);
        $this->assertTrue(true);
    }

    public function testAchatCryptoVersTradingValide(): void
    {
        $cur = new Currency();
        $cur->setTypeCurrency('crypto');
        $cur->setNom('BTC');
        $cur->setCode('BTC');
        $cur->setIsTrading(true);

        $this->buildManager(
            $this->makeWallet('crypto', 'actif'),
            $this->makeWallet('trading', 'actif'),
            $cur,
            null,
            1000.0
        )->execute('24837277', 'RIB_TRADING', 100, 'ACHAT', 7);
        $this->assertTrue(true);
    }
}
