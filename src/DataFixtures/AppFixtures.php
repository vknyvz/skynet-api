<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
  public function __construct(
    private readonly UserPasswordHasherInterface $passwordHasher
  ) {}

  public function load(ObjectManager $manager): void
  {
    // Create admin user
    $adminUser = new User();
    $adminUser->setUsername('admin');
    $adminUser->setEmail('admin@example.com');
    $adminUser->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
    $adminUser->setIsActive(true);

    $hashedPassword = $this->passwordHasher->hashPassword($adminUser, 'password');
    $adminUser->setPassword($hashedPassword);

    $manager->persist($adminUser);

    // Create regular user
    $user = new User();
    $user->setUsername('user');
    $user->setEmail('user@example.com');
    $user->setRoles(['ROLE_USER']);
    $user->setIsActive(true);

    $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
    $user->setPassword($hashedPassword);

    $manager->persist($user);

    $testUsers = [
      ['username' => 'tester', 'email' => 'tester@example.com', 'password' => 'test123', 'roles' => ['ROLE_USER']],
      ['username' => 'api_user', 'email' => 'api@example.com', 'password' => 'api123', 'roles' => ['ROLE_USER']],
      ['username' => 'load_test', 'email' => 'loadtest@example.com', 'password' => 'load123', 'roles' => ['ROLE_USER']],
    ];

    foreach ($testUsers as $userData) {
      $testUser = new User();
      $testUser->setUsername($userData['username']);
      $testUser->setEmail($userData['email']);
      $testUser->setRoles($userData['roles']);
      $testUser->setIsActive(true);

      $hashedPassword = $this->passwordHasher->hashPassword($testUser, $userData['password']);
      $testUser->setPassword($hashedPassword);

      $manager->persist($testUser);
    }

    $manager->flush();
  }
}
