<?php
declare(strict_types=1);

namespace App\Presentation\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PresetBitrateJsonValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PresetBitrateJsonConstraint) {
            throw new UnexpectedTypeException($constraint, PresetBitrateJsonConstraint::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $decoded = json_decode((string) $value, true);

        if (!is_array($decoded)) {
            $this->context->buildViolation('Invalid JSON: must be a JSON object.')
                ->addViolation();
            return;
        }

        foreach ($decoded as $key => $val) {
            $intKey = (int) $key;
            if ((string) $intKey !== (string) $key || $intKey <= 0) {
                $this->context->buildViolation(
                    'Invalid key "{{ key }}": each key must be a positive integer height (e.g. "720").'
                )
                    ->setParameter('{{ key }}', (string) $key)
                    ->addViolation();
                continue;
            }

            if (!is_numeric($val) || (float) $val < 0) {
                $this->context->buildViolation(
                    'Invalid bitrate for height {{ height }}: must be a non-negative number (Mbps).'
                )
                    ->setParameter('{{ height }}', (string) $key)
                    ->addViolation();
            }
        }
    }
}
