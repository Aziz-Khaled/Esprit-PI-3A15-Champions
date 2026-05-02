<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use App\Repository\FormationRepository;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Table(name: 'formations')]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idFormation', type: 'integer')]
    private ?int $idFormation = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le titre de la formation est requis.')]
    private string $titre = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $domaine = null;

    #[ORM\Column(name: 'dateDebut', type: 'date', nullable: false)]
    #[Assert\NotBlank(message: 'La date de début est requise.')]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(name: 'dateFin', type: 'date', nullable: false)]
    #[Assert\NotBlank(message: 'La date de fin est requise.')]
    private \DateTimeInterface $dateFin;

    #[ORM\Column(type: 'float', nullable: false)]
    #[Assert\NotBlank(message: 'Le prix est requis.')]
    #[Assert\Positive(message: 'Le prix doit être un nombre positif.')]
    private float $prix = 0.0;

    #[ORM\Column(name: 'capaciteMax', type: 'integer', nullable: false)]
    #[Assert\NotBlank(message: 'La capacité maximale est requise.')]
    private int $capaciteMax = 0;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statut = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'formations')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    public function __construct()
    {
        $this->dateDebut = new \DateTime();
        $this->dateFin = new \DateTime();
    }

    public function getIdFormation(): ?int { return $this->idFormation; }

    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getDomaine(): ?string { return $this->domaine; }
    public function setDomaine(?string $domaine): self { $this->domaine = $domaine; return $this; }

    public function getDateDebut(): \DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): self { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): \DateTimeInterface { return $this->dateFin; }
    public function setDateFin(\DateTimeInterface $dateFin): self { $this->dateFin = $dateFin; return $this; }

    public function getPrix(): float { return $this->prix; }
    public function setPrix(float $prix): self { $this->prix = $prix; return $this; }

    public function getCapaciteMax(): int { return $this->capaciteMax; }
    public function setCapaciteMax(int $capaciteMax): self { $this->capaciteMax = $capaciteMax; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }

    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if ($this->dateFin <= $this->dateDebut) {
            $context->buildViolation('La date de fin doit être postérieure à la date de début.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }
}