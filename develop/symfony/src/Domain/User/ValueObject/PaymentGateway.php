<?php
declare(strict_types=1);

namespace App\Domain\User\ValueObject;

enum PaymentGateway: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';

    public const array NAMES = [
        self::STRIPE->value => self::STRIPE->name,
        self::PAYPAL->value => self::PAYPAL->name,
    ];

    public static function stripe(): self
    {
        return self::STRIPE;
    }

    public static function paypal(): self
    {
        return self::PAYPAL;
    }
}
