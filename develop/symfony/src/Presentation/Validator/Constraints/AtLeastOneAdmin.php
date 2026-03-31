<?php
declare(strict_types=1);

namespace App\Presentation\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AtLeastOneAdmin extends Constraint
{
    public string $message = 'Cannot remove the last administrator.';
}
