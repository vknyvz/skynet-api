<?php

namespace App\EventListener;

use App\Service\ApiLoggingService;
use App\Service\AsyncLoggingService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

class ApiLoggingListener implements EventSubscriberInterface
{
  private array $requestStartTimes = [];
  private array $requestIds = [];

  public function __construct(
    private readonly ApiLoggingService   $apiLoggingService,
    private readonly AsyncLoggingService $asyncLoggingService,
    private readonly LoggerInterface     $logger
  ) {}

  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 10],
      KernelEvents::RESPONSE => ['onKernelResponse', -10],
    ];
  }

  public function onKernelRequest(RequestEvent $event): void
  {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    if (!$this->isApiRoute($request)) {
      return;
    }

    $threadKey = $this->asyncLoggingService->generateRequestId();
    $this->requestStartTimes[$threadKey] = microtime(true);
    $this->requestIds[spl_object_id($request)] = $threadKey;

    $request->attributes->set('_thread_key', $threadKey);

    try {
      $this->apiLoggingService->logRequest($request, $threadKey);
    } catch (\Exception $e) {
      $this->logger->error('Failed to log API request', [
        'thread_key' => $threadKey,
        'error' => $e->getMessage(),
        'uri' => $request->getUri()
      ]);
    }
  }

  public function onKernelResponse(ResponseEvent $event): void
  {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $response = $event->getResponse();

    if (!$this->isApiRoute($request)) {
      return;
    }

    $requestObjectId = spl_object_id($request);
    $requestId = $this->requestIds[$requestObjectId] ?? null;

    if (!$requestId) {
      return;
    }

    $startTime = $this->requestStartTimes[$requestId] ?? null;
    $responseTime = $startTime ? (microtime(true) - $startTime) * 1000 : null;

    try {
      $responseData = null;
      $contentType = $response->headers->get('Content-Type', '');

      if (str_contains($contentType, 'application/json')) {
        $content = $response->getContent();
        if ($content) {
          $responseData = json_decode($content, true);
        }
      }

      $this->apiLoggingService->logResponse(
        $requestId,
        $response->getStatusCode(),
        $responseData ?: ['content' => 'Non-JSON response'],
        $responseTime
      );
    } catch (\Exception $e) {
      $this->logger->error('Failed to log API response', [
        'thread_key' => $requestId,
        'error' => $e->getMessage(),
        'status_code' => $response->getStatusCode()
      ]);
    } finally {
      // Clean up stored data
      unset($this->requestStartTimes[$requestId]);
      unset($this->requestIds[$requestObjectId]);
    }
  }

  private function isApiRoute(Request $request): bool
  {
    $path = $request->getPathInfo();
    return str_starts_with($path, '/api/');
  }
}