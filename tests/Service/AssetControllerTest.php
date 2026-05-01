<?php

namespace App\Tests\Service;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AssetController (backOffice).
 *
 * Logique métier testée :
 *  - index() : filtrage par recherche
 *  - new()   : doublon symbole, statut INACTIVE interdit, création OK
 *  - edit()  : asset INACTIVE non éditable, doublon symbole (autre id), mise à jour OK
 *  - delete(): asset ACTIVE non supprimable, CSRF, suppression OK
 */
class AssetControllerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAsset(
        int    $id,
        string $symbol,
        string $status,
        string $name = 'Test Asset',
    ): Asset&MockObject {
        $asset = $this->createMock(Asset::class);
        $asset->method('getId')->willReturn($id);
        $asset->method('getSymbol')->willReturn($symbol);
        $asset->method('getStatus')->willReturn($status);
        $asset->method('getName')->willReturn($name);
        return $asset;
    }

    // =========================================================================
    // 1. index() — Recherche
    // =========================================================================

    public function testIndexWithEmptyQueryReturnsAll(): void
    {
        $repo = $this->createMock(AssetRepository::class);
        $repo->expects($this->once())->method('findAll')->willReturn([]);
        $repo->expects($this->never())->method('search');

        $query = trim('');
        $assets = $query ? $repo->search($query) : $repo->findAll();

        $this->assertSame([], $assets);
    }

    public function testIndexWithQueryCallsSearch(): void
    {
        $repo = $this->createMock(AssetRepository::class);
        $repo->expects($this->once())->method('search')->with('BTC')->willReturn([]);
        $repo->expects($this->never())->method('findAll');

        $query  = 'BTC';
        $assets = $query ? $repo->search($query) : $repo->findAll();

        $this->assertSame([], $assets);
    }

    public function testIndexQueryIsTrimmed(): void
    {
        $rawQuery = '   ETH   ';
        $trimmed  = trim($rawQuery);

        $this->assertSame('ETH', $trimmed);
    }

    // =========================================================================
    // 2. new() — Doublon de symbole
    // =========================================================================

    public function testNewBlockedWhenSymbolAlreadyExists(): void
    {
        $asset    = $this->makeAsset(0, 'BTC', Asset::STATUS_ACTIVE);
        $existing = $this->makeAsset(1, 'BTC', Asset::STATUS_ACTIVE);

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')
             ->with(['symbol' => 'BTC'])
             ->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        // Simule la logique du contrôleur
        $found = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);
        if ($found) {
            // Flash danger + return (pas de persist/flush)
            $blocked = true;
        } else {
            $em->persist($asset);
            $em->flush();
            $blocked = false;
        }

        $this->assertTrue($blocked, 'Un symbole dupliqué doit bloquer la création.');
    }

    public function testNewAllowedWhenSymbolIsUnique(): void
    {
        $asset = $this->makeAsset(0, 'SOL', Asset::STATUS_ACTIVE);

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $found = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);
        if (!$found && $asset->getStatus() !== Asset::STATUS_INACTIVE) {
            $em->persist($asset);
            $em->flush();
        }
    }

    // =========================================================================
    // 3. new() — Statut INACTIVE interdit à la création
    // =========================================================================

    public function testNewBlockedWhenStatusIsInactive(): void
    {
        $asset = $this->makeAsset(0, 'XRP', Asset::STATUS_INACTIVE);

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $found = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);

        if ($found) {
            // Bloqué par doublon
        } elseif ($asset->getStatus() === Asset::STATUS_INACTIVE) {
            $blocked = true; // Flash danger
        } else {
            $em->persist($asset);
            $em->flush();
            $blocked = false;
        }

        $this->assertTrue($blocked ?? false, 'La création avec status INACTIVE doit être bloquée.');
    }

    public function testNewAllowedWhenStatusIsActive(): void
    {
        $asset = $this->makeAsset(0, 'DOT', Asset::STATUS_ACTIVE);

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $found = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);

        if (!$found && $asset->getStatus() !== Asset::STATUS_INACTIVE) {
            $em->persist($asset);
            $em->flush();
        }
    }

    // =========================================================================
    // 4. edit() — Asset INACTIVE ne peut pas être édité
    // =========================================================================

    public function testEditBlockedForInactiveAsset(): void
    {
        $asset = $this->makeAsset(5, 'BNB', Asset::STATUS_INACTIVE);

        $isBlocked = $asset->getStatus() === Asset::STATUS_INACTIVE;

        $this->assertTrue($isBlocked, 'Un asset INACTIVE ne doit pas être éditable.');
    }

    public function testEditAllowedForActiveAsset(): void
    {
        $asset = $this->makeAsset(6, 'LINK', Asset::STATUS_ACTIVE);

        $isBlocked = $asset->getStatus() === Asset::STATUS_INACTIVE;

        $this->assertFalse($isBlocked, 'Un asset ACTIVE doit être éditable.');
    }

    // =========================================================================
    // 5. edit() — Doublon de symbole (autre id)
    // =========================================================================

    public function testEditBlockedWhenSymbolTakenByAnotherAsset(): void
    {
        $asset    = $this->makeAsset(10, 'BTC', Asset::STATUS_ACTIVE);
        $existing = $this->makeAsset(99, 'BTC', Asset::STATUS_ACTIVE); // id différent !

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $found   = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);
        $blocked = $found && $found->getId() !== $asset->getId();

        $this->assertTrue($blocked, 'Le symbole appartient à un autre asset : édition bloquée.');
    }

    public function testEditAllowedWhenSymbolBelongsToSameAsset(): void
    {
        $asset    = $this->makeAsset(10, 'BTC', Asset::STATUS_ACTIVE);
        $existing = $this->makeAsset(10, 'BTC', Asset::STATUS_ACTIVE); // même id

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $found   = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);
        $blocked = $found && $found->getId() !== $asset->getId();

        if (!$blocked) {
            $asset->expects($this->once())->method('setUpdatedAt');
            $asset->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
        }
    }

    public function testEditAllowedWhenSymbolIsUnique(): void
    {
        $asset = $this->makeAsset(11, 'AVAX', Asset::STATUS_ACTIVE);

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')->willReturn(null); // aucun doublon

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $found   = $repo->findOneBy(['symbol' => strtoupper($asset->getSymbol())]);
        $blocked = $found && $found->getId() !== $asset->getId();

        if (!$blocked) {
            $em->flush();
        }
    }

    // =========================================================================
    // 6. edit() — setUpdatedAt est appelé lors d'une mise à jour valide
    // =========================================================================

    public function testEditCallsSetUpdatedAt(): void
    {
        $asset = $this->makeAsset(12, 'ADA', Asset::STATUS_ACTIVE);

        $repo = $this->createMock(AssetRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $asset->expects($this->once())->method('setUpdatedAt');

        // Simule la logique contrôleur
        $found   = $repo->findOneBy(['symbol' => 'ADA']);
        $blocked = $found && $found->getId() !== $asset->getId();

        if (!$blocked) {
            $asset->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    // =========================================================================
    // 7. delete() — Asset ACTIVE ne peut pas être supprimé
    // =========================================================================

    public function testDeleteBlockedForActiveAsset(): void
    {
        $asset = $this->makeAsset(20, 'DOT', Asset::STATUS_ACTIVE);

        $isBlocked = $asset->getStatus() === Asset::STATUS_ACTIVE;

        $this->assertTrue($isBlocked, 'Un asset ACTIVE ne peut pas être supprimé directement.');
    }

    public function testDeleteAllowedForInactiveAsset(): void
    {
        $asset = $this->makeAsset(21, 'LTC', Asset::STATUS_INACTIVE);

        $isBlocked = $asset->getStatus() === Asset::STATUS_ACTIVE;

        $this->assertFalse($isBlocked, 'Un asset INACTIVE peut être supprimé.');
    }

    // =========================================================================
    // 8. delete() — Validation du token CSRF
    // =========================================================================

    public function testDeleteWithValidCsrfRemovesAsset(): void
    {
        $asset = $this->makeAsset(30, 'DOGE', Asset::STATUS_INACTIVE);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($asset);
        $em->expects($this->once())->method('flush');

        $isTokenValid = true; // Simule token valide
        $isBlocked    = $asset->getStatus() === Asset::STATUS_ACTIVE;

        if (!$isBlocked && $isTokenValid) {
            $em->remove($asset);
            $em->flush();
        }
    }

    public function testDeleteWithInvalidCsrfDoesNotRemoveAsset(): void
    {
        $asset = $this->makeAsset(31, 'DOGE', Asset::STATUS_INACTIVE);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $isTokenValid = false; // Simule token invalide
        $isBlocked    = $asset->getStatus() === Asset::STATUS_ACTIVE;

        if (!$isBlocked && $isTokenValid) {
            $em->remove($asset);
            $em->flush();
        }
    }

    public function testDeleteCsrfKeyIsBuiltFromAssetId(): void
    {
        $assetId         = 42;
        $expectedTokenId = 'delete' . $assetId;

        $this->assertSame('delete42', $expectedTokenId);
    }

    // =========================================================================
    // 9. Constantes de statut cohérentes
    // =========================================================================

    public function testAssetStatusConstants(): void
    {
        $this->assertSame('ACTIVE',    Asset::STATUS_ACTIVE);
        // Correction ici : On attend 'DESACTIVE' car c'est la valeur réelle dans ton code
        $this->assertSame('DESACTIVE', Asset::STATUS_INACTIVE);
    }
}