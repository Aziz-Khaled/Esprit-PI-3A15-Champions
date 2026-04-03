<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AssetRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'asset')]
class Asset
{
    public const TYPE_CRYPTO  = 'crypto';
    public const TYPE_STOCK   = 'stock';
    public const TYPE_FOREX   = 'forex';

    public const MARKET_CRYPTO = 'crypto';
    public const MARKET_NYSE   = 'nyse';
    public const MARKET_NASDAQ = 'nasdaq';

    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_INACTIVE = 'DESACTIVE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank(message: 'Symbol is required.')]
    #[Assert\Length(
        min: 2, max: 10,
        minMessage: 'Symbol must be at least {{ limit }} characters.',
        maxMessage: 'Symbol cannot exceed {{ limit }} characters.'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]+$/',
        message: 'Symbol must contain only uppercase letters and numbers.'
    )]
    private ?string $symbol = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Name is required.')]
    #[Assert\Length(
        min: 2, max: 100,
        minMessage: 'Name must be at least {{ limit }} characters.',
        maxMessage: 'Name cannot exceed {{ limit }} characters.'
    )]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Please select an asset type.')]
    #[Assert\Choice(
        choices: [self::TYPE_CRYPTO, self::TYPE_STOCK, self::TYPE_FOREX],
        message: 'Invalid asset type.'
    )]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Please select a market.')]
    #[Assert\Choice(
        choices: [self::MARKET_CRYPTO, self::MARKET_NYSE, self::MARKET_NASDAQ],
        message: 'Invalid market.'
    )]
    private ?string $market = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8)]
    #[Assert\NotBlank(message: 'Price is required.')]
    #[Assert\Positive(message: 'Price must be greater than zero.')]
    private ?string $currentPrice = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank(message: 'Please select a status.')]
    #[Assert\Choice(
        choices: [self::STATUS_ACTIVE, self::STATUS_PENDING, self::STATUS_INACTIVE],
        message: 'Invalid status.'
    )]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int { return $this->id; }

    public function getSymbol(): ?string { return $this->symbol; }
    public function setSymbol(?string $symbol): static 
    { 
        $this->symbol = $symbol ? strtoupper($symbol) : null; 
        return $this; 
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static 
    { 
        $this->name = $name; 
        return $this; 
    }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static 
    { 
        $this->type = $type ?? null; 
        return $this; 
    }

    public function getMarket(): ?string { return $this->market; }
    public function setMarket(?string $market): static 
    { 
        $this->market = $market ?? null; 
        return $this; 
    }

    public function getCurrentPrice(): ?string { return $this->currentPrice; }
    public function setCurrentPrice(?string $currentPrice): static 
    { 
        $this->currentPrice = $currentPrice; 
        return $this; 
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(?string $status): static 
    { 
        $this->status = $status ?? self::STATUS_ACTIVE; 
        return $this; 
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static 
    { 
        $this->createdAt = $createdAt; 
        return $this; 
    }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static 
    { 
        $this->updatedAt = $updatedAt; 
        return $this; 
    }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): static 
    { 
        $this->utilisateur = $utilisateur; 
        return $this; 
    }
}