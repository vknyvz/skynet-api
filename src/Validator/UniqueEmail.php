<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueEmail extends Constraint
{
  public string $message = 'Lead with email "{{ email }}" already exists.';

  public function getTargets(): string
  {
    return self::CLASS_CONSTRAINT;
  }
}