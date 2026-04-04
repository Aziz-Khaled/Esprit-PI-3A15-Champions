<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\CurrencyRepository;

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

    // ... tes collections (OneToMany) ...

    public function __construct()
    {
        $this->conversionsFrom = new ArrayCollection();
        $this->conversionsTo = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->walletCurrencys = new ArrayCollection();
    }

    // --- GETTERS COMPATIBLES TWIG (IMPORTANTS) ---

    public function getId(): ?int 
    { 
        return $this->id_currency; 
    }

    public function getType(): ?string 
    { 
        return $this->type_currency; 
    }

    public function getIsTrading(): ?bool 
    { 
        return $this->is_trading; 
    }

    // --- TES MÉTHODES EXISTANTES ---

    public function getId_currency(): ?int { return $this->id_currency; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    
    public function getType_currency(): ?string { return $this->type_currency; }
    public function setType_currency(string $type_currency): self { $this->type_currency = $type_currency; return $this; }
    
    public function is_trading(): ?bool { return $this->is_trading; }
    public function setIs_trading(bool $is_trading): self { $this->is_trading = $is_trading; return $this; }

    // ... tes getters de collections ...
}