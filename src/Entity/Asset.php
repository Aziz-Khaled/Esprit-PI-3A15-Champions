<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AssetRepository;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[ORM\Table(name: 'asset')]
#[ORM\HasLifecycleCallbacks]
class Asset
{
    public const TYPE_CRYPTO  = 'crypto';
    public const TYPE_STOCK   = 'stock';
    public const TYPE_FOREX   = 'forex';

    public const MARKET_BINANCE = 'binance';
    public const MARKET_CRYPTO  = 'crypto';
    public const MARKET_NYSE    = 'nyse';
    public const MARKET_NASDAQ  = 'nasdaq';

    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_INACTIVE = 'DESACTIVE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $symbol = '';

    #[ORM\Column(type: 'string', length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $type = '';

    #[ORM\Column(type: 'string', length: 50)]
    private string $market = '';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8)]
    private string $currentPrice = '0';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: true)]
    private ?Utilisateur $utilisateur = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSymbol(): string { return $this->symbol; }
    public function setSymbol(string $symbol): static
    {
        $this->symbol = strtoupper($symbol);
        return $this;
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getMarket(): string { return $this->market; }
    public function setMarket(string $market): static { $this->market = $market; return $this; }

    public function getCurrentPrice(): string { return $this->currentPrice; }
    public function setCurrentPrice(string $currentPrice): static { $this->currentPrice = $currentPrice; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(?string $status): static
    {
        $this->status = $status ?? self::STATUS_ACTIVE;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}