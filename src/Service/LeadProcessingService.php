<?php

namespace App\Service;

use App\DTO\LeadDto;
use App\Entity\Lead;
use App\Entity\LeadData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

readonly class LeadProcessingService
{
  public function __construct(
    private EntityManagerInterface $entityManager,
    private PropertyAccessorInterface $propertyAccessor
  ) {}

  public function processLead(LeadDto $leadDto): Lead
  {
    $lead = new Lead();

    $this->mapBasicProperties($leadDto, $lead);
    $this->processDynamicData($leadDto, $lead);

    $this->entityManager->persist($lead);
    $this->entityManager->flush();

    return $lead;
  }

  public function mapBasicProperties(LeadDto $leadDto, Lead $lead): void
  {
    $propertyMap = [
      'firstName' => 'firstName',
      'lastName' => 'lastName',
      'email' => 'email',
      'phone' => 'phone',
      'dateOfBirth' => 'dateOfBirth',
      'status' => 'status'
    ];

    foreach ($propertyMap as $dtoProperty => $entityProperty) {
      $value = $this->propertyAccessor->getValue($leadDto, $dtoProperty);

      if ($value !== null) {
        if (is_string($value) && $dtoProperty !== 'dateOfBirth') {
          $value = trim($value);
        }

        $this->propertyAccessor->setValue($lead, $entityProperty, $value);
      }
    }

    if ($lead->getStatus() === null) {
      $lead->setStatus('active');
    }
  }

  private function processDynamicData(LeadDto $leadDto, Lead $lead): void
  {
    if ($leadDto->dynamicData === null) {
      return;
    }

    foreach ($leadDto->dynamicData as $dynamicDataDto) {
      if ($dynamicDataDto->fieldValue !== null) {
        $leadData = new LeadData();
        $leadData->setLead($lead);
        $leadData->setFieldName($dynamicDataDto->fieldName);
        $leadData->setFieldValue($this->convertValueToString($dynamicDataDto->fieldValue, $dynamicDataDto->fieldType));
        $leadData->setFieldType($dynamicDataDto->fieldType);

        $this->entityManager->persist($leadData);
      }
    }
  }

  private function convertValueToString(mixed $value, ?string $fieldType): string
  {
    return match ($fieldType) {
      'boolean' => $value ? '1' : '0',
      'json' => json_encode($value),
      'date', 'datetime' => $value instanceof \DateTimeInterface
        ? $value->format($fieldType === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s')
        : (string) $value,
      default => (string) $value
    };
  }
}
