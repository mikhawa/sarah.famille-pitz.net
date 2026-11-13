<?php

namespace App\Entity;

use App\Enum\Visibility;
use App\Repository\AlbumRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[UniqueEntity(fields: ['slug'], message: 'Un album utilise déjà cette adresse.')]
class Album
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $title = null;

    #[ORM\Column(length: 160, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Uniquement des minuscules, chiffres et tirets.')]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // Date de l'événement (anniversaire, vacances…), distincte de la date de création
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $eventDate = null;

    // Photo affichée en vignette de l'album
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $coverMedia = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // Privé par défaut : un administrateur rend l'album public quand il est prêt
    #[ORM\Column(length: 20, enumType: Visibility::class, options: ['default' => 'private'])]
    private Visibility $visibility = Visibility::Private;

    // Verrou du super-administrateur : les administrateurs ne peuvent plus changer la visibilité
    #[ORM\Column(options: ['default' => false])]
    private bool $visibilityLocked = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getEventDate(): ?\DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(?\DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;

        return $this;
    }

    public function getCoverMedia(): ?Media
    {
        return $this->coverMedia;
    }

    public function setCoverMedia(?Media $coverMedia): static
    {
        $this->coverMedia = $coverMedia;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getVisibility(): Visibility
    {
        return $this->visibility;
    }

    // null (champ absent du formulaire) : privé
    public function setVisibility(?Visibility $visibility): static
    {
        $this->visibility = $visibility ?? Visibility::Private;

        return $this;
    }

    public function isVisibilityLocked(): bool
    {
        return $this->visibilityLocked;
    }

    public function setVisibilityLocked(bool $visibilityLocked): static
    {
        $this->visibilityLocked = $visibilityLocked;

        return $this;
    }

    // Libellé affiché dans l'administration (🔒 : verrouillée par le super-administrateur)
    public function getVisibilityLabel(): string
    {
        return ($this->visibilityLocked ? '🔒 ' : '').$this->visibility->label();
    }

    public function isVisibleTo(?User $user): bool
    {
        return \in_array($this->visibility, Visibility::allowedFor($user), true);
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }
}
