<?php

namespace App\Service;

use App\Message\LogMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AsyncLoggingService
{
  private const LOG_NAME_REQUEST = 'API_REQUEST_INGEST';
  private const LOG_NAME_RESPONSE = 'API_REQUEST_RESPOND';

  public function __construct(
    private readonly MessageBusInterface $messageBus,
    private readonly RequestStack $requestStack
  ) {}

  public function generateRequestId(): string
  {
    // Generate SHA1-style unique ID: timestamp-random-hash
    $timestamp = time();
    $random = mt_rand(100000, 999999);
    $hash = substr(sha1(uniqid('', true)), 0, 10);

    return sprintf('%s-%s-%s', $timestamp, $random, $hash);
  }

  public function logRequest(Request $request, string $requestId): void
  {
    $clientIp = $this->getClientIp($request);
    $payload = $this->getRequestPayload($request);
    $headers = $this->getFilteredHeaders($request);

    $data = [
      'method' => $request->getMethod(),
      'endpoint' => $request->getPathInfo(),
      'ip' => $clientIp,
      'headers' => $headers,
      'query_params' => $request->query->all(),
      'payload' => $payload,
      'user_agent' => $request->headers->get('User-Agent', 'Unknown'),
      'content_type' => $request->headers->get('Content-Type', 'Unknown')
    ];

    $logMessage = new LogMessage(
      $requestId,
      self::LOG_NAME_REQUEST,
      $data,
      new \DateTimeImmutable()
    );

    $this->messageBus->dispatch($logMessage, [
      new AmqpStamp('log.write')
    ]);
  }

  public function logResponse(
    string              $requestId,
    Response            $response,
    ?array              $responseData = null,
    ?float              $duration = null,
    ?\DateTimeInterface $startTime = null
  ): void
  {
    $durationMs = null;
    if ($duration !== null) {
      $durationMs = round($duration, 2);
    } elseif ($startTime !== null) {
      $durationMs = round((microtime(true) - $startTime->getTimestamp()) * 1000, 2);
    }

    $payload = $responseData;

    // If no response data provided, try to get it from response content
    if ($payload === null && $response->getContent()) {
      $content = $response->getContent();
      if ($this->isJson($content)) {
        $payload = json_decode($content, true);
      } else {
        $payload = ['content' => $content];
      }
    }

    $data = [
      'status' => $response->getStatusCode(),
      'payload' => $payload,
      'headers' => $this->getFilteredResponseHeaders($response),
      'content_type' => $response->headers->get('Content-Type', 'Unknown')
    ];

    if ($durationMs !== null) {
      $data['duration_ms'] = $durationMs;
    }

    $logMessage = new LogMessage(
      $requestId,
      self::LOG_NAME_RESPONSE,
      $data,
      new \DateTimeImmutable()
    );

    $this->messageBus->dispatch($logMessage, [
      new AmqpStamp('log.write')
    ]);
  }

  public function info(
    string $name,
    ?array $context = null,
  ): void {
    $request = $this->requestStack->getCurrentRequest();
    $threadKey = $request?->attributes->get('_thread_key') ?? $this->generateRequestId();

    $logMessage = new LogMessage(
      $threadKey,
      $name,
      $context,
      new \DateTimeImmutable()
    );

    $this->messageBus->dispatch($logMessage, [
      new AmqpStamp('log.write')
    ]);
  }

  public function logError(
    $name,
    \Throwable $exception,
    ?array     $context = null
  ): void
  {
    $request = $this->requestStack->getCurrentRequest();
    $threadKey = $request?->attributes->get('_thread_key') ?? $this->generateRequestId();

    $data = [
      'error_type' => get_class($exception),
      'error_message' => $exception->getMessage(),
      'error_code' => $exception->getCode(),
      'file' => $exception->getFile(),
      'line' => $exception->getLine(),
      'trace' => $exception->getTraceAsString()
    ];

    if ($context) {
      $data['context'] = $context;
    }

    $logMessage = new LogMessage(
      $threadKey,
      $name ?? 'API_REQUEST_ERROR',
      $data,
      new \DateTimeImmutable()
    );

    $this->messageBus->dispatch($logMessage, [
      new AmqpStamp('log.write')
    ]);
  }

  private function getClientIp(Request $request): string
  {
    // Check for IP from various headers in order of preference
    $ipHeaders = [
      'HTTP_CF_CONNECTING_IP',     // Cloudflare
      'HTTP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_X_CLUSTER_CLIENT_IP',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED',
      'REMOTE_ADDR'
    ];

    foreach ($ipHeaders as $header) {
      $ip = $request->server->get($header);
      if ($ip && !empty($ip) && $ip !== 'unknown') {
        // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
        if (strpos($ip, ',') !== false) {
          $ip = trim(explode(',', $ip)[0]);
        }

        // Validate IP address
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
          return $ip;
        }
      }
    }

    return $request->getClientIp() ?? 'unknown';
  }

  private function getRequestPayload(Request $request): ?array
  {
    $content = $request->getContent();

    if (empty($content)) {
      return null;
    }

    if ($this->isJson($content)) {
      return json_decode($content, true);
    }

    // For form data
    if ($request->request->count() > 0) {
      return $request->request->all();
    }

    return ['raw_content' => $content];
  }

  private function getFilteredHeaders(Request $request): array
  {
    $headers = [];
    $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

    foreach ($request->headers->all() as $name => $values) {
      $lowerName = strtolower($name);

      if (in_array($lowerName, $sensitiveHeaders)) {
        $headers[$name] = '[FILTERED]';
      } else {
        $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
      }
    }

    return $headers;
  }

  private function getFilteredResponseHeaders(Response $response): array
  {
    $headers = [];
    $sensitiveHeaders = ['set-cookie', 'authorization'];

    foreach ($response->headers->all() as $name => $values) {
      $lowerName = strtolower($name);

      if (in_array($lowerName, $sensitiveHeaders)) {
        $headers[$name] = '[FILTERED]';
      } else {
        $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
      }
    }

    return $headers;
  }

  private function isJson(string $content): bool
  {
    if (empty($content)) {
      return false;
    }

    json_decode($content);
    return json_last_error() === JSON_ERROR_NONE;
  }
}