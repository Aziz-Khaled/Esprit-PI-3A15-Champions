<?php

namespace App\Service;

use App\Entity\Order;

class OrderManager
{
    /**
     * Valide les règles métier d'une commande.
     * 1. L'adresse de livraison est obligatoire.
     * 2. Le montant total ne peut pas être négatif.
     */
    public function validate(Order $order): bool
    {
        if (empty($order->getShippingAddress())) {
            throw new \InvalidArgumentException('L\'adresse de livraison est obligatoire');
        }

        if ($order->getTotalAmount() < 0) {
            throw new \InvalidArgumentException('Le montant total ne peut pas être négatif');
        }

        return true;
    }
}