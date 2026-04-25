<?php
declare(strict_types=1);

namespace App\Application\Service\Payment;

use App\Domain\User\ValueObject\PaymentExternalId;
use App\Domain\User\ValueObject\PaymentMethod;
use App\Domain\User\ValueObject\PaymentStatus;

/**
 * Normalised result produced by PaymentProviderInterface::handleWebhook().
 * Represents a single payment event received from the gateway.
 */
final readonly class PaymentWebhookResult
{
    public function __construct(
        /** Internal Payment entity ID or external order/capture ID. */
        public string $externalId,
        /** The new status that should be applied to the Payment entity. */
        public PaymentStatus $status,
        /** Payment method string (e.g. "card", "paypal_account"), if available. */
        public ?PaymentMethod $paymentMethod = null,
        /** Raw gateway payload attached to Payment.meta for audit purposes. */
        public array $meta = [],
    ) {
    }

    public function toExternalId(): PaymentExternalId
    {
        return new PaymentExternalId($this->externalId);
    }
}
