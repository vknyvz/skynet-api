<?php

namespace App\Entity;

use App\Repository\LeadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LeadRepository::class)]
#[ORM\Table(name: 'leads')]
#[ORM\Index(columns: ['email'], name: 'idx_lead_email')]
#[ORM\Index(columns: ['phone'], name: 'idx_lead_phone')]
#[ORM\Index(columns: ['created_at'], name: 'idx_lead_created_at')]
#[ORM\Index(columns: ['status'], name: 'idx_lead_status')]
class Lead
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  #[Groups(['lead:read', 'lead:write'])]
  private ?int $id = null;

  #[ORM\Column(length: 100)]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  #[Groups(['lead:read', 'lead:write'])]
  private ?string $firstName = null;

  #[ORM\Column(length: 100)]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  #[Groups(['lead:read', 'lead:write'])]
  private ?string $lastName = null;

  #[ORM\Column(length: 255, unique: true)]
  #[Assert\NotBlank]
  #[Assert\Email]
  #[Assert\Length(max: 255)]
  #[Groups(['lead:read', 'lead:write'])]
  private ?string $email = null;

  #[ORM\Column(length: 20, nullable: true)]
  #[Assert\Length(max: 20)]
  #[Assert\Regex('/^[\+]?[\d\-\(\)\s]+$/', message: 'Invalid phone number format')]
  #[Groups(['lead:read', 'lead:write'])]
  private ?string $phone = null;

  #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
  #[Assert\Type(\DateTimeInterface::class)]
  #[Groups(['lead:read', 'lead:write'])]
  private ?\DateTimeInterface $dateOfBirth = null;

  #[ORM\Column(length: 20, options: ['default' => 'active'])]
  #[Assert\Choice(choices: ['active', 'inactive', 'converted', 'invalid'])]
  #[Groups(['lead:read', 'lead:write'])]
  private string $status = 'active';

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  #[Groups(['lead:read'])]
  private ?\DateTimeInterface $createdAt = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  #[Groups(['lead:read'])]
  private ?\DateTimeInterface $updatedAt = null;

  #[ORM\OneToMany(mappedBy: 'lead', targetEntity: LeadData::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
  #[Groups(['lead:read:detailed'])]
  private Collection $dynamicData;

  public function __construct()
  {
    $this->dynamicData = new ArrayCollection();
    $this->createdAt = new \DateTime();
    $this->updatedAt = new \DateTime();
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getFirstName(): ?string
  {
    return $this->firstName;
  }

  public function setFirstName(string $firstName): static
  {
    $this->firstName = $firstName;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function getLastName(): ?string
  {
    return $this->lastName;
  }

  public function setLastName(string $lastName): static
  {
    $this->lastName = $lastName;
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

  public function getPhone(): ?string
  {
    return $this->phone;
  }

  public function setPhone(?string $phone): static
  {
    $this->phone = $phone;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function getDateOfBirth(): ?\DateTimeInterface
  {
    return $this->dateOfBirth;
  }

  public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): static
  {
    $this->dateOfBirth = $dateOfBirth;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function getStatus(): string
  {
    return $this->status;
  }

  public function setStatus(string $status): static
  {
    $this->status = $status;
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

  /**
   * @return Collection<int, LeadData>
   */
  public function getDynamicData(): Collection
  {
    return $this->dynamicData;
  }

  public function addDynamicData(LeadData $dynamicData): static
  {
    if (!$this->dynamicData->contains($dynamicData)) {
      $this->dynamicData->add($dynamicData);
      $dynamicData->setLead($this);
    }

    return $this;
  }

  public function removeDynamicData(LeadData $dynamicData): static
  {
    if ($this->dynamicData->removeElement($dynamicData)) {
      if ($dynamicData->getLead() === $this) {
        $dynamicData->setLead(null);
      }
    }

    return $this;
  }

  public function getFullName(): string
  {
    return $this->firstName . ' ' . $this->lastName;
  }

  public function setDynamicField(string $fieldName, mixed $value): static
  {
    // Find existing dynamic data for this field
    $existingData = $this->dynamicData->filter(
      fn(LeadData $data) => $data->getFieldName() === $fieldName
    )->first();

    if ($existingData) {
      $existingData->setFieldValue($value);
    } else {
      $newData = new LeadData();
      $newData->setFieldName($fieldName);
      $newData->setFieldValue($value);
      $this->addDynamicData($newData);
    }

    return $this;
  }

  public function getDynamicField(string $fieldName): mixed
  {
    $data = $this->dynamicData->filter(
      fn(LeadData $data) => $data->getFieldName() === $fieldName
    )->first();

    return $data ? $data->getFieldValue() : null;
  }
}