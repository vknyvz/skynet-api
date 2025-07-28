<?php

namespace App\MessageHandler;

use App\Message\LogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class LogMessageHandler
{
  public function __construct(
    private LoggerInterface $apiLogger
  ) {}

  public function __invoke(LogMessage $message): void
  {
    try {
      $logData = $message->toArray();

      $this->apiLogger->info(json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    } catch (\Throwable $e) {
      error_log(sprintf(
        'Failed to process log message for request ID %s: %s',
        $message->getThreadKey(),
        $e->getMessage()
      ));
    }
  }
}