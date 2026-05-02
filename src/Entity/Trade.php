<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\TradeRepository;

#[ORM\Entity(repositoryClass: TradeRepository::class)]
#[ORM\Table(name: 'trade')]
#[ORM\HasLifecycleCallbacks]
class Trade
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', type: 'integer', nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'asset_id', type: 'integer', nullable: false)]
    private int $assetId = 0;

    #[ORM\Column(name: 'trade_type', type: 'string', nullable: false)]
    private string $tradeType = '';

    #[ORM\Column(name: 'order_mode', type: 'string', nullable: false)]
    private string $orderMode = '';

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $price = null;

    #[ORM\Column(type: 'float', nullable: false)]
    private float $quantity = 0.0;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $status = '';

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'executed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $executedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $userId): self { $this->userId = $userId; return $this; }

    public function getAssetId(): int { return $this->assetId; }
    public function setAssetId(int $assetId): self { $this->assetId = $assetId; return $this; }

    public function getTradeType(): string { return $this->tradeType; }
    public function setTradeType(string $tradeType): self { $this->tradeType = $tradeType; return $this; }

    public function getOrderMode(): string { return $this->orderMode; }
    public function setOrderMode(string $orderMode): self { $this->orderMode = $orderMode; return $this; }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $price): self { $this->price = $price; return $this; }

    public function getQuantity(): float { return $this->quantity; }
    public function setQuantity(float $quantity): self { $this->quantity = $quantity; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    
public function setCreatedAt(\DateTimeInterface $createdAt): self
{
    $this->createdAt = $createdAt;
    return $this;
}
    public function getExecutedAt(): ?\DateTimeInterface { return $this->executedAt; }
    public function setExecutedAt(?\DateTimeInterface $executedAt): self { $this->executedAt = $executedAt; return $this; }
}