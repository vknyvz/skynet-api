<?php

namespace App\EventSubscriber;

use App\Repository\UserRepository;
use App\Service\AsyncLoggingService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuthenticationSuccessListener implements EventSubscriberInterface
{
  private UserRepository $userRepository;
  private AsyncLoggingService $asyncLoggingService;

  public function __construct(
    UserRepository $userRepository,
    AsyncLoggingService $asyncLoggingService
  )
  {
    $this->userRepository = $userRepository;
    $this->asyncLoggingService = $asyncLoggingService;
  }

  /**
   * @param AuthenticationSuccessEvent $event
   */
  public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
  {
    $data = $event->getData();
    $user = $event->getUser();

    if (!$user instanceof \App\Entity\User) {
      $this->asyncLoggingService->info('LOGIN_FAILED_NO_USER');
      return;
    }

    $this->userRepository->updateLastLogin($user);

    $token = $data['token'];
    unset($data['token']);

    $data['success'] = true;
    $data['message'] = 'Login was successful!';
    $data['user'] = [
      'id' => $user->getId(),
      'username' => $user->getUsername(),
      'email' => $user->getEmail(),
      'roles' => $user->getRoles(),
      'last_login' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
      'token' => $token,
    ];

    $this->asyncLoggingService->info('SUCCESSFULL_USER_LOGIN', $data);

    $event->setData($data);
  }

  public static function getSubscribedEvents(): array
  {
    return [
      'lexik_jwt_authentication.on_authentication_success' => 'onAuthenticationSuccess',
    ];
  }
}