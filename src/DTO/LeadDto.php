<?php

namespace App\DTO;

use App\Contracts\Hydrates;
use App\Validator\UniqueEmail;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEmail]
class LeadDto implements Hydrates
{
  #[Assert\NotBlank(message: "First name is required.")]
  #[Assert\Length(max: 100, maxMessage: "First name cannot be longer than {{ limit }} characters.")]
  public ?string $firstName = null;

  #[Assert\NotBlank(message: "Last name is required.")]
  #[Assert\Length(max: 100, maxMessage: "Last name cannot be longer than {{ limit }} characters.")]
  public ?string $lastName = null;

  #[Assert\NotBlank(message: "Email is required.")]
  #[Assert\Email(message: "The email '{{ value }}' is not a valid email.")]
  #[Assert\Length(max: 255, maxMessage: "Email cannot be longer than {{ limit }} characters.")]
  public ?string $email = null;

  #[Assert\Length(max: 20, maxMessage: "Phone number cannot be longer than {{ limit }} characters.")]
  #[Assert\Regex(
    pattern: '/^[\+]?[\d\-\(\)\s]+$/',
    message: 'Invalid phone number format.'
  )]
  public ?string $phone = null;

  #[Assert\Type(\DateTimeInterface::class, message: "Date of birth must be a valid date.")]
  public ?\DateTimeInterface $dateOfBirth = null;

  #[Assert\Choice(choices: ['active', 'inactive', 'converted', 'invalid'], message: "Invalid status type.")]
  public ?string $status = null;

  /**
   * @var LeadDynamicDataDto[]|null
   */
  #[Assert\Valid]
  public ?array $dynamicData = null;

  public function toArray(): array
  {
    $data = [
      'firstName' => $this->firstName,
      'lastName' => $this->lastName,
      'email' => $this->email,
      'phone' => $this->phone,
      'dateOfBirth' => $this->dateOfBirth?->format('Y-m-d'),
    ];

    if ($this->dynamicData !== null) {
      $dynamicArray = [];
      foreach ($this->dynamicData as $dynamicItem) {
        if ($dynamicItem instanceof LeadDynamicDataDto) {
          $dynamicArray[$dynamicItem->fieldName] = $dynamicItem->fieldValue;
        }
      }
      $data['dynamicData'] = $dynamicArray;
    }

    return array_filter($data, fn($value) => $value !== null);
  }

  public function hydrate(array $data): LeadDto
  {
    $knownFields = [
      'firstName', 'lastName', 'email', 'phone',
      'dateOfBirth', 'status'
    ];

    foreach ($knownFields as $field) {
      if (isset($data[$field])) {
        if ($field === 'dateOfBirth' && $data[$field]) {
          $this->$field = new \DateTime($data[$field]);
        } else {
          $this->$field = $data[$field];
        }
        unset($data[$field]);
      }
    }

    if (!empty($data)) {
      $this->dynamicData = [];
      foreach ($data as $fieldName => $fieldValue) {
        $dynamicDto = new LeadDynamicDataDto();
        $dynamicDto->fieldName = $fieldName;
        $dynamicDto->fieldValue = $fieldValue;
        $dynamicDto->fieldType = $this->inferFieldType($fieldValue);

        $this->dynamicData[] = $dynamicDto;
      }
    }

    return $this;
  }

  private function inferFieldType(mixed $value): string
  {
    return match (true) {
      is_bool($value) => 'boolean',
      is_int($value) => 'integer',
      is_float($value) => 'float',
      is_array($value) => 'json',
      preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) => 'date',
      preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $value) => 'datetime',
      default => 'string'
    };
  }
}