<?php

namespace App\Entity;

use App\Enum\MediaType;
use App\Repository\MediaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Index(name: 'idx_media_taken_at', columns: ['taken_at'])]
#[ORM\Index(name: 'idx_media_created_at', columns: ['created_at'])]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10, enumType: MediaType::class)]
    private MediaType $type;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Chemin relatif au dossier des originaux (ex. 2026/10/<uuid>.jpg), jamais exposé publiquement
     */
    #[ORM\Column(length: 255, unique: true)]
    private string $storagePath;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    // Taille en octets du fichier stocké (après nettoyage)
    #[ORM\Column]
    private int $size = 0;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    // Durée en secondes (audio et vidéo)
    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    // Date de prise de vue (EXIF / métadonnées vidéo) ou saisie par l'admin ; sert au fil chronologique
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $takenAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Album $album = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'media_tag')]
    private Collection $tags;

    // Conservé même si le compte de l'auteur est supprimé
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(MediaType $type, string $storagePath, string $originalFilename, string $mimeType)
    {
        $this->type = $type;
        $this->storagePath = $storagePath;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): MediaType
    {
        return $this->type;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Titre saisi, sinon nom du fichier d'origine sans extension
     */
    public function getDisplayTitle(): string
    {
        return $this->title ?: pathinfo($this->originalFilename, \PATHINFO_FILENAME);
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

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setDimensions(?int $width, ?int $height): static
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getTakenAt(): ?\DateTimeImmutable
    {
        return $this->takenAt;
    }

    public function setTakenAt(?\DateTimeImmutable $takenAt): static
    {
        $this->takenAt = $takenAt;

        return $this;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(?Album $album): static
    {
        $this->album = $album;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->getDisplayTitle();
    }
}
