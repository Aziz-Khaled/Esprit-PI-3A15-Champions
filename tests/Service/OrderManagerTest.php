<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Service\OrderManager;
use PHPUnit\Framework\TestCase;

class OrderManagerTest extends TestCase
{
    public function testValidOrder(): void
    {
        $order = new Order();
        $order->setShippingAddress('123 Rue de la Paix');
        $order->setTotalAmount(150.0);

        $manager = new OrderManager();
       
        $this->assertTrue($manager->validate($order));
    }

    public function testOrderWithoutAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'adresse de livraison est obligatoire');

        $order = new Order();
        $order->setTotalAmount(150.0);

        $manager = new OrderManager();
        $manager->validate($order);
    }

    public function testOrderWithNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant total ne peut pas être négatif');

        $order = new Order();
        $order->setShippingAddress('Paris');
        $order->setTotalAmount(-50.0);

        $manager = new OrderManager();
        $manager->validate($order);
    }
}
