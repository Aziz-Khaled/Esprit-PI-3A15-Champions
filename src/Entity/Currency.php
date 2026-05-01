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
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private ?string $type_currency = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $is_trading = null;

    // Relation avec les portefeuilles des utilisateurs
    /** @var Collection<int, WalletCurrency> */
    #[ORM\OneToMany(mappedBy: 'currency', targetEntity: WalletCurrency::class)]
    private Collection $walletCurrencys;

    public function __construct()
    {
        $this->walletCurrencys = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id_currency;
    }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
   
    public function getTypeCurrency(): ?string { return $this->type_currency; }
    public function setTypeCurrency(string $type_currency): self { $this->type_currency = $type_currency; return $this; }

    public function isTrading(): ?bool { return $this->is_trading; }
    public function setIsTrading(bool $is_trading): self
    {
        // Strict Rule: Fiat cannot be traded
        if ($this->type_currency === 'fiat') {
            $this->is_trading = false;
        } else {
            $this->is_trading = $is_trading;
        }
        return $this;
    }

    /**
     * @return Collection<int, WalletCurrency>
     */
    public function getWalletCurrencys(): Collection
    {
        return $this->walletCurrencys;
    }
}