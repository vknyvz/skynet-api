<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
  #[Route('/api/v1/health', methods: ['GET'])]
  public function health(): JsonResponse
  {
    return $this->json([
      'status' => 'OK',
      'timestamp' => date('Y-m-d H:i:s'),
      'message' => 'SkynetAPI is running'
    ]);
  }
}