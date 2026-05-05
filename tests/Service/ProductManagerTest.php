<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Service\ProductManager;
use PHPUnit\Framework\TestCase;

class ProductManagerTest extends TestCase
{
    public function testValidProduct(): void
    {
        $product = new Product();
        $product->setName('Tesla Model 3');
        $product->setPrice(45000);

        $manager = new ProductManager();
       
        $this->assertTrue($manager->validate($product));
    }

    public function testProductWithoutName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $product = new Product();
        $product->setPrice(45000);

        $manager = new ProductManager();
        $manager->validate($product);
    }

    public function testProductWithInvalidPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être supérieur à 0');

        $product = new Product();
        $product->setName('Test Product');
        $product->setPrice(-10);

        $manager = new ProductManager();
        $manager->validate($product);
    }
}