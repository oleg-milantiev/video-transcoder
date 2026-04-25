<?php

declare(strict_types=1);

namespace App\Tests\Domain\Entity;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Payment;
use App\Domain\User\ValueObject\PaymentAmount;
use App\Domain\User\ValueObject\PaymentCurrency;
use App\Domain\User\ValueObject\PaymentDates;
use App\Domain\User\ValueObject\PaymentExternalId;
use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentInvoiceUrl;
use App\Domain\User\ValueObject\PaymentMethod;
use App\Domain\User\ValueObject\PaymentPlanSnapshot;
use App\Domain\User\ValueObject\PaymentStatus;
use Faker\Factory;

final class PaymentFake
{
    public static function create(
        ?Uuid $userId = null,
        ?PaymentStatus $status = null,
        ?PaymentGateway $gateway = null,
    ): Payment {
        $faker   = Factory::create();
        $created = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 year', '-1 day'));
        $paid    = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween($created->format(DATE_ATOM), 'now'));
        $until   = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('now', '+2 years'));

        $resolvedStatus  = $status  ?? PaymentStatus::COMPLETED;
        $resolvedGateway = $gateway ?? $faker->randomElement([PaymentGateway::STRIPE, PaymentGateway::PAYPAL]);

        $dates = match ($resolvedStatus) {
            PaymentStatus::COMPLETED, PaymentStatus::REFUNDED => PaymentDates::fromPersistence($created, $paid, $until),
            default => PaymentDates::create($created),
        };

        return Payment::reconstitute(
            userId: $userId ?? Uuid::generate(),
            status: $resolvedStatus,
            currency: new PaymentCurrency($faker->randomElement(['USD', 'EUR', 'RUB'])),
            gateway: $resolvedGateway,
            amount: new PaymentAmount($faker->numberBetween(100, 99900)),
            planSnapshot: new PaymentPlanSnapshot($faker->randomElement(['Basic', 'Pro', 'Business'])),
            dates: $dates,
            externalId: new PaymentExternalId('pi_' . $faker->bothify('??########')),
            paymentMethod: new PaymentMethod($faker->randomElement(['card', 'bank_transfer', 'wallet'])),
            invoiceUrl: new PaymentInvoiceUrl('https://example.com/invoices/' . $faker->uuid()),
            meta: [],
            id: Uuid::generate(),
        );
    }

    public static function pending(?Uuid $userId = null): Payment
    {
        return self::create($userId, PaymentStatus::PENDING);
    }

    public static function completed(?Uuid $userId = null): Payment
    {
        return self::create($userId, PaymentStatus::COMPLETED);
    }
}
