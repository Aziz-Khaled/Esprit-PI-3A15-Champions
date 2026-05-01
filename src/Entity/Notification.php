<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\NotificationRepository;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_notification = null;

    public function getId_notification(): ?int
    {
        return $this->id_notification;
    }

    public function setId_notification(int $id_notification): self
    {
        $this->id_notification = $id_notification;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $id_transaction = null;

    public function getId_transaction(): ?int
    {
        return $this->id_transaction;
    }

    public function setId_transaction(?int $id_transaction): self
    {
        $this->id_transaction = $id_transaction;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type_notification = null;

    public function getType_notification(): ?string
    {
        return $this->type_notification;
    }

    public function setType_notification(string $type_notification): self
    {
        $this->type_notification = $type_notification;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_read = null;

    public function is_read(): ?bool
    {
        return $this->is_read;
    }

    public function setIs_read(?bool $is_read): self
    {
        $this->is_read = $is_read;
        return $this;
    }

    public function getIdNotification(): ?int
    {
        return $this->id_notification;
    }

    public function getIdTransaction(): ?int
    {
        return $this->id_transaction;
    }

    public function setIdTransaction(?int $id_transaction): static
    {
        $this->id_transaction = $id_transaction;

        return $this;
    }

    public function getTypeNotification(): ?string
    {
        return $this->type_notification;
    }

    public function setTypeNotification(string $type_notification): static
    {
        $this->type_notification = $type_notification;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->is_read;
    }

    public function setIsRead(?bool $is_read): static
    {
        $this->is_read = $is_read;

        return $this;
    }

}
