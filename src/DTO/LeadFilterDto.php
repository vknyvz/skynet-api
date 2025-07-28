<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class LeadFilterDto
{
  #[Assert\PositiveOrZero]
  public int $page = 1;

  #[Assert\Range(min: 1, max: 100)]
  public int $limit = 20;

  #[Assert\Choice(choices: ['active', 'inactive', 'converted', 'invalid'])]
  public ?string $status = null;

  public ?string $email = null;

  #[Assert\Length(max: 100)]
  public ?string $search = null;

  public function toArray(): array
  {
    $data = [
      'page' => $this->page,
      'limit' => $this->limit,
      'status' => $this->status,
      'email' => $this->email,
      'search' => $this->search,
    ];

    return array_filter($data, fn($value) => $value !== null);
  }
}