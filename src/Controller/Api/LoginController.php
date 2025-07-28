<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class LoginController extends AbstractController
{
  #[Route('/api/v1/login', name: 'api_login', methods: ['POST'])]
  public function login(#[CurrentUser] ?User $user, Request $request): JsonResponse
  {
  }

  #[Route('/api/v1/user/profile', name: 'api_user_profile', methods: ['GET'])]
  public function profile(#[CurrentUser] ?User $user): JsonResponse
  {
    if (null === $user) {
      return $this->json([
        'success' => false,
        'message' => 'Authentication required'
      ], Response::HTTP_UNAUTHORIZED);
    }

    return $this->json([
      'success' => true,
      'data' => [
        'id' => $user->getId(),
        'username' => $user->getUsername(),
        'email' => $user->getEmail(),
        'roles' => $user->getRoles(),
        'is_active' => $user->isActive(),
        'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        'last_login' => $user->getLastLoginAt()?->format('Y-m-d H:i:s')
      ]
    ]);
  }
}