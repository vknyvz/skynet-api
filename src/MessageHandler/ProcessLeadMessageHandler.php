<?php

namespace App\MessageHandler;

use App\Message\ProcessLeadMessage;
use App\Service\AsyncLoggingService;
use App\Service\LeadProcessingService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class ProcessLeadMessageHandler
{
  public function __construct(
    private LeadProcessingService $leadProcessingService,
    private AsyncLoggingService $asyncLoggingService
  ) {}

  public function __invoke(ProcessLeadMessage $message): void
  {
    $leadDto = $message->getLeadDto();
    $batchId = $message->getBatchId();

    try {
      $this->asyncLoggingService->info('PROCESSING_LEAD_ASYNC', [
        'batch_id' => $batchId,
        'email' => $leadDto->email ?? 'unknown, although it should\'t be',
      ]);

      $lead = $this->leadProcessingService->processLead($leadDto);

      $this->asyncLoggingService->info('PROCESSED_SUCCESSFULLY', [
        'batch_id' => $batchId,
        'lead_id' => $lead->getId(),
        'email' => $lead->getEmail()
      ]);

    } catch (\InvalidArgumentException $e) {
      $this->asyncLoggingService->logError('LEAD_VALIDATION_FAILED', $e, [
        'batch_id' => $batchId,
        'lead_dto_array' => $leadDto->toArray(),
      ]);

      throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);

    } catch (\Exception $e) {
      $this->asyncLoggingService->logError('FAILED_TO_PROCESS', $e, [
        'batch_id' => $batchId,
        'lead_dto_array' => $leadDto->toArray(),
      ]);

      throw $e;
    }
  }
}