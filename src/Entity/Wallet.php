<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\WalletRepository;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallet')]
#[ORM\HasLifecycleCallbacks]
class Wallet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_wallet', type: 'integer')]
    private ?int $idWallet = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'wallets')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 8, nullable: false)]
    private string $rib = '';

    #[ORM\Column(name: 'type_wallet', type: 'string', columnDefinition: "ENUM('fiat', 'crypto', 'trading')", nullable: true)]
    private ?string $typeWallet = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $solde = null;

    #[ORM\Column(type: 'string', columnDefinition: "ENUM('bloque', 'actif')", nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime', nullable: false, options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_derniere_modification', type: 'datetime', nullable: false, options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeInterface $dateDerniereModification;

    /** @var Collection<int, Blockchain> */
    #[ORM\OneToMany(targetEntity: Blockchain::class, mappedBy: 'walletSource')]
    private Collection $blockchainSources;

    /** @var Collection<int, Blockchain> */
    #[ORM\OneToMany(targetEntity: Blockchain::class, mappedBy: 'walletDestination')]
    private Collection $blockchainDestinations;

    /** @var Collection<int, Transaction> */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'walletSource')]
    private Collection $transactionsSource;

    /** @var Collection<int, Transaction> */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'walletDestination')]
    private Collection $transactionsDestination;

    /** @var Collection<int, WalletCurrency> */
    #[ORM\OneToMany(targetEntity: WalletCurrency::class, mappedBy: 'wallet', cascade: ['remove'], orphanRemoval: true)]
    private Collection $walletCurrencys;

    public function __construct()
    {
        $this->blockchainSources = new ArrayCollection();
        $this->blockchainDestinations = new ArrayCollection();
        $this->transactionsSource = new ArrayCollection();
        $this->transactionsDestination = new ArrayCollection();
        $this->walletCurrencys = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateDerniereModification = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setInitialDates(): void
    {
        $this->dateDerniereModification = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->dateDerniereModification = new \DateTime();
    }

    public function getIdWallet(): ?int { return $this->idWallet; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    public function getRib(): string { return $this->rib; }
    public function setRib(string $rib): self { $this->rib = $rib; return $this; }

    public function getTypeWallet(): ?string { return $this->typeWallet; }
    public function setTypeWallet(?string $typeWallet): self { $this->typeWallet = $typeWallet; return $this; }

    public function getSolde(): ?string { return $this->solde; }
    public function setSolde(?string $solde): self { $this->solde = $solde; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }

    public function getDateDerniereModification(): \DateTimeInterface { return $this->dateDerniereModification; }

    /** @return Collection<int, Blockchain> */
    public function getBlockchainSources(): Collection { return $this->blockchainSources; }

    /** @return Collection<int, Blockchain> */
    public function getBlockchainDestinations(): Collection { return $this->blockchainDestinations; }

    /** @return Collection<int, Transaction> */
    public function getTransactionsSource(): Collection { return $this->transactionsSource; }

    /** @return Collection<int, Transaction> */
    public function getTransactionsDestination(): Collection { return $this->transactionsDestination; }

    /** @return Collection<int, WalletCurrency> */
    public function getWalletCurrencys(): Collection { return $this->walletCurrencys; }

    public function addWalletCurrency(WalletCurrency $walletCurrency): self
    {
        if (!$this->walletCurrencys->contains($walletCurrency)) {
            $this->walletCurrencys->add($walletCurrency);
            $walletCurrency->setWallet($this);
        }
        return $this;
    }

    public function removeWalletCurrency(WalletCurrency $walletCurrency): self
    {
        if ($this->walletCurrencys->removeElement($walletCurrency)) {
            if ($walletCurrency->getWallet() === $this) {
                $walletCurrency->setWallet(null);
            }
        }
        return $this;
    }
}