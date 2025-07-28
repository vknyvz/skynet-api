<?php

namespace App\Message;

readonly class LogMessage
{
  public function __construct(
    private string             $threadKey,
    private string             $name,
    private array              $data,
    private \DateTimeInterface $timestamp
  ) {}

  public function getThreadKey(): string
  {
    return $this->threadKey;
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function getData(): array
  {
    return $this->data;
  }

  public function getTimestamp(): \DateTimeInterface
  {
    return $this->timestamp;
  }

  public function toArray(): array
  {
    return [
      'thread_key' => $this->threadKey,
      'name' => $this->name,
      'timestamp' => $this->timestamp->format('Y-m-d\TH:i:s.v\Z'),
      ...$this->data
    ];
  }
}