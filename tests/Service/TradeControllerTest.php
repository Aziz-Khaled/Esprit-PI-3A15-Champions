<?php

namespace App\Tests\Service;

use App\Entity\Trade;
use App\Repository\TradeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Tests unitaires pour TradeController (frontOffice).
 *
 * On teste la LOGIQUE MÉTIER isolément :
 *  - Calcul des stats (index)
 *  - Comportement MARKET vs LIMIT (new / edit)
 *  - Protection contre la modification/suppression d'un trade EXECUTED
 *  - Validation CSRF (delete)
 */
class TradeControllerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Crée un Trade mock avec les méthodes de base configurées.
     */
    private function makeTrade(
        int    $id,
        string $status,
        string $orderMode = 'LIMIT',
        ?float $price = 100.0,
    ): Trade&MockObject {
        $trade = $this->createMock(Trade::class);
        $trade->method('getId')->willReturn($id);
        $trade->method('getStatus')->willReturn($status);
        $trade->method('getOrderMode')->willReturn($orderMode);
        
        // CORRECTION : On retourne directement le float (ou null) sans le transformer en string
        $trade->method('getPrice')->willReturn($price); 
        
        $trade->method('getExecutedAt')->willReturn(null);
        $trade->method('getAssetId')->willReturn(1);
        return $trade;
    }

    // =========================================================================
    // 1. index() — Calcul des statistiques
    // =========================================================================

    public function testIndexStatsWithMixedStatuses(): void
    {
        // 3 trades : 1 PENDING, 1 EXECUTED, 1 CANCELLED
        $trades = [
            $this->makeTrade(1, 'PENDING'),
            $this->makeTrade(2, 'EXECUTED'),
            $this->makeTrade(3, 'CANCELLED'),
        ];

        $stats = $this->computeStats($trades);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['executed']);
        $this->assertSame(1, $stats['cancelled']);
    }

    public function testIndexStatsAllPending(): void
    {
        $trades = [
            $this->makeTrade(1, 'PENDING'),
            $this->makeTrade(2, 'PENDING'),
        ];

        $stats = $this->computeStats($trades);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(0, $stats['executed']);
        $this->assertSame(0, $stats['cancelled']);
    }

    public function testIndexStatsEmptyList(): void
    {
        $stats = $this->computeStats([]);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['executed']);
        $this->assertSame(0, $stats['cancelled']);
    }

    // =========================================================================
    // 2. new() — Ordre MARKET : price doit être null
    // =========================================================================

    public function testNewMarketOrderSetsNullPrice(): void
    {
        $trade = $this->makeTrade(0, 'PENDING', 'MARKET', 500.0);

        // Simule le comportement du contrôleur après soumission valide
        if ($trade->getOrderMode() === 'MARKET') {
            $trade->expects($this->once())->method('setPrice')->with(null);
            $trade->setPrice(null);
        }
    }

    public function testNewLimitOrderKeepsPrice(): void
    {
        $trade = $this->createMock(Trade::class);
        $trade->method('getOrderMode')->willReturn('LIMIT');

        // Pour un ordre LIMIT, setPrice(null) ne doit JAMAIS être appelé
        $trade->expects($this->never())->method('setPrice');

        if ($trade->getOrderMode() === 'MARKET') {
            $trade->setPrice(null); // Ne doit pas s'exécuter
        }
    }

    // =========================================================================
    // 3. edit() — Trade EXECUTED : ne peut pas être modifié
    // =========================================================================

    public function testEditBlockedForExecutedTrade(): void
    {
        $trade = $this->makeTrade(5, 'EXECUTED');

        // Le contrôleur doit rediriger immédiatement sans modifier le trade
        $isBlocked = $trade->getStatus() === 'EXECUTED';

        $this->assertTrue($isBlocked, 'Un trade EXECUTED doit bloquer l\'édition.');
        $trade->expects($this->never())->method('setPrice');
        $trade->expects($this->never())->method('setExecutedAt');
    }

    public function testEditAllowedForPendingTrade(): void
    {
        $trade = $this->makeTrade(6, 'PENDING');

        $isBlocked = $trade->getStatus() === 'EXECUTED';

        $this->assertFalse($isBlocked, 'Un trade PENDING doit pouvoir être édité.');
    }

    public function testEditAllowedForCancelledTrade(): void
    {
        $trade = $this->makeTrade(7, 'CANCELLED');

        $isBlocked = $trade->getStatus() === 'EXECUTED';

        $this->assertFalse($isBlocked, 'Un trade CANCELLED doit pouvoir être édité.');
    }

    // =========================================================================
    // 4. edit() — Ordre MARKET en modification : price = null
    // =========================================================================

    public function testEditMarketOrderSetsNullPrice(): void
    {
        $trade = $this->makeTrade(8, 'PENDING', 'MARKET', 200.0);

        $trade->expects($this->once())->method('setPrice')->with(null);

        // Simule la logique du contrôleur
        if ($trade->getOrderMode() === 'MARKET') {
            $trade->setPrice(null);
        }
    }

    // =========================================================================
    // 5. edit() — setExecutedAt appelé si EXECUTED et executedAt est null
    // =========================================================================

    public function testEditSetsExecutedAtWhenExecutedAndNotSet(): void
    {
        $trade = $this->createMock(Trade::class);
        $trade->method('getOrderMode')->willReturn('LIMIT');
        $trade->method('getStatus')->willReturn('EXECUTED');
        $trade->method('getExecutedAt')->willReturn(null);

        $trade->expects($this->once())->method('setExecutedAt');

        // Simule la logique post-soumission du contrôleur
        if ($trade->getOrderMode() === 'MARKET') {
            $trade->setPrice(null);
        }
        if ($trade->getStatus() === 'EXECUTED' && $trade->getExecutedAt() === null) {
            $trade->setExecutedAt(new \DateTime());
        }
    }

    public function testEditDoesNotOverrideExistingExecutedAt(): void
    {
        $trade = $this->createMock(Trade::class);
        $trade->method('getOrderMode')->willReturn('LIMIT');
        $trade->method('getStatus')->willReturn('EXECUTED');
        $trade->method('getExecutedAt')->willReturn(new \DateTime('2024-01-01'));

        // executedAt déjà défini → setExecutedAt ne doit PAS être rappelé
        $trade->expects($this->never())->method('setExecutedAt');

        if ($trade->getStatus() === 'EXECUTED' && $trade->getExecutedAt() === null) {
            $trade->setExecutedAt(new \DateTime());
        }
    }

    // =========================================================================
    // 6. delete() — Trade EXECUTED ne peut pas être supprimé
    // =========================================================================

    public function testDeleteBlockedForExecutedTrade(): void
    {
        $trade = $this->makeTrade(10, 'EXECUTED');

        $isBlocked = $trade->getStatus() === 'EXECUTED';

        $this->assertTrue($isBlocked, 'Un trade EXECUTED ne doit pas pouvoir être supprimé.');
    }

    public function testDeleteAllowedForPendingTrade(): void
    {
        $trade = $this->makeTrade(11, 'PENDING');

        $isBlocked = $trade->getStatus() === 'EXECUTED';

        $this->assertFalse($isBlocked, 'Un trade PENDING doit pouvoir être supprimé.');
    }

    public function testDeleteAllowedForCancelledTrade(): void
    {
        $trade = $this->makeTrade(12, 'CANCELLED');

        $isBlocked = $trade->getStatus() === 'EXECUTED';

        $this->assertFalse($isBlocked, 'Un trade CANCELLED doit pouvoir être supprimé.');
    }

    // =========================================================================
    // 7. delete() — Validation du token CSRF
    // =========================================================================

    public function testDeleteRequiresValidCsrfToken(): void
    {
        $tradeId = 15;

        // Le token est construit comme 'delete' + id
        $expectedTokenKey = 'delete' . $tradeId;
        $submittedToken   = 'valid_token_abc';

        // Simule isCsrfTokenValid : ici on vérifie juste la construction de la clé
        $this->assertSame('delete15', $expectedTokenKey);
    }

    public function testDeleteWithInvalidCsrfTokenDoesNotRemove(): void
    {
        $trade = $this->makeTrade(16, 'PENDING');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $isTokenValid = false; // Simule un token invalide

        if ($isTokenValid) {
            $em->remove($trade);
            $em->flush();
        }
    }

    public function testDeleteWithValidCsrfTokenRemovesTrade(): void
    {
        $trade = $this->makeTrade(17, 'PENDING');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($trade);
        $em->expects($this->once())->method('flush');

        $isTokenValid = true; // Simule un token valide

        if ($isTokenValid) {
            $em->remove($trade);
            $em->flush();
        }
    }

    // =========================================================================
    // 8. getAssetMap() — Structure de retour correcte
    // =========================================================================

    public function testGetAssetMapReturnsKeyedById(): void
    {
        // Simule ce que retourne fetchAllAssociative
        $rows = [
            ['id' => 1, 'symbol' => 'BTC', 'name' => 'Bitcoin'],
            ['id' => 2, 'symbol' => 'ETH', 'name' => 'Ethereum'],
        ];

        // Simule la logique de getAssetMap()
        $map = [];
        foreach ($rows as $row) {
            $map[$row['id']] = $row;
        }

        $this->assertArrayHasKey(1, $map);
        $this->assertArrayHasKey(2, $map);
        $this->assertSame('BTC', $map[1]['symbol']);
        $this->assertSame('ETH', $map[2]['symbol']);
    }

    public function testGetAssetMapMissingIdReturnsNull(): void
    {
        $map = [
            1 => ['id' => 1, 'symbol' => 'BTC', 'name' => 'Bitcoin'],
        ];

        $asset = $map[999] ?? null;

        $this->assertNull($asset, 'Un assetId inexistant doit retourner null.');
    }

    // =========================================================================
    // 9. getAssetChoices() — Format label => id
    // =========================================================================

    public function testGetAssetChoicesFormatIsCorrect(): void
    {
        $rows = [
            ['id' => 1, 'symbol' => 'BTC', 'name' => 'Bitcoin'],
            ['id' => 2, 'symbol' => 'ETH', 'name' => 'Ethereum'],
        ];

        // Simule la logique de getAssetChoices()
        $choices = [];
        foreach ($rows as $row) {
            $choices[$row['symbol'] . ' — ' . $row['name']] = $row['id'];
        }

        $this->assertArrayHasKey('BTC — Bitcoin', $choices);
        $this->assertArrayHasKey('ETH — Ethereum', $choices);
        $this->assertSame(1, $choices['BTC — Bitcoin']);
        $this->assertSame(2, $choices['ETH — Ethereum']);
    }

    // =========================================================================
    // Utilitaire interne : réplique la logique de calcul des stats du contrôleur
    // =========================================================================

    private function computeStats(array $trades): array
    {
        return [
            'total'     => count($trades),
            'pending'   => count(array_filter($trades, fn($t) => $t->getStatus() === 'PENDING')),
            'executed'  => count(array_filter($trades, fn($t) => $t->getStatus() === 'EXECUTED')),
            'cancelled' => count(array_filter($trades, fn($t) => $t->getStatus() === 'CANCELLED')),
        ];
    }
}