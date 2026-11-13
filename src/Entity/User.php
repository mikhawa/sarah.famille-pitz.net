<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Niveaux d'accès, du plus bas au plus haut (voir Visibility::requiredLevel())
    public const LEVEL_MEMBER = 1;
    public const LEVEL_ADMIN = 2;
    public const LEVEL_SUPER_ADMIN = 3;

    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Mot de passe haché
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $displayName = null;

    // Permet de bloquer un compte sans le supprimer
    #[ORM\Column]
    private bool $isActive = true;

    // Membre autorisé à envoyer des médias dans les albums publics (accordé par un administrateur)
    #[ORM\Column(options: ['default' => false])]
    private bool $canUpload = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Tout utilisateur connecté est au minimum membre de la famille
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Niveau le plus élevé parmi les rôles (un seul rôle est attribué : membre, administrateur ou super-administrateur)
     */
    public function getLevel(): int
    {
        return self::levelFromRoles($this->roles);
    }

    /**
     * @param list<string> $roles rôles stockés (sans la hiérarchie)
     */
    public static function levelFromRoles(array $roles): int
    {
        return match (true) {
            \in_array(self::ROLE_SUPER_ADMIN, $roles, true) => self::LEVEL_SUPER_ADMIN,
            \in_array(self::ROLE_ADMIN, $roles, true) => self::LEVEL_ADMIN,
            default => self::LEVEL_MEMBER,
        };
    }

    // Administrateur ou super-administrateur
    public function isAdmin(): bool
    {
        return $this->getLevel() >= self::LEVEL_ADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return self::LEVEL_SUPER_ADMIN === $this->getLevel();
    }

    // Utilisé par les tests et l'ancienne case « Administrateur » : membre <-> administrateur
    public function setAdmin(bool $admin): static
    {
        return $this->setLevel($admin ? self::LEVEL_ADMIN : self::LEVEL_MEMBER);
    }

    /**
     * Remplace le rôle du compte ; les autres rôles éventuels sont conservés
     */
    public function setLevel(int $level): static
    {
        $roles = array_values(array_diff($this->roles, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN]));
        $role = match ($level) {
            self::LEVEL_SUPER_ADMIN => self::ROLE_SUPER_ADMIN,
            self::LEVEL_ADMIN => self::ROLE_ADMIN,
            self::LEVEL_MEMBER => null,
            default => throw new \InvalidArgumentException(\sprintf('Niveau inconnu : %d', $level)),
        };
        if (null !== $role) {
            $roles[] = $role;
        }
        $this->roles = $roles;

        return $this;
    }

    public static function levelLabel(int $level): string
    {
        return match ($level) {
            self::LEVEL_SUPER_ADMIN => 'Super-administrateur',
            self::LEVEL_ADMIN => 'Administrateur',
            default => 'Membre',
        };
    }

    public function getLevelLabel(): string
    {
        return self::levelLabel($this->getLevel());
    }

    public function canUpload(): bool
    {
        return $this->canUpload;
    }

    public function setCanUpload(bool $canUpload): static
    {
        $this->canUpload = $canUpload;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ne stocke jamais le hash du mot de passe en session (Symfony 7.3+)
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return (string) $this->displayName;
    }
}
