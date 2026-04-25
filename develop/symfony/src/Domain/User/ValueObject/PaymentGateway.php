<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

enum PaymentGateway: string
{
    case STRIPE = 'stripe';
    case YOOKASSA = 'yookassa';

    public const array NAMES = [
        self::STRIPE->value => self::STRIPE->name,
        self::YOOKASSA->value => self::YOOKASSA->name,
    ];

    public static function stripe(): self
    {
        return self::STRIPE;
    }

    public static function yookassa(): self
    {
        return self::YOOKASSA;
    }
}
