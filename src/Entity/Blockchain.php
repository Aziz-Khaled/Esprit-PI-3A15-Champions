<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\BlockchainRepository;

#[ORM\Entity(repositoryClass: BlockchainRepository::class)]
#[ORM\Table(name: 'blockchain')]
class Blockchain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_block = null;

    #[ORM\ManyToOne(targetEntity: Transaction::class, inversedBy: 'blockchains')]
    #[ORM\JoinColumn(name: 'id_transaction', referencedColumnName: 'id_transaction')]
    private ?Transaction $transaction = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $block_index = 0;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $previous_hash = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $current_hash = '';

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'blockchainSources')]
    #[ORM\JoinColumn(name: 'wallet_source', referencedColumnName: 'id_wallet', nullable: true)]
    private ?Wallet $walletSource = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'blockchainDestinations')]
    #[ORM\JoinColumn(name: 'wallet_destination', referencedColumnName: 'id_wallet', nullable: true)]
    private ?Wallet $walletDestination = null;

    #[ORM\Column(type: 'float', nullable: false)]
    private float $montant = 0.0;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $type = '';

    #[ORM\ManyToOne(targetEntity: CreditCard::class, inversedBy: 'blockchains')]
    #[ORM\JoinColumn(name: 'id_card', referencedColumnName: 'id_card', nullable: true)]
    private ?CreditCard $creditCard = null;

    public function getId_block(): ?int { return $this->id_block; }
    public function getTransaction(): ?Transaction { return $this->transaction; }
    public function setTransaction(?Transaction $transaction): self { $this->transaction = $transaction; return $this; }
    public function getBlock_index(): int { return $this->block_index; }
    public function setBlock_index(int $block_index): self { $this->block_index = $block_index; return $this; }
    public function getPrevious_hash(): string { return $this->previous_hash; }
    public function setPrevious_hash(string $previous_hash): self { $this->previous_hash = $previous_hash; return $this; }
    public function getCurrent_hash(): string { return $this->current_hash; }
    public function setCurrent_hash(string $current_hash): self { $this->current_hash = $current_hash; return $this; }
    public function getWalletSource(): ?Wallet { return $this->walletSource; }
    public function setWalletSource(?Wallet $walletSource): self { $this->walletSource = $walletSource; return $this; }
    public function getWalletDestination(): ?Wallet { return $this->walletDestination; }
    public function setWalletDestination(?Wallet $walletDestination): self { $this->walletDestination = $walletDestination; return $this; }
    public function getMontant(): float { return $this->montant; }
    public function setMontant(float $montant): self { $this->montant = $montant; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getCreditCard(): ?CreditCard { return $this->creditCard; }
    public function setCreditCard(?CreditCard $creditCard): self { $this->creditCard = $creditCard; return $this; }
}