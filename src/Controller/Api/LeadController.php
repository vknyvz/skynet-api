<?php

namespace App\Controller\Api;

use App\Repository\LeadRepository;
use App\Service\LeadProcessingService;
use App\Service\AsyncLeadService;
use App\Service\AsyncLoggingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class LeadController extends AbstractController
{
  public function __construct(
    private readonly LeadRepository         $leadRepository,
    private readonly SerializerInterface    $serializer,
    private readonly LeadProcessingService  $leadProcessingService,
    private readonly AsyncLeadService       $asyncLeadService,
    private readonly AsyncLoggingService    $asyncLoggingService
  ) {}

  #[Route('/api/v1/leads', methods: ['GET'])]
  public function index(Request $request): JsonResponse
  {
    try {
      $page = max(1, (int)$request->query->get('page', 1));
      $limit = min(100, max(1, (int)$request->query->get('limit', 20)));
      $status = $request->query->get('status');
      $email = $request->query->get('email');
      $search = $request->query->get('search');

      $leads = $this->leadRepository->findWithFilters($page, $limit, $status, $email, $search);
      $total = $this->leadRepository->countWithFilters($status, $email, $search);

      $serializedLeads = $this->serializer->serialize($leads, 'json', ['groups' => ['lead:read', 'lead:read:detailed']]);

      $response = [
        'success' => true,
        'data' => json_decode($serializedLeads, true),
        'pagination' => [
          'page' => $page,
          'limit' => $limit,
          'total' => $total,
          'pages' => ceil($total / $limit)
        ]
      ];

      return new JsonResponse($response, Response::HTTP_OK);

    } catch (\Exception $e) {
      $this->asyncLoggingService->logError($request->attributes->get('_thread_key'), $e, [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return $this->json([
        'success' => false,
        'message' => 'Error fetching leads',
        'error' => $e->getMessage()
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/api/v1/leads/{id}', methods: ['GET'])]
  public function show(int $id, Request $request): JsonResponse
  {
    try {
      $lead = $this->leadRepository->show($id);

      if (!$lead) {
        return $this->json([
          'success' => false,
          'message' => 'Lead not found'
        ], Response::HTTP_NOT_FOUND);
      }

      $serializedLead = $this->serializer->serialize($lead, 'json', ['groups' => ['lead:read', 'lead:read:detailed']]);

      return new JsonResponse([
        'success' => true,
        'data' => json_decode($serializedLead, true)
      ], Response::HTTP_OK);

    } catch (\Exception $e) {
      $this->asyncLoggingService->logError($request->attributes->get('_thread_key'), $e, [
        'lead_id' => $id,
        'error' => $e->getMessage()
      ]);

      return $this->json([
        'success' => false,
        'message' => 'Error fetching lead'
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/api/v1/lead/process', methods: ['POST'])]
  public function process(Request $request): JsonResponse
  {
    $requestId = $request->attributes->get('_thread_key') ?? $this->asyncLoggingService->generateRequestId();

    try {
      $data = json_decode($request->getContent(), true);

      if (!$data) {
        return $this->json([
          'success' => false,
          'message' => 'Invalid JSON payload',
          'thread_key' => $requestId
        ], Response::HTTP_BAD_REQUEST);
      }

      $requiredFields = ['firstName', 'lastName', 'email'];
      $missingFields = [];

      foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
          $missingFields[] = $field;
        }
      }

      if (!empty($missingFields)) {
        return $this->json([
          'success' => false,
          'message' => 'Missing required fields',
          'missing_fields' => $missingFields,
          'thread_key' => $requestId
        ], Response::HTTP_BAD_REQUEST);
      }

      $existingLead = $this->leadRepository->findOneBy(['email' => $data['email']]);
      if ($existingLead) {
        return $this->json([
          'success' => false,
          'message' => 'Lead with this email already exists',
          'existing_lead_id' => $existingLead->getId(),
          'thread_key' => $requestId
        ], Response::HTTP_CONFLICT);
      }

      $lead = $this->leadProcessingService->processLead($data);

      $response = [
        'success' => true,
        'message' => 'Lead processed successfully',
        'data' => [
          'id' => $lead->getId(),
          'firstName' => $lead->getFirstName(),
          'lastName' => $lead->getLastName(),
          'email' => $lead->getEmail(),
          'phone' => $lead->getPhone(),
          'status' => $lead->getStatus(),
          'createdAt' => $lead->getCreatedAt()->format('Y-m-d H:i:s')
        ],
        'thread_key' => $requestId,
      ];

      return $this->json($response, Response::HTTP_CREATED);

    } catch (\InvalidArgumentException $e) {
      return $this->json([
        'success' => false,
        'message' => 'Validation error',
        'errors' => $e->getMessage(),
        'thread_key' => $requestId
      ], Response::HTTP_BAD_REQUEST);

    } catch (\Exception $e) {
      $this->asyncLoggingService->logError($requestId, $e, [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'data' => $data ?? null
      ]);

      return $this->json([
        'success' => false,
        'message' => 'Internal server error occurred while processing lead',
        'thread_key' => $requestId
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/api/v1/lead/process-async', methods: ['POST'])]
  public function processAsync(Request $request): JsonResponse
  {
    $requestId = $request->attributes->get('_thread_key') ??
      $this->asyncLoggingService->generateRequestId();

    try {
      $data = json_decode($request->getContent(), true);

      if (!$data) {
        return $this->json([
          'success' => false,
          'message' => 'Invalid JSON payload',
          'thread_key' => $requestId
        ], Response::HTTP_BAD_REQUEST);
      }

      $requiredFields = ['firstName', 'lastName', 'email'];
      $missingFields = [];

      foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
          $missingFields[] = $field;
        }
      }

      if (!empty($missingFields)) {
        return $this->json([
          'success' => false,
          'message' => 'Missing required fields',
          'missing_fields' => $missingFields,
          'thread_key' => $requestId
        ], Response::HTTP_BAD_REQUEST);
      }

      $this->asyncLeadService->processLeadAsync($data);

      $response = [
        'success' => true,
        'message' => 'Lead queued for processing',
        'status' => 'queued',
        'thread_key' => $requestId,
      ];

      return $this->json($response, Response::HTTP_ACCEPTED);

    } catch (\Exception $e) {
      $this->asyncLoggingService->logError($requestId, $e, [
        'thread_key' => $requestId,
        'message' => 'Error queueing lead for async processing',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'data' => $data ?? null
      ]);

      return $this->json([
        'success' => false,
        'message' => 'Error queueing lead for processing',
        'thread_key' => $requestId
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  #[Route('/api/v1/leads/process-bulk', methods: ['POST'])]
  public function processBulk(Request $request): JsonResponse
  {
    $requestId = $request->attributes->get('_thread_key') ?? $this->asyncLoggingService->generateRequestId();

    try {
      $data = json_decode($request->getContent(), true);

      if (!$data || !isset($data['leads']) || !is_array($data['leads'])) {
        return $this->json([
          'success' => false,
          'message' => 'Invalid payload. Expected JSON with "leads" array.',
          'thread_key' => $requestId
        ], Response::HTTP_BAD_REQUEST);
      }

      $leads = $data['leads'];
      $batchSize = $data['batch_size'] ?? 50;

      if (empty($leads)) {
        return $this->json([
          'success' => false,
          'message' => 'No leads provided',
          'thread_key' => $requestId
        ], Response::HTTP_BAD_REQUEST);
      }

      $result = $this->asyncLeadService->processLeadsInChunks($leads, min($batchSize, 100));

      $response = [
        'success' => true,
        'message' => 'Bulk leads queued for processing',
        'status' => 'queued',
        'details' => $result,
        'thread_key' => $requestId,
      ];

      return $this->json($response, Response::HTTP_ACCEPTED);

    } catch (\Exception $e) {
      $this->asyncLoggingService->logError($requestId, $e, [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return $this->json([
        'success' => false,
        'message' => 'Error queueing bulk leads for processing',
        'thread_key' => $requestId
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}