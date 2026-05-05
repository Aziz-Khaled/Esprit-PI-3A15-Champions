<?php

namespace App\Entity;

use App\Repository\CurrencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'currency')]
class Currency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_currency = null;

    #[ORM\Column(type: 'string', length: 10, nullable: false)]
    private string $code = '';

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $nom = '';

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private string $type_currency = '';

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $is_trading = false;

    /** @var Collection<int, WalletCurrency> */
    #[ORM\OneToMany(mappedBy: 'currency', targetEntity: WalletCurrency::class)]
    private Collection $walletCurrencys;

    /** @var Collection<int, Transaction> */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'currency')]
    private Collection $transactions;

    /** @var Collection<int, Conversion> */
    #[ORM\OneToMany(targetEntity: Conversion::class, mappedBy: 'currencyFrom')]
    private Collection $conversionsFrom;

    /** @var Collection<int, Conversion> */
    #[ORM\OneToMany(targetEntity: Conversion::class, mappedBy: 'currencyTo')]
    private Collection $conversionsTo;

    public function __construct()
    {
        $this->walletCurrencys = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->conversionsFrom = new ArrayCollection();
        $this->conversionsTo = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id_currency; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getTypeCurrency(): string { return $this->type_currency; }
    public function setTypeCurrency(string $type_currency): self { $this->type_currency = $type_currency; return $this; }

    public function isTrading(): bool { return $this->is_trading; }
    public function setIsTrading(bool $is_trading): self
    {
        $this->is_trading = ($this->type_currency === 'fiat') ? false : $is_trading;
        return $this;
    }

    /** @return Collection<int, WalletCurrency> */
    public function getWalletCurrencys(): Collection { return $this->walletCurrencys; }

    /** @return Collection<int, Transaction> */
    public function getTransactions(): Collection { return $this->transactions; }

    /** @return Collection<int, Conversion> */
    public function getConversionsFrom(): Collection { return $this->conversionsFrom; }

    /** @return Collection<int, Conversion> */
    public function getConversionsTo(): Collection { return $this->conversionsTo; }
}