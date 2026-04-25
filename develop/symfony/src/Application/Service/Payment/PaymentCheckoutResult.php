<?php
declare(strict_types=1);

namespace App\Application\Service\Payment;

/**
 * Result returned by PaymentProviderInterface::createCheckoutSession().
 * Contains the external order/session ID and the URL to redirect the user to.
 */
final readonly class PaymentCheckoutResult
{
    public function __construct(
        /** External order/session ID assigned by the payment gateway (e.g. PayPal order ID). */
        public string $externalId,
        /** URL to redirect the user to the payment gateway's hosted checkout page. */
        public string $approvalUrl,
    ) {
    }
}
