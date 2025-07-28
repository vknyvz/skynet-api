<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class ApiLoggingService
{
  public function __construct(
    private AsyncLoggingService $asyncLoggingService
  ) {}

  public function logRequest(Request $request, string $requestId): string
  {
    $this->asyncLoggingService->logRequest($request, $requestId);

    return $requestId;
  }

  public function logResponse(string $requestId, int $statusCode, array $responseData, ?float $responseTime = null): void
  {
    $response = new Response(
      json_encode($responseData),
      $statusCode,
      ['Content-Type' => 'application/json']
    );

    $this->asyncLoggingService->logResponse(
      $requestId,
      $response,
      $responseData,
      $responseTime
    );
  }

  public function logError(string $requestId, \Throwable $exception, ?array $context = null): void
  {
    $this->asyncLoggingService->logError($requestId, $exception, $context);
  }
}