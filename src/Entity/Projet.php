<?php

namespace App\Entity;

use App\Repository\ProjetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
#[ORM\Table(name: 'projet')]
class Projet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    // Correction : Utilisation du nom exact de la colonne en base
    #[ORM\Column(name: 'id_projet', type: 'integer')]
    private ?int $idProjet = null;

    // Correction : Mapping précis pour owner_id -> id_user
    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'projets')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id_user', nullable: false, onDelete: "CASCADE")]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: "Le titre du projet est obligatoire")]
    #[Assert\Length(min: 5, minMessage: "Le titre doit faire au moins 5 caractères")]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: "Veuillez décrire votre projet")]
    private ?string $description = null;

    // Le statut est géré par un ENUM dans ton SQL
    #[ORM\Column(type: 'string', length: 20)]
    private ?string $status = 'DRAFT';

    #[ORM\Column(name: 'target_amount', type: 'float', nullable: true)]
    #[Assert\Positive(message: "Le montant cible doit être un chiffre positif")]
    private ?float $targetAmount = null;

    #[ORM\Column(name: 'start_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(name: 'end_date', type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\GreaterThan(propertyPath: "startDate", message: "La date de fin doit être après la date de début")]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(name: 'image_url', type: Types::TEXT, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $secteur = 'Autre';

    /** @var Collection<int, Credit> */
    #[ORM\OneToMany(targetEntity: Credit::class, mappedBy: 'projet', cascade: ['remove'])]
    private Collection $credits;

    public function __construct()
    {
        $this->credits = new ArrayCollection();
        $this->startDate = new \DateTime(); // Date par défaut
    }

    // --- Getters et Setters ---

    public function getIdProjet(): ?int { return $this->idProjet; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTargetAmount(): ?float { return $this->targetAmount; }
    public function setTargetAmount(?float $targetAmount): self
    {
        $this->targetAmount = $targetAmount;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getSecteur(): ?string { return $this->secteur; }
    public function setSecteur(?string $secteur): self
    {
        $this->secteur = $secteur;
        return $this;
    }

    /** @return Collection<int, Credit> */
    public function getCredits(): Collection { return $this->credits; }
}
