<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\CertificatRepository;

#[ORM\Entity(repositoryClass: CertificatRepository::class)]
#[ORM\Table(name: 'certificats')]
class Certificat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_certificat', type: 'integer')]
    private ?int $idCertificat = null;

    #[ORM\ManyToOne(targetEntity: Participation::class, inversedBy: 'certificats')]
    // Correction : referencedColumnName doit être 'id_participation' pour correspondre à l'entité Participation
    #[ORM\JoinColumn(name: 'participation_id', referencedColumnName: 'id_participation', nullable: false)]
    #[Assert\NotNull(message: "La participation est obligatoire.")]
    private ?Participation $participation = null;

    #[ORM\Column(name: 'date_emission', type: 'date', nullable: false)]
    #[Assert\NotBlank(message: "La date d'émission est requise.")]
    #[Assert\LessThanOrEqual("today", message: "La date d'émission ne peut pas être une date future.")]
    private \DateTimeInterface $dateEmission;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: "La mention ne peut pas dépasser 255 caractères.")]
    private ?string $mention = null;

    #[ORM\Column(name: 'url_fichier', type: 'string', length: 255, nullable: true)]
    private ?string $urlFichier = null;

    public function __construct()
    {
        $this->dateEmission = new \DateTime();
    }

    public function getIdCertificat(): ?int { return $this->idCertificat; }

    public function getParticipation(): ?Participation { return $this->participation; }
    public function setParticipation(?Participation $participation): self { $this->participation = $participation; return $this; }

    public function getDateEmission(): \DateTimeInterface { return $this->dateEmission; }
    public function setDateEmission(\DateTimeInterface $dateEmission): self { $this->dateEmission = $dateEmission; return $this; }

    public function getMention(): ?string { return $this->mention; }
    public function setMention(?string $mention): self { $this->mention = $mention; return $this; }

    public function getUrlFichier(): ?string { return $this->urlFichier; }
    public function setUrlFichier(?string $urlFichier): self { $this->urlFichier = $urlFichier; return $this; }
}