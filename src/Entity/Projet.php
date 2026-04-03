<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\ProjetRepository;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
#[ORM\Table(name: 'projet')]
class Projet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_projet', type: 'integer')]
    private ?int $idProjet = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'projets')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $status = null;

    #[ORM\Column(name: 'target_amount', type: 'float', nullable: true)]
    private ?float $targetAmount = null;

    #[ORM\Column(name: 'start_date', type: 'date', nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(name: 'end_date', type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(name: 'image_url', type: 'text', nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $secteur = null;

    #[ORM\OneToMany(targetEntity: Credit::class, mappedBy: 'projet')]
    private Collection $credits;

    public function __construct() { $this->credits = new ArrayCollection(); }

    public function getIdProjet(): ?int { return $this->idProjet; }
    public function setIdProjet(int $idProjet): self { $this->idProjet = $idProjet; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getTargetAmount(): ?float { return $this->targetAmount; }
    public function setTargetAmount(?float $targetAmount): self { $this->targetAmount = $targetAmount; return $this; }
    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(?\DateTimeInterface $startDate): self { $this->startDate = $startDate; return $this; }
    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(?\DateTimeInterface $endDate): self { $this->endDate = $endDate; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self { $this->imageUrl = $imageUrl; return $this; }
    public function getSecteur(): ?string { return $this->secteur; }
    public function setSecteur(?string $secteur): self { $this->secteur = $secteur; return $this; }
    public function getCredits(): Collection { return $this->credits; }
}