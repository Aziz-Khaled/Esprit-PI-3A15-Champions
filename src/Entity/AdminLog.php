<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\AdminLogRepository;

#[ORM\Entity(repositoryClass: AdminLogRepository::class)]
#[ORM\Table(name: 'admin_log')]
class AdminLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $action;

    #[ORM\Column(type: 'string')]
    private string $entity;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: 'string')]
    private string $performedBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(string $action, string $entity, string $performedBy, ?string $details = null)
    {
        $this->action      = $action;
        $this->entity      = $entity;
        $this->performedBy = $performedBy;
        $this->details     = $details;
        $this->createdAt   = new \DateTime();
    }

    public function getId(): ?int                      { return $this->id; }
    public function getAction(): string                { return $this->action; }
    public function getEntity(): string                { return $this->entity; }
    public function getDetails(): ?string              { return $this->details; }
    public function getPerformedBy(): string           { return $this->performedBy; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}