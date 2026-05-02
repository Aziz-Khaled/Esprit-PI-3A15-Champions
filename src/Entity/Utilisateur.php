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
#[ORM\HasLifecycleCallbacks]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_user', type: 'integer')]
    private ?int $idUser = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $nom = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $prenom = '';

    #[ORM\Column(name: 'mot_de_passe', type: 'string', nullable: false)]
    private string $motDePasse = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $telephone = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $role = '';

    #[ORM\Column(name: 'date_de_creation', type: 'datetime', nullable: false)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'piece_identite', type: 'string', nullable: false)]
    private string $pieceIdentite = '';

    #[ORM\Column(name: 'user_image', type: 'string', nullable: false)]
    private string $userImage = '';

    #[ORM\Column(name: 'date_derniere_connexion', type: 'datetime', nullable: false)]
    private \DateTimeInterface $dateDerniereConnexion;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $email = '';

    #[ORM\Column(type: 'string', nullable: false)]
    private string $statut = '';

    /** @var Collection<int, Asset> */
    #[ORM\OneToMany(targetEntity: Asset::class, mappedBy: 'utilisateur')]
    private Collection $assets;

    /** @var Collection<int, Credit> */
    #[ORM\OneToMany(targetEntity: Credit::class, mappedBy: 'borrower')]
    private Collection $creditsBorrowed;

    /** @var Collection<int, Credit> */
    #[ORM\OneToMany(targetEntity: Credit::class, mappedBy: 'investisseur')]
    private Collection $creditsInvested;

    /** @var Collection<int, CreditCard> */
    #[ORM\OneToMany(targetEntity: CreditCard::class, mappedBy: 'utilisateur')]
    private Collection $creditCards;

    /** @var Collection<int, Formation> */
    #[ORM\OneToMany(targetEntity: Formation::class, mappedBy: 'utilisateur')]
    private Collection $formations;

    /** @var Collection<int, Negociation> */
    #[ORM\OneToMany(targetEntity: Negociation::class, mappedBy: 'utilisateur')]
    private Collection $negociations;

    /** @var Collection<int, Order> */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'utilisateur')]
    private Collection $orders;

    /** @var Collection<int, Participation> */
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'utilisateur', cascade: ['persist'])]
    private Collection $participations;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'utilisateur')]
    private Collection $products;

    /** @var Collection<int, Projet> */
    #[ORM\OneToMany(
    targetEntity: Projet::class,
    mappedBy: 'utilisateur',
    cascade: ['persist'],
    orphanRemoval: true
    )]
    private Collection $projets;

    /** @var Collection<int, Wallet> */
    #[ORM\OneToMany(targetEntity: Wallet::class, mappedBy: 'utilisateur')]
    private Collection $wallets;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateDerniereConnexion = new \DateTime();
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

    #[ORM\PreUpdate]
    public function updateDerniereConnexion(): void
    {
        $this->dateDerniereConnexion = new \DateTime();
    }

    public function getIdUser(): ?int { return $this->idUser; }
    public function setIdUser(int $idUser): self { $this->idUser = $idUser; return $this; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = $prenom; return $this; }

    public function getMotDePasse(): string { return $this->motDePasse; }
    public function setMotDePasse(string $motDePasse): self { $this->motDePasse = $motDePasse; return $this; }

    public function getTelephone(): string { return $this->telephone; }
    public function setTelephone(string $telephone): self { $this->telephone = $telephone; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }

    public function getPieceIdentite(): string { return $this->pieceIdentite; }
    public function setPieceIdentite(string $pieceIdentite): self { $this->pieceIdentite = $pieceIdentite; return $this; }

    public function getUserImage(): string { return $this->userImage; }
    public function setUserImage(string $userImage): self { $this->userImage = $userImage; return $this; }

    public function getDateDerniereConnexion(): \DateTimeInterface { return $this->dateDerniereConnexion; }
    public function setDateDerniereConnexion(\DateTimeInterface $date): self { $this->dateDerniereConnexion = $date; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    /** @return Collection<int, Asset> */
    public function getAssets(): Collection { return $this->assets; }

    /** @return Collection<int, Credit> */
    public function getCreditsBorrowed(): Collection { return $this->creditsBorrowed; }

    /** @return Collection<int, Credit> */
    public function getCreditsInvested(): Collection { return $this->creditsInvested; }

    /** @return Collection<int, CreditCard> */
    public function getCreditCards(): Collection { return $this->creditCards; }

    /** @return Collection<int, Formation> */
    public function getFormations(): Collection { return $this->formations; }

    /** @return Collection<int, Negociation> */
    public function getNegociations(): Collection { return $this->negociations; }

    /** @return Collection<int, Order> */
    public function getOrders(): Collection { return $this->orders; }

    /** @return Collection<int, Participation> */
    public function getParticipations(): Collection { return $this->participations; }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection { return $this->products; }

    /** @return Collection<int, Projet> */
    public function getProjets(): Collection { return $this->projets; }

    /** @return Collection<int, Wallet> */
    public function getWallets(): Collection { return $this->wallets; }

    public function getUserIdentifier(): string { return $this->email; }

    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getRoles(): array
    {
        $map = [
            'CLIENT'       => 'ROLE_CLIENT',
            'ADMIN'        => 'ROLE_ADMIN',
            'COMMERCANT'   => 'ROLE_COMMERCANT',
            'INVESTISSEUR' => 'ROLE_INVESTISSEUR',
        ];
        return [$map[$this->role] ?? 'ROLE_USER'];
    }

    public function eraseCredentials(): void {}

    public function getPassword(): string { return $this->motDePasse; }
}