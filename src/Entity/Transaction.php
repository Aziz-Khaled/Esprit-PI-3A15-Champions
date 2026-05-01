<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\TransactionRepository;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transaction')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_transaction', type: 'integer')]
    private ?int $idTransaction = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'transactionsSource')]
    #[ORM\JoinColumn(name: 'id_wallet_source', referencedColumnName: 'id_wallet', nullable: true)]
    private ?Wallet $walletSource = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'transactionsDestination')]
    #[ORM\JoinColumn(name: 'id_wallet_destination', referencedColumnName: 'id_wallet', nullable: true)]
    private ?Wallet $walletDestination = null;

    #[ORM\ManyToOne(targetEntity: CreditCard::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'id_card', referencedColumnName: 'id_card', nullable: true)]
    private ?CreditCard $creditCard = null;

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $montant = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'date_transaction', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTransaction = null;

    #[ORM\ManyToOne(targetEntity: Currency::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'id_currency', referencedColumnName: 'id_currency')]
    private ?Currency $currency = null;

    #[ORM\ManyToOne(targetEntity: Conversion::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'id_conversion', referencedColumnName: 'id_conversion', nullable: true)]
    private ?Conversion $conversion = null;
/** @var Collection<int, Blockchain> */
    #[ORM\OneToMany(targetEntity: Blockchain::class, mappedBy: 'transaction')]
    private Collection $blockchains;

    public function __construct()
    {
        $this->blockchains = new ArrayCollection();
    }

    public function getIdTransaction(): ?int { return $this->idTransaction; }
    public function getWalletSource(): ?Wallet { return $this->walletSource; }
    public function setWalletSource(?Wallet $walletSource): self { $this->walletSource = $walletSource; return $this; }
    public function getWalletDestination(): ?Wallet { return $this->walletDestination; }
    public function setWalletDestination(?Wallet $walletDestination): self { $this->walletDestination = $walletDestination; return $this; }
    public function getCreditCard(): ?CreditCard { return $this->creditCard; }
    public function setCreditCard(?CreditCard $creditCard): self { $this->creditCard = $creditCard; return $this; }
    public function getMontant(): ?float { return $this->montant; }
    public function setMontant(float $montant): self { $this->montant = $montant; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }
    public function getDateTransaction(): ?\DateTimeInterface { return $this->dateTransaction; }
    public function setDateTransaction(?\DateTimeInterface $dateTransaction): self { $this->dateTransaction = $dateTransaction; return $this; }
    public function getCurrency(): ?Currency { return $this->currency; }
    public function setCurrency(?Currency $currency): self { $this->currency = $currency; return $this; }
    public function getConversion(): ?Conversion { return $this->conversion; }
    public function setConversion(?Conversion $conversion): self { $this->conversion = $conversion; return $this; }
    /** @return Collection<int, Blockchain> */
    public function getBlockchains(): Collection { return $this->blockchains; }
}
