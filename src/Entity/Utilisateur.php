<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\UtilisateurRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_user', type: 'integer')]
    private ?int $idUser = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $prenom = null;

    #[ORM\Column(name: 'mot_de_passe', type: 'string', nullable: false)]
    private ?string $motDePasse = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $role = null;

    #[ORM\Column(name: 'date_de_creation', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'piece_identite', type: 'string', nullable: false)]
    private ?string $pieceIdentite = null;

    #[ORM\Column(name: 'user_image', type: 'string', nullable: false)]
    private ?string $userImage = null;

    #[ORM\Column(name: 'date_derniere_connexion', type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $dateDerniereConnexion = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $statut = null;

    #[ORM\OneToMany(targetEntity: Asset::class, mappedBy: 'utilisateur')]
    private Collection $assets;

    #[ORM\OneToMany(targetEntity: Credit::class, mappedBy: 'borrower')]
    private Collection $creditsBorrowed;

    #[ORM\OneToMany(targetEntity: Credit::class, mappedBy: 'investisseur')]
    private Collection $creditsInvested;

    #[ORM\OneToMany(targetEntity: CreditCard::class, mappedBy: 'utilisateur')]
    private Collection $creditCards;

    #[ORM\OneToMany(targetEntity: Formation::class, mappedBy: 'utilisateur')]
    private Collection $formations;

    #[ORM\OneToMany(targetEntity: Negociation::class, mappedBy: 'utilisateur')]
    private Collection $negociations;

    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'utilisateur')]
    private Collection $orders;

    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'utilisateur')]
    private Collection $participations;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'utilisateur')]
    private Collection $products;

    #[ORM\OneToMany(targetEntity: Projet::class, mappedBy: 'utilisateur')]
    private Collection $projets;

    #[ORM\OneToMany(targetEntity: Wallet::class, mappedBy: 'utilisateur')]
    private Collection $wallets;

    public function __construct()
    {
        $this->assets = new ArrayCollection();
        $this->creditsBorrowed = new ArrayCollection();
        $this->creditsInvested = new ArrayCollection();
        $this->creditCards = new ArrayCollection();
        $this->formations = new ArrayCollection();
        $this->negociations = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->participations = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->projets = new ArrayCollection();
        $this->wallets = new ArrayCollection();
    }

    public function getIdUser(): ?int { return $this->idUser; }
    public function setIdUser(int $idUser): self { $this->idUser = $idUser; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = $prenom; return $this; }
    public function getMotDePasse(): ?string { return $this->motDePasse; }
    public function setMotDePasse(string $motDePasse): self { $this->motDePasse = $motDePasse; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(string $telephone): self { $this->telephone = $telephone; return $this; }
    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): self { $this->dateCreation = $dateCreation; return $this; }
    public function getPieceIdentite(): ?string { return $this->pieceIdentite; }
    public function setPieceIdentite(string $pieceIdentite): self { $this->pieceIdentite = $pieceIdentite; return $this; }
    public function getUserImage(): ?string { return $this->userImage; }
    public function setUserImage(string $userImage): self { $this->userImage = $userImage; return $this; }
    public function getDateDerniereConnexion(): ?\DateTimeInterface { return $this->dateDerniereConnexion; }
    public function setDateDerniereConnexion(\DateTimeInterface $dateDerniereConnexion): self { $this->dateDerniereConnexion = $dateDerniereConnexion; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getAssets(): Collection { return $this->assets; }
    public function getCreditsBorrowed(): Collection { return $this->creditsBorrowed; }
    public function getCreditsInvested(): Collection { return $this->creditsInvested; }
    public function getCreditCards(): Collection { return $this->creditCards; }
    public function getFormations(): Collection { return $this->formations; }
    public function getNegociations(): Collection { return $this->negociations; }
    public function getOrders(): Collection { return $this->orders; }
    public function getParticipations(): Collection { return $this->participations; }
    public function getProducts(): Collection { return $this->products; }
    public function getProjets(): Collection { return $this->projets; }
    public function getWallets(): Collection { return $this->wallets; }


    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
{
    $map = [
        'CLIENT'      => 'ROLE_CLIENT',
        'ADMIN'       => 'ROLE_ADMIN',
        'COMMERCANT'  => 'ROLE_COMMERCANT',
        'INVESTISSEUR'=> 'ROLE_INVESTISSEUR',
    ];

    return [$map[$this->role] ?? 'ROLE_USER'];
}

    public function eraseCredentials(): void {}

    public function getPassword(): string
    {
        return $this->motDePasse;
    }
}