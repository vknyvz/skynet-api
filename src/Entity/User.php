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
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['username'], name: 'idx_user_username')]
#[ORM\Index(columns: ['email'], name: 'idx_user_email')]
#[ORM\Index(columns: ['is_active'], name: 'idx_user_active')]
#[UniqueEntity(fields: ['username'], message: 'Username already exists')]
#[UniqueEntity(fields: ['email'], message: 'Email already exists')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  private ?int $id = null;

  #[ORM\Column(length: 180, unique: true)]
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 180)]
  private ?string $username = null;

  #[ORM\Column(length: 255, unique: true)]
  #[Assert\NotBlank]
  #[Assert\Email]
  private ?string $email = null;

  /**
   * @var list<string> The user roles
   */
  #[ORM\Column]
  private array $roles = [];

  /**
   * @var string The hashed password
   */
  #[ORM\Column]
  private ?string $password = null;

  #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
  private bool $isActive = true;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $createdAt = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  private ?\DateTimeInterface $updatedAt = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
  private ?\DateTimeInterface $lastLoginAt = null;

  public function __construct()
  {
    $this->createdAt = new \DateTime();
    $this->updatedAt = new \DateTime();
    $this->roles = ['ROLE_USER'];
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getUsername(): ?string
  {
    return $this->username;
  }

  public function setUsername(string $username): static
  {
    $this->username = $username;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function getEmail(): ?string
  {
    return $this->email;
  }

  public function setEmail(string $email): static
  {
    $this->email = $email;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  /**
   * A visual identifier that represents this user.
   *
   * @see UserInterface
   */
  public function getUserIdentifier(): string
  {
    return (string)$this->username;
  }

  /**
   * @return list<string>
   * @see UserInterface
   *
   */
  public function getRoles(): array
  {
    $roles = $this->roles;
    // guarantee every user at least has ROLE_USER
    $roles[] = 'ROLE_USER';

    return array_unique($roles);
  }

  /**
   * @param list<string> $roles
   */
  public function setRoles(array $roles): static
  {
    $this->roles = $roles;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  /**
   * @see PasswordAuthenticatedUserInterface
   */
  public function getPassword(): ?string
  {
    return $this->password;
  }

  public function setPassword(string $password): static
  {
    $this->password = $password;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  /**
   * @see UserInterface
   */
  public function eraseCredentials(): void
  {
    // If you store any temporary, sensitive data on the user, clear it here
    // $this->plainPassword = null;
  }

  public function isActive(): bool
  {
    return $this->isActive;
  }

  public function setIsActive(bool $isActive): static
  {
    $this->isActive = $isActive;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function getCreatedAt(): ?\DateTimeInterface
  {
    return $this->createdAt;
  }

  public function setCreatedAt(\DateTimeInterface $createdAt): static
  {
    $this->createdAt = $createdAt;
    return $this;
  }

  public function getUpdatedAt(): ?\DateTimeInterface
  {
    return $this->updatedAt;
  }

  public function setUpdatedAt(\DateTimeInterface $updatedAt): static
  {
    $this->updatedAt = $updatedAt;
    return $this;
  }

  public function getLastLoginAt(): ?\DateTimeInterface
  {
    return $this->lastLoginAt;
  }

  public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
  {
    $this->lastLoginAt = $lastLoginAt;
    return $this;
  }

  public function updateLastLogin(): static
  {
    $this->lastLoginAt = new \DateTime();
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function hasRole(string $role): bool
  {
    return in_array($role, $this->getRoles());
  }

  public function addRole(string $role): static
  {
    if (!$this->hasRole($role)) {
      $this->roles[] = $role;
      $this->updatedAt = new \DateTime();
    }
    return $this;
  }

  public function removeRole(string $role): static
  {
    if ($role !== 'ROLE_USER') { // Prevent removing base role
      $this->roles = array_values(array_diff($this->roles, [$role]));
      $this->updatedAt = new \DateTime();
    }
    return $this;
  }
}