<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\ParticipationRepository;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participations')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_participation', type: 'integer')]
    private ?int $idParticipation = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(name: 'formation_id', referencedColumnName: 'id_formation')]
    private ?Formation $formation = null;

    #[ORM\Column(name: 'date_inscription', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $presence = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $note = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    /**
     * @var Collection<int, Certificat>
     */
    #[ORM\OneToMany(targetEntity: Certificat::class, mappedBy: 'participation')]
    private Collection $certificats;

    public function __construct()
    {
        $this->certificats = new ArrayCollection();
    }

    public function getIdParticipation(): ?int
    {
        return $this->idParticipation;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;
        return $this;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(?\DateTimeInterface $dateInscription): self
    {
        $this->dateInscription = $dateInscription;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function isPresence(): ?bool
    {
        return $this->presence;
    }

    public function setPresence(?bool $presence): self
    {
        $this->presence = $presence;
        return $this;
    }

    public function getNote(): ?float
    {
        return $this->note;
    }

    public function setNote(?float $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    /**
     * @return Collection<int, Certificat>
     */
    public function getCertificats(): Collection
    {
        return $this->certificats;
    }
}