<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\CreditRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CreditRepository::class)]
#[ORM\Table(name: 'credit')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_credit = null;

    #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'credits')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id_projet', nullable: false)]
    #[Assert\NotNull(message: "Le projet est obligatoire")]
    private ?Projet $projet = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'creditsBorrowed')]
    #[ORM\JoinColumn(name: 'borrower_id', referencedColumnName: 'id_user', nullable: false)]
    #[Assert\NotNull(message: "L'emprunteur est obligatoire")]
    private ?Utilisateur $borrower = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'creditsInvested')]
    #[ORM\JoinColumn(name: 'investisseur_id', referencedColumnName: 'id_user', nullable: true)]
    private ?Utilisateur $investisseur = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: false)]
    #[Assert\NotBlank(message: "Le montant ne peut pas être vide")]
    #[Assert\Positive(message: "Le montant doit être supérieur à zéro")]
    private ?string $montant = null;

    #[ORM\Column(type: 'string', length: 8, nullable: false, options: ["default" => "EUR"])]
    #[Assert\NotBlank]
    private ?string $devise = "EUR";

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: false)]
    #[Assert\NotBlank(message: "Le taux est obligatoire")]
    #[Assert\PositiveOrZero(message: "Le taux ne peut pas être négatif")]
    private ?string $taux = null;

    #[ORM\Column(type: 'integer', nullable: false, options: ["unsigned" => true])]
    #[Assert\NotBlank(message: "La durée est obligatoire")]
    #[Assert\GreaterThan(0, message: "La durée doit être d'au moins 1 mois")]
    private ?int $duree = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', nullable: false, options: ["default" => "OPEN"])]
    private ?string $status = "OPEN";

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $contrat_id = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_demande = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_contrat = null;
/** @var Collection<int, Negociation> */
    #[ORM\OneToMany(targetEntity: Negociation::class, mappedBy: 'credit', cascade: ['persist', 'remove'])]
    private Collection $negociations;

    public function __construct()
    {
        $this->negociations = new ArrayCollection();
        $this->date_demande = new \DateTime(); // Initialisation automatique à la création
    }

    // Getters et Setters obligatoires pour le CRUD
    public function getIdCredit(): ?int { return $this->id_credit; }

    public function getProjet(): ?Projet { return $this->projet; }
    public function setProjet(?Projet $projet): self { $this->projet = $projet; return $this; }

    public function getBorrower(): ?Utilisateur { return $this->borrower; }
    public function setBorrower(?Utilisateur $borrower): self { $this->borrower = $borrower; return $this; }

    public function getInvestisseur(): ?Utilisateur { return $this->investisseur; }
    public function setInvestisseur(?Utilisateur $investisseur): self { $this->investisseur = $investisseur; return $this; }

    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): self { $this->montant = $montant; return $this; }

    public function getDevise(): ?string { return $this->devise; }
    public function setDevise(string $devise): self { $this->devise = $devise; return $this; }

    public function getTaux(): ?string { return $this->taux; }
    public function setTaux(string $taux): self { $this->taux = $taux; return $this; }

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(int $duree): self { $this->duree = $duree; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getContratId(): ?string { return $this->contrat_id; }
    public function setContratId(?string $contrat_id): self { $this->contrat_id = $contrat_id; return $this; }

    public function getDateDemande(): ?\DateTimeInterface { return $this->date_demande; }
    public function setDateDemande(\DateTimeInterface $date_demande): self { $this->date_demande = $date_demande; return $this; }

    public function getDateContrat(): ?\DateTimeInterface { return $this->date_contrat; }
    public function setDateContrat(?\DateTimeInterface $date_contrat): self { $this->date_contrat = $date_contrat; return $this; }

    /**
     * @return Collection<int, Negociation>
     */
    public function getNegociations(): Collection { return $this->negociations; }

    public function addNegociation(Negociation $negociation): self
    {
        if (!$this->negociations->contains($negociation)) {
            $this->negociations[] = $negociation;
            $negociation->setCredit($this);
        }
        return $this;
    }

    public function removeNegociation(Negociation $negociation): self
    {
        if ($this->negociations->removeElement($negociation)) {
            if ($negociation->getCredit() === $this) {
                $negociation->setCredit(null);
            }
        }
        return $this;
    }
}