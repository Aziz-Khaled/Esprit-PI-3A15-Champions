<?php
// Negociation.php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\NegociationRepository;

#[ORM\Entity(repositoryClass: NegociationRepository::class)]
#[ORM\Table(name: 'negociation')]
class Negociation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_negociation = null;

    #[ORM\ManyToOne(targetEntity: Credit::class, inversedBy: 'negociations')]
    #[ORM\JoinColumn(name: 'credit_id', referencedColumnName: 'id_credit')]
    private ?Credit $credit = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'negociations')]
    #[ORM\JoinColumn(name: 'investor_id', referencedColumnName: 'id_user')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: false)]
    private ?string $montant = null;

    #[ORM\Column(type: 'decimal', precision: 9, scale: 6, nullable: false)]
    private ?string $taux_propose = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $status = null;

    public function getId_negociation(): ?int { return $this->id_negociation; }
    public function getCredit(): ?Credit { return $this->credit; }
    public function setCredit(?Credit $credit): self { $this->credit = $credit; return $this; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }
    public function getMontant(): ?string { return $this->montant; }
    public function setMontant(string $montant): self { $this->montant = $montant; return $this; }
    public function getTauxpropose(): ?string { return $this->taux_propose; }
    public function setTauxpropose(string $taux_propose): self { $this->taux_propose = $taux_propose; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
}