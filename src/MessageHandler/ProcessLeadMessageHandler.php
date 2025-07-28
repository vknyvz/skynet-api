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
    $leadData = $message->getLeadData();
    $batchId = $message->getBatchId();

    try {
      $this->asyncLoggingService->info('PROCESSING_LEAD_ASYNC', [
        'batch_id' => $batchId,
        'email' => $leadData['email'] ?? 'unknown'
      ]);

      $lead = $this->leadProcessingService->processLead($leadData);

      $this->asyncLoggingService->info('PROCESSED_SUCCESSFULLY', [
        'batch_id' => $batchId,
        'lead_id' => $lead->getId(),
        'email' => $lead->getEmail()
      ]);

    } catch (\InvalidArgumentException $e) {
      $this->asyncLoggingService->logError('LEAD_VALIDATION_FAILED', $e, [
        'batch_id' => $batchId,
        'lead_data' => $leadData
      ]);

      throw new UnrecoverableMessageHandlingException($e->getMessage(), $e->getCode(), $e);

    } catch (\Exception $e) {
      $this->asyncLoggingService->logError('FAILED_TO_PROCESS', $e, [
        'batch_id' => $batchId,
        'lead_data' => $leadData
      ]);

      throw $e;
    }
  }
}