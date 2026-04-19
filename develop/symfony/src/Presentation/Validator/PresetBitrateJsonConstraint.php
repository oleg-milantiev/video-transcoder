<?php
declare(strict_types=1);

namespace App\Presentation\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PresetBitrateJsonConstraint extends Constraint
{
    public string $message = 'Invalid bitrate JSON.';

    public function validatedBy(): string
    {
        return PresetBitrateJsonValidator::class;
    }
}
