<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
  public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
  {
    if ($request->isXmlHttpRequest() ||
      $request->attributes->get('_route') && str_starts_with($request->attributes->get('_route'), 'api_')) {

      $data = [
        'status' => 'error',
        'code' => Response::HTTP_FORBIDDEN,
        'message' => $accessDeniedException->getMessage(),
      ];

      return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    return null;
  }
}