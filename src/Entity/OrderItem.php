<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\OrderItemRepository;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $quantity = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private string $unit_price = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private string $sub_total = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: false)]
    private string $discount_applied = '0';

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id')]
    private ?Order $order = null;

    public function getId(): ?int { return $this->id; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function getUnit_price(): string { return $this->unit_price; }
    public function setUnit_price(string $unit_price): self { $this->unit_price = $unit_price; return $this; }
    public function getUnitPrice(): string { return $this->unit_price; }
    public function setUnitPrice(string $unit_price): static { $this->unit_price = $unit_price; return $this; }

    public function getSub_total(): string { return $this->sub_total; }
    public function setSub_total(string $sub_total): self { $this->sub_total = $sub_total; return $this; }
    public function getSubTotal(): string { return $this->sub_total; }
    public function setSubTotal(string $sub_total): static { $this->sub_total = $sub_total; return $this; }

    public function getDiscount_applied(): string { return $this->discount_applied; }
    public function setDiscount_applied(string $discount_applied): self { $this->discount_applied = $discount_applied; return $this; }
    public function getDiscountApplied(): string { return $this->discount_applied; }
    public function setDiscountApplied(string $discount_applied): static { $this->discount_applied = $discount_applied; return $this; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): self { $this->product = $product; return $this; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $order): self { $this->order = $order; return $this; }
}