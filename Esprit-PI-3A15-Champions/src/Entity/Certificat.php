<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\CertificatRepository;

#[ORM\Entity(repositoryClass: CertificatRepository::class)]
#[ORM\Table(name: 'certificats')]
class Certificat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $idCertificat = null;

    public function getIdCertificat(): ?int
    {
        return $this->idCertificat;
    }

    public function setIdCertificat(int $idCertificat): self
    {
        $this->idCertificat = $idCertificat;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Participation::class, inversedBy: 'certificats')]
    #[ORM\JoinColumn(name: 'idParticipation', referencedColumnName: 'idParticipation')]
    private ?Participation $participation = null;

    public function getParticipation(): ?Participation
    {
        return $this->participation;
    }

    public function setParticipation(?Participation $participation): self
    {
        $this->participation = $participation;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $dateEmission = null;

    public function getDateEmission(): ?\DateTimeInterface
    {
        return $this->dateEmission;
    }

    public function setDateEmission(\DateTimeInterface $dateEmission): self
    {
        $this->dateEmission = $dateEmission;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $mention = null;

    public function getMention(): ?string
    {
        return $this->mention;
    }

    public function setMention(?string $mention): self
    {
        $this->mention = $mention;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $urlFichier = null;

    public function getUrlFichier(): ?string
    {
        return $this->urlFichier;
    }

    public function setUrlFichier(?string $urlFichier): self
    {
        $this->urlFichier = $urlFichier;
        return $this;
    }

}
