<?php

namespace App\Entity;

use App\Repository\LeadDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LeadDataRepository::class)]
#[ORM\Table(name: 'lead_data')]
#[ORM\Index(columns: ['lead_id', 'field_name'], name: 'idx_lead_data_lead_field')]
#[ORM\UniqueConstraint(name: 'uniq_lead_field', columns: ['lead_id', 'field_name'])]
class LeadData
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column]
  #[Groups(['leaddata:read'])]
  private ?int $id = null;

  #[ORM\ManyToOne(inversedBy: 'dynamicData')]
  #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
  private ?Lead $lead = null;

  #[ORM\Column(length: 100)]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  #[Groups(['leaddata:read', 'lead:read:detailed'])]
  private ?string $fieldName = null;

  #[ORM\Column(type: Types::TEXT, nullable: true)]
  #[Groups(['leaddata:read', 'lead:read:detailed'])]
  private ?string $fieldValue = null;

  #[ORM\Column(length: 50, options: ['default' => 'string'])]
  #[Assert\Choice(choices: ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json'])]
  #[Groups(['leaddata:read', 'lead:read:detailed'])]
  private string $fieldType = 'string';

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  #[Groups(['leaddata:read'])]
  private ?\DateTimeInterface $createdAt = null;

  #[ORM\Column(type: Types::DATETIME_MUTABLE)]
  #[Groups(['leaddata:read'])]
  private ?\DateTimeInterface $updatedAt = null;

  public function __construct()
  {
    $this->createdAt = new \DateTime();
    $this->updatedAt = new \DateTime();
  }

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getLead(): ?Lead
  {
    return $this->lead;
  }

  public function setLead(?Lead $lead): static
  {
    $this->lead = $lead;
    return $this;
  }

  public function getFieldName(): ?string
  {
    return $this->fieldName;
  }

  public function setFieldName(string $fieldName): static
  {
    $this->fieldName = $fieldName;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function getFieldValue(): ?string
  {
    return $this->fieldValue;
  }

  public function setFieldValue(?string $fieldValue): static
  {
    $this->fieldValue = $fieldValue;
    $this->updatedAt = new \DateTime();
    return $this;
  }

  public function getFieldType(): string
  {
    return $this->fieldType;
  }

  public function setFieldType(string $fieldType): static
  {
    $this->fieldType = $fieldType;
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
   * Get the typed value based on field type
   */
  public function getTypedValue(): mixed
  {
    if ($this->fieldValue === null) {
      return null;
    }

    return match ($this->fieldType) {
      'integer' => (int)$this->fieldValue,
      'float' => (float)$this->fieldValue,
      'boolean' => filter_var($this->fieldValue, FILTER_VALIDATE_BOOLEAN),
      'date' => \DateTime::createFromFormat('Y-m-d', $this->fieldValue),
      'datetime' => \DateTime::createFromFormat('Y-m-d H:i:s', $this->fieldValue),
      'json' => json_decode($this->fieldValue, true),
      default => $this->fieldValue,
    };
  }

  /**
   * Set value with automatic type detection
   */
  public function setValue(mixed $value): static
  {
    if ($value === null) {
      $this->fieldValue = null;
      return $this;
    }

    switch (true) {
      case is_int($value):
        $this->fieldType = 'integer';
        $this->fieldValue = (string)$value;
        break;
      case is_float($value):
        $this->fieldType = 'float';
        $this->fieldValue = (string)$value;
        break;
      case is_bool($value):
        $this->fieldType = 'boolean';
        $this->fieldValue = $value ? '1' : '0';
        break;
      case $value instanceof \DateTimeInterface:
        $this->fieldType = 'datetime';
        $this->fieldValue = $value->format('Y-m-d H:i:s');
        break;
      case is_array($value) || is_object($value):
        $this->fieldType = 'json';
        $this->fieldValue = json_encode($value);
        break;
      default:
        $this->fieldType = 'string';
        $this->fieldValue = (string)$value;
    }

    $this->updatedAt = new \DateTime();
    return $this;
  }
}