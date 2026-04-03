<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use App\Repository\WalletCurrencyRepository;

#[ORM\Entity(repositoryClass: WalletCurrencyRepository::class)]
#[ORM\Table(name: 'wallet_currency')]
class WalletCurrency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_wallet_currency', type: 'integer')]
    private ?int $idWalletCurrency = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'walletCurrencys')]
    #[ORM\JoinColumn(name: 'id_wallet', referencedColumnName: 'id_wallet')]
    private ?Wallet $wallet = null;

    #[ORM\ManyToOne(targetEntity: Currency::class, inversedBy: 'walletCurrencys')]
    #[ORM\JoinColumn(name: 'id_currency', referencedColumnName: 'id_currency')]
    private ?Currency $currency = null;

    #[ORM\Column(type: 'float', precision: 18, scale: 8, nullable: true)]
    private ?float $solde = null;

    #[ORM\Column(name: 'nom_currency', type: 'string', nullable: false)]
    private ?string $nomCurrency = null;

    // --- Getters & Setters ---

    public function getIdWalletCurrency(): ?int
    {
        return $this->idWalletCurrency;
    }

    public function setIdWalletCurrency(int $idWalletCurrency): self
    {
        $this->idWalletCurrency = $idWalletCurrency;
        return $this;
    }

    public function getWallet(): ?Wallet
    {
        return $this->wallet;
    }

    public function setWallet(?Wallet $wallet): self
    {
        $this->wallet = $wallet;
        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getSolde(): ?float
    {
        return $this->solde;
    }

    public function setSolde(?float $solde): self
    {
        $this->solde = $solde;
        return $this;
    }

    public function getNomCurrency(): ?string
    {
        return $this->nomCurrency;
    }

    public function setNomCurrency(string $nomCurrency): self
    {
        $this->nomCurrency = $nomCurrency;
        return $this;
    }
}