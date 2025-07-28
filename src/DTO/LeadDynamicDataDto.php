<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class LeadDynamicDataDto
{
  #[Assert\NotBlank(message: "Dynamic field name is required.")]
  #[Assert\Length(max: 25, maxMessage: "Dynamic field name cannot be longer than {{ limit }} characters.")]
  public ?string $fieldName = null;

  public mixed $fieldValue = null;

  #[Assert\Choice(choices: ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json'], message: "Invalid field type.")]
  public ?string $fieldType = null;
}