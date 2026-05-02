<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\OrderRepository;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $orderDate;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalAmount = '0';

    #[ORM\Column(type: 'string')]
    private string $status = '';

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'text')]
    private string $shippingAddress = '';

    #[ORM\Column(type: 'string')]
    private string $paymentMethod = '';

    #[ORM\Column(type: 'string')]
    private string $phoneNumber = '';

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orderItems;

    public function __construct()
    {
        $this->orderDate = new \DateTime();
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getOrderDate(): \DateTimeInterface { return $this->orderDate; }
    public function setOrderDate(\DateTimeInterface $orderDate): self { $this->orderDate = $orderDate; return $this; }

    public function getTotalAmount(): string { return $this->totalAmount; }
    public function setTotalAmount(string $totalAmount): self { $this->totalAmount = $totalAmount; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    public function getShippingAddress(): string { return $this->shippingAddress; }
    public function setShippingAddress(string $shippingAddress): self { $this->shippingAddress = $shippingAddress; return $this; }

    public function getPaymentMethod(): string { return $this->paymentMethod; }
    public function setPaymentMethod(string $paymentMethod): self { $this->paymentMethod = $paymentMethod; return $this; }

    public function getPhoneNumber(): string { return $this->phoneNumber; }
    public function setPhoneNumber(string $phoneNumber): self { $this->phoneNumber = $phoneNumber; return $this; }

    /** @return Collection<int, OrderItem> */
    public function getOrderItems(): Collection { return $this->orderItems; }

    public function addOrderItem(OrderItem $orderItem): self
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
        }
        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): self
    {
        $this->orderItems->removeElement($orderItem);
        return $this;
    }
}