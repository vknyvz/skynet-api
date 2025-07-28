<?php

namespace App\Service;

use App\Message\ProcessLeadMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class AsyncLeadService
{
  public function __construct(
    private MessageBusInterface $messageBus,
    private AsyncLoggingService $asyncLoggingService
  ) {}

  public function processLeadAsync(array $leadData, mixed $batchId = null): void
  {
    try {
      $message = new ProcessLeadMessage($leadData, $batchId);
      $this->messageBus->dispatch($message, [
        new AmqpStamp('lead.process')
      ]);

      $this->asyncLoggingService->info('LEAD_QUEUED_FOR_PROCESSING', [
        'batch_id' => $batchId,
        'email' => $leadData['email'] ?? 'unknown'
      ]);
    } catch (\Exception $e) {
      $this->asyncLoggingService->logError('FAILED_TO_QUEUE_LEAD_FOR_PROCESSING', $e, [
        'lead_data' => $leadData
      ]);

      throw $e;
    }
  }

  public function processLeadsInChunks(array $leadsData, int $chunkSize = 100): array
  {
    $chunks = array_chunk($leadsData, $chunkSize);
    $batchIds = [];

    foreach ($chunks as $index => $chunk) {
      $batchId = time() . '_' . $index;

      foreach ($chunk as $leadData) {
        $flattenedData = $this->flattenLeadData($leadData);
        $this->processLeadAsync($flattenedData, $batchId);
      }

      $batchIds[] = $batchId;
    }

    $this->asyncLoggingService->info('LEADS_QUEUED_IN_CHUNKS', [
      'total_leads' => count($leadsData),
      'total_chunks' => count($chunks),
      'chunk_size' => $chunkSize,
      'batch_ids' => $batchIds
    ]);

    return [
      'total_leads' => count($leadsData),
      'total_chunks' => count($chunks),
      'batch_ids' => $batchIds,
      'message' => 'All leads queued for processing'
    ];
  }

  /**
   * Flatten lead data structure from bulk format to standard format
   */
  private function flattenLeadData(array $leadData): array
  {
    // Handle bulk format: { "email": "...", "fields": { "firstName": "..." } }
    if (isset($leadData['fields']) && is_array($leadData['fields'])) {
      $flattened = $leadData['fields'];
      $flattened['email'] = $leadData['email'];
      return $flattened;
    }

    // Already in standard format: { "firstName": "...", "email": "..." }
    return $leadData;
  }
}