<?php

namespace App\Entity;

use App\Repository\WalletCurrencyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WalletCurrencyRepository::class)]
#[ORM\Table(name: 'wallet_currency')]
class WalletCurrency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_wallet_currency', type: 'integer')]
    private ?int $idWalletCurrency = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'walletCurrencys')]
    #[ORM\JoinColumn(name: 'id_wallet', referencedColumnName: 'id_wallet', nullable: false)]
    private ?Wallet $wallet = null;

    #[ORM\ManyToOne(targetEntity: Currency::class, inversedBy: 'walletCurrencys')]
    #[ORM\JoinColumn(name: 'id_currency', referencedColumnName: 'id_currency', nullable: false)]
    private ?Currency $currency = null;

    // Initialisé à 0.0 par défaut pour ta logique FinTech
    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private ?float $solde = 0.0;

    #[ORM\Column(name: 'nom_currency', type: 'string', length: 255, nullable: false)]
    private ?string $nomCurrency = null;

    // --- Getters & Setters ---

    public function getIdWalletCurrency(): ?int
    {
        return $this->idWalletCurrency;
    }

    // Le setIdWalletCurrency a été supprimé car l'ID est auto-généré

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
        // Optionnel : on peut auto-remplir le nomCurrency ici si besoin
        if ($currency) {
            $this->nomCurrency = $currency->getNom();
        }
        return $this;
    }

    public function getSolde(): ?float
    {
        return $this->solde;
    }

    public function setSolde(float $solde): self
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