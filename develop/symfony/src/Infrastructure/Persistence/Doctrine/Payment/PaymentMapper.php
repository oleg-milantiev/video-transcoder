<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Payment;

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
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;

class PaymentMapper
{
    public static function toDomain(PaymentEntity $entity): Payment
    {
        return Payment::reconstitute(
            userId: Uuid::fromString($entity->user->id->toRfc4122()),
            status: PaymentStatus::from($entity->status),
            currency: new PaymentCurrency($entity->currency),
            gateway: PaymentGateway::from($entity->gateway),
            amount: new PaymentAmount($entity->amount),
            planSnapshot: new PaymentPlanSnapshot($entity->planSnapshot),
            dates: PaymentDates::fromPersistence(
                $entity->createdAt ?? new \DateTimeImmutable(),
                $entity->paidAt,
                $entity->validUntil,
            ),
            externalId: $entity->externalId !== null ? new PaymentExternalId($entity->externalId) : null,
            paymentMethod: $entity->paymentMethod !== null ? new PaymentMethod($entity->paymentMethod) : null,
            invoiceUrl: $entity->invoiceUrl !== null ? new PaymentInvoiceUrl($entity->invoiceUrl) : null,
            meta: $entity->meta,
            id: Uuid::fromString($entity->id->toRfc4122()),
        );
    }

    public static function toDoctrine(Payment $payment, UserEntity $user): PaymentEntity
    {
        $entity = new PaymentEntity();
        if ($payment->id() !== null) {
            $entity->id = SymfonyUuid::fromString($payment->id()->toRfc4122());
        }
        self::hydrate($entity, $payment, $user);
        $entity->createdAt = $payment->createdAt();

        return $entity;
    }

    public static function hydrate(PaymentEntity $entity, Payment $payment, UserEntity $user): void
    {
        $entity->user          = $user;
        $entity->status        = $payment->status()->value;
        $entity->currency      = $payment->currency()->value();
        $entity->gateway       = $payment->gateway()->value;
        $entity->externalId    = $payment->externalId()?->value();
        $entity->paymentMethod = $payment->paymentMethod()?->value();
        $entity->meta          = $payment->meta();
        $entity->planSnapshot  = $payment->planSnapshot()->value();
        $entity->invoiceUrl    = $payment->invoiceUrl()?->value();
        $entity->amount        = $payment->amount()->value();
        $entity->paidAt        = $payment->paidAt();
        $entity->validUntil    = $payment->validUntil();
    }
}
