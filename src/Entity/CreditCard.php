<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\CreditCardRepository;

#[ORM\Entity(repositoryClass: CreditCardRepository::class)]
#[ORM\Table(name: 'credit_card')]
class CreditCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_card = null;

    public function getId_card(): ?int
    {
        return $this->id_card;
    }

    public function setId_card(int $id_card): self
    {
        $this->id_card = $id_card;
        return $this;
    }
    public function __construct()
{
    $this->blockchains = new ArrayCollection();
    $this->transactions = new ArrayCollection();
}

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'creditCards')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $card_holder_name = null;

    public function getCard_holder_name(): ?string
    {
        return $this->card_holder_name;
    }

    public function setCard_holder_name(string $card_holder_name): self
    {
        $this->card_holder_name = $card_holder_name;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $last_4_digits = null;

    public function getLast_4_digits(): ?string
    {
        return $this->last_4_digits;
    }

    public function setLast_4_digits(string $last_4_digits): self
    {
        $this->last_4_digits = $last_4_digits;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $expiry_month = null;

    public function getExpiry_month(): ?int
    {
        return $this->expiry_month;
    }

    public function setExpiry_month(int $expiry_month): self
    {
        $this->expiry_month = $expiry_month;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $expiry_year = null;

    public function getExpiry_year(): ?int
    {
        return $this->expiry_year;
    }

    public function setExpiry_year(int $expiry_year): self
    {
        $this->expiry_year = $expiry_year;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $stripe_customer_id = null;

    public function getStripe_customer_id(): ?string
    {
        return $this->stripe_customer_id;
    }

    public function setStripe_customer_id(?string $stripe_customer_id): self
    {
        $this->stripe_customer_id = $stripe_customer_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $stripe_payment_method_id = null;

    public function getStripe_payment_method_id(): ?string
    {
        return $this->stripe_payment_method_id;
    }

    public function setStripe_payment_method_id(?string $stripe_payment_method_id): self
    {
        $this->stripe_payment_method_id = $stripe_payment_method_id;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_ajout = null;

    public function getDate_ajout(): ?\DateTimeInterface
    {
        return $this->date_ajout;
    }

    public function setDate_ajout(?\DateTimeInterface $date_ajout): self
    {
        $this->date_ajout = $date_ajout;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Blockchain::class, mappedBy: 'creditCard')]
    private Collection $blockchains;

    /**
     * @return Collection<int, Blockchain>
     */
    public function getBlockchains(): Collection
    {
        if (!$this->blockchains instanceof Collection) {
            $this->blockchains = new ArrayCollection();
        }
        return $this->blockchains;
    }

    public function addBlockchain(Blockchain $blockchain): self
    {
        if (!$this->getBlockchains()->contains($blockchain)) {
            $this->getBlockchains()->add($blockchain);
        }
        return $this;
    }

    public function removeBlockchain(Blockchain $blockchain): self
    {
        $this->getBlockchains()->removeElement($blockchain);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'creditCard')]
    private Collection $transactions;

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        if (!$this->transactions instanceof Collection) {
            $this->transactions = new ArrayCollection();
        }
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): self
    {
        if (!$this->getTransactions()->contains($transaction)) {
            $this->getTransactions()->add($transaction);
        }
        return $this;
    }

    public function removeTransaction(Transaction $transaction): self
    {
        $this->getTransactions()->removeElement($transaction);
        return $this;
    }

    public function getIdCard(): ?int
    {
        return $this->id_card;
    }

    public function getCardHolderName(): ?string
    {
        return $this->card_holder_name;
    }

    public function setCardHolderName(string $card_holder_name): static
    {
        $this->card_holder_name = $card_holder_name;

        return $this;
    }

    public function getLast4Digits(): ?string
    {
        return $this->last_4_digits;
    }

    public function setLast4Digits(string $last_4_digits): static
    {
        $this->last_4_digits = $last_4_digits;

        return $this;
    }

    public function getExpiryMonth(): ?int
    {
        return $this->expiry_month;
    }

    public function setExpiryMonth(int $expiry_month): static
    {
        $this->expiry_month = $expiry_month;

        return $this;
    }

    public function getExpiryYear(): ?int
    {
        return $this->expiry_year;
    }

    public function setExpiryYear(int $expiry_year): static
    {
        $this->expiry_year = $expiry_year;

        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripe_customer_id;
    }

    public function setStripeCustomerId(?string $stripe_customer_id): static
    {
        $this->stripe_customer_id = $stripe_customer_id;

        return $this;
    }

    public function getStripePaymentMethodId(): ?string
    {
        return $this->stripe_payment_method_id;
    }

    public function setStripePaymentMethodId(?string $stripe_payment_method_id): static
    {
        $this->stripe_payment_method_id = $stripe_payment_method_id;

        return $this;
    }

    public function getDateAjout(): ?\DateTime
    {
        return $this->date_ajout;
    }

    public function setDateAjout(?\DateTime $date_ajout): static
    {
        $this->date_ajout = $date_ajout;

        return $this;
    }

}
