<?php

namespace App\Message;

use App\DTO\LeadDto;

readonly class ProcessLeadMessage
{
  public function __construct(
    private LeadDto $leadDto,
    private mixed $batchId = null
  ) {}

  public function getLeadDto(): LeadDto
  {
    return $this->leadDto;
  }

  public function getBatchId(): mixed
  {
    return $this->batchId;
  }
}