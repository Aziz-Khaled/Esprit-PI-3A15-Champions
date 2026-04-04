<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AssetRepository;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'asset')]
class Asset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private ?string $symbol = null;

    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $market = null;

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $current_price = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int { return $this->id; }
    public function getSymbol(): ?string { return $this->symbol; }
    public function setSymbol(string $symbol): self { $this->symbol = $symbol; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getMarket(): ?string { return $this->market; }
    public function setMarket(string $market): self { $this->market = $market; return $this; }
    public function getCurrent_price(): ?float { return $this->current_price; }
    public function setCurrent_price(float $current_price): self { $this->current_price = $current_price; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getCreated_at(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreated_at(\DateTimeInterface $created_at): self { $this->created_at = $created_at; return $this; }
    public function getUpdated_at(): ?\DateTimeInterface { return $this->updated_at; }
    public function setUpdated_at(?\DateTimeInterface $updated_at): self { $this->updated_at = $updated_at; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }
}