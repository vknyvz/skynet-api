<?php

namespace App\Message;

readonly class ProcessLeadMessage
{
  public function __construct(
    private array $leadData,
    private mixed $batchId = null
  ) {}

  public function getLeadData(): array
  {
    return $this->leadData;
  }

  public function getBatchId(): mixed
  {
    return $this->batchId;
  }
}