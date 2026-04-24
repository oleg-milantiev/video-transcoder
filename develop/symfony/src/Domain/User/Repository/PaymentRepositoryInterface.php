<?php
declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Payment;
use App\Domain\User\ValueObject\PaymentExternalId;
use App\Domain\User\ValueObject\PaymentGateway;

interface PaymentRepositoryInterface
{
    public function save(Payment $payment): Payment;

    public function findById(Uuid $id): ?Payment;

    /** @return list<Payment> */
    public function findByUserId(Uuid $userId): array;

    public function findByExternalId(PaymentGateway $gateway, PaymentExternalId $externalId): ?Payment;
}
