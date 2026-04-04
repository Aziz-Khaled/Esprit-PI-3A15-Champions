<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\ProductRepository;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

   #[ORM\Column(type: 'float', precision: 10, scale: 2, nullable: false)]
    private ?float $price = null;

   #[ORM\Column(name: 'discount_price', type: 'float', precision: 10, scale: 2, nullable: true)]
    private ?float $discountPrice = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $brand = null;

   #[ORM\Column(name: 'avg_rating', type: 'float', precision: 3, scale: 2, nullable: true)]
    private ?float $avgRating = null;

    #[ORM\Column(name: 'image_url', type: 'text', nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stock = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'product')]
    private Collection $orderItems;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getDiscountPrice(): ?float
    {
        return $this->discountPrice;
    }

    public function setDiscountPrice(?float $discountPrice): self
    {
        $this->discountPrice = $discountPrice;
        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    public function getAvgRating(): ?float
    {
        return $this->avgRating;
    }

    public function setAvgRating(?float $avgRating): self
    {
        $this->avgRating = $avgRating;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): self
    {
        $this->stock = $stock;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

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