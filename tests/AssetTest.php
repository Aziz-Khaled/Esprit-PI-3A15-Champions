<?php

namespace App\Tests;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AssetTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private AssetRepository $repo;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo   = static::getContainer()->get(AssetRepository::class);
    }

    private function createAsset(string $status = 'ACTIVE'): Asset
    {
        $asset = new Asset();
        $asset->setSymbol('TST' . uniqid());
        $asset->setName('Test Asset');
        $asset->setType('crypto');
        $asset->setMarket('binance');
        $asset->setCurrentPrice(100.0);
        $asset->setStatus($status);
        $asset->setCreatedAt(new \DateTimeImmutable());
        $asset->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($asset);
        $this->em->flush();

        return $asset;
    }

    private function removeAsset(Asset $asset): void
    {
        $fresh = $this->em->find(Asset::class, $asset->getId());
        if ($fresh) {
            $this->em->remove($fresh);
            $this->em->flush();
        }
    }

    public function testIndexLoads(): void
    {
        $this->client->request('GET', '/asset/');
        $this->assertResponseIsSuccessful();
    }

   
    public function testNewFormLoads(): void
    {
        $this->client->request('GET', '/asset/new');
        $this->assertResponseIsSuccessful();
    }

   
    public function testEditInactiveAssetIsBlocked(): void
    {
        $asset = $this->createAsset('DESACTIVE');

        $this->client->request('GET', '/asset/' . $asset->getId() . '/edit');

        $this->assertResponseRedirects('/asset/');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');

        $this->removeAsset($asset);
    }

    public function testDeleteActiveAssetIsBlocked(): void
    {
        $asset = $this->createAsset('ACTIVE');
        $id    = $asset->getId();

        $this->client->request('POST', '/asset/' . $id, [
            '_token' => static::getContainer()
                ->get('security.csrf.token_manager')
                ->getToken('delete' . $id)
                ->getValue(),
        ]);

        $this->assertResponseRedirects('/asset/');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');

        // Asset toujours en base
        $this->assertNotNull($this->repo->find($id));

        $this->removeAsset($asset);
    }

    public function testDeleteInactiveAssetSuccess(): void
    {
        $asset = $this->createAsset('DESACTIVE');
        $id    = $asset->getId();

        $this->client->request('POST', '/asset/' . $id, [
            '_token' => static::getContainer()
                ->get('security.csrf.token_manager')
                ->getToken('delete' . $id)
                ->getValue(),
        ]);

        $this->assertResponseRedirects('/asset/');
        $this->assertNull($this->repo->find($id));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}