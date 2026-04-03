<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\CreditRepository;

#[ORM\Entity(repositoryClass: CreditRepository::class)]
#[ORM\Table(name: 'credit')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_credit = null;

    #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'credits')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id_projet')]
    private ?Projet $projet = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'creditsBorrowed')]
    #[ORM\JoinColumn(name: 'borrower_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $borrower = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'creditsInvested')]
    #[ORM\JoinColumn(name: 'investisseur_id', referencedColumnName: 'id_user', nullable: true)]
    private ?Utilisateur $investisseur = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: false)]
    private ?string $montant = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $devise = null;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: false)]
    private ?string $taux = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $duree = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $status = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $contrat_id = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_demande = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $date_contrat = null;

    #[ORM\OneToMany(targetEntity: Negociation::class, mappedBy: 'credit')]
    private Collection $negociations;

    public function __construct()
    {
        $this->negociations = new ArrayCollection();
    }

    public function getId_credit(): ?int { return $this->id_credit; }
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
    public function getContrat_id(): ?string { return $this->contrat_id; }
    public function setContrat_id(?string $contrat_id): self { $this->contrat_id = $contrat_id; return $this; }
    public function getDate_demande(): ?\DateTimeInterface { return $this->date_demande; }
    public function setDate_demande(\DateTimeInterface $date_demande): self { $this->date_demande = $date_demande; return $this; }
    public function getDate_contrat(): ?\DateTimeInterface { return $this->date_contrat; }
    public function setDate_contrat(?\DateTimeInterface $date_contrat): self { $this->date_contrat = $date_contrat; return $this; }
    public function getNegociations(): Collection { return $this->negociations; }
}