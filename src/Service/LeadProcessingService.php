<?php

namespace App\Service;

use App\Entity\Lead;
use App\Entity\LeadData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class LeadProcessingService
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private ValidatorInterface     $validator
  ) {}

  public function processLead(array $data): Lead
  {
    $lead = new Lead();

    $lead->setFirstName(trim($data['firstName']));
    $lead->setLastName(trim($data['lastName']));
    $lead->setEmail(trim($data['email']));

    if (isset($data['phone'])) {
      $lead->setPhone(trim($data['phone']));
    }

    if (isset($data['dateOfBirth'])) {
      $dateOfBirth = new \DateTime($data['dateOfBirth']);
      $lead->setDateOfBirth($dateOfBirth);
    }

    $lead->setStatus($data['status'] ?? 'active');

    $violations = $this->validator->validate($lead);
    if (count($violations) > 0) {
      $errors = [];
      foreach ($violations as $violation) {
        $errors[] = $violation->getMessage();
      }
      throw new \InvalidArgumentException(implode(', ', $errors));
    }

    $this->entityManager->persist($lead);

    $standardFields = ['firstName', 'lastName', 'email', 'phone', 'dateOfBirth', 'company', 'source', 'status'];
    foreach ($data as $key => $value) {
      if (!in_array($key, $standardFields) && $value !== null) {
        $leadData = new LeadData();
        $leadData->setLead($lead);
        $leadData->setFieldName($key);
        $leadData->setFieldValue((string)$value);
        $this->entityManager->persist($leadData);
      }
    }

    $this->entityManager->flush();

    return $lead;
  }
}
