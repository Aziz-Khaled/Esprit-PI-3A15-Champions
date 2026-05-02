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
    #[ORM\JoinColumn(name: 'id_wallet_id', referencedColumnName: 'id_wallet', nullable: false, onDelete: 'CASCADE')]
    private Wallet $wallet;

    #[ORM\ManyToOne(targetEntity: Currency::class, inversedBy: 'walletCurrencys')]
    #[ORM\JoinColumn(name: 'id_currency_id', referencedColumnName: 'id_currency', nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $solde = 0.0;

    #[ORM\Column(name: 'nom_currency', type: 'string', length: 255, nullable: false)]
    private string $nomCurrency = '';

    public function getIdWalletCurrency(): ?int { return $this->idWalletCurrency; }

    public function getWallet(): Wallet { return $this->wallet; }
    public function setWallet(Wallet $wallet): static { $this->wallet = $wallet; return $this; }

    public function getCurrency(): ?Currency { return $this->currency; }
    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;
        if ($currency) {
            $this->nomCurrency = $currency->getNom();
        }
        return $this;
    }

    public function getSolde(): float { return $this->solde; }
    public function setSolde(float $solde): static { $this->solde = $solde; return $this; }

    public function getNomCurrency(): string { return $this->nomCurrency; }
    public function setNomCurrency(string $nomCurrency): static { $this->nomCurrency = $nomCurrency; return $this; }
}