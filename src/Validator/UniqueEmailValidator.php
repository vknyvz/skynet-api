<?php

namespace App\Validator;

use App\Repository\LeadRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueEmailValidator extends ConstraintValidator
{
  public function __construct(
    private LeadRepository $leadRepository
  ) {}

  public function validate(mixed $value, Constraint $constraint): void
  {
    if (!$constraint instanceof UniqueEmail) {
      throw new UnexpectedTypeException($constraint, UniqueEmail::class);
    }

    // if no email provided then it would fail actually on my dto validator
    if (null === $value || !isset($value->email) || empty($value->email)) {
      return;
    }

    $existingLead = $this->leadRepository->findOneBy(['email' => $value->email]);

    if ($existingLead) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('{{ email }}', $value->email)
        ->addViolation();
    }
  }
}