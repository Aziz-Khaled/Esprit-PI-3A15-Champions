<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\OrderItemRepository;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $quantity = null;

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

   #[ORM\Column(type: 'float', precision: 10, scale: 2, nullable: false)]
    private ?float $unit_price = null;

    public function getUnit_price(): ?float
    {
        return $this->unit_price;
    }

    public function setUnit_price(float $unit_price): self
    {
        $this->unit_price = $unit_price;
        return $this;
    }

    #[ORM\Column(type: 'float', precision: 10, scale: 2, nullable: false)]
    private ?float $sub_total = null;

    public function getSub_total(): ?float
    {
        return $this->sub_total;
    }

    public function setSub_total(float $sub_total): self
    {
        $this->sub_total = $sub_total;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    private ?Product $product = null;

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id')]
    private ?Order $order = null;

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    #[ORM\Column(type: 'float', precision: 10, scale: 2, nullable: false)]
    private ?float $discount_applied = null;

    public function getDiscount_applied(): ?float
    {
        return $this->discount_applied;
    }

    public function setDiscount_applied(float $discount_applied): self
    {
        $this->discount_applied = $discount_applied;
        return $this;
    }

public function getUnitPrice(): ?float
{
    return $this->unit_price;
}
public function setUnitPrice(float $unit_price): static
{
    $this->unit_price = $unit_price;
    return $this;
}

public function getSubTotal(): ?float
{
    return $this->sub_total;
}
public function setSubTotal(float $sub_total): static
{
    $this->sub_total = $sub_total;
    return $this;
}

public function getDiscountApplied(): ?float
{
    return $this->discount_applied;
}
public function setDiscountApplied(float $discount_applied): static
{
    $this->discount_applied = $discount_applied;
    return $this;
}

}
