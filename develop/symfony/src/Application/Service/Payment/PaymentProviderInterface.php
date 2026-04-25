<?php
declare(strict_types=1);

namespace App\Application\Service\Payment;

use App\Domain\User\Entity\Payment;
use App\Domain\User\ValueObject\PaymentGateway;
use Symfony\Component\HttpFoundation\Request;

/**
 * Abstraction over a payment gateway adapter.
 * Each gateway (PayPal, Stripe, …) implements this interface in Infrastructure.
 */
interface PaymentProviderInterface
{
    /** Returns true if this provider handles the given gateway. */
    public function supports(PaymentGateway $gateway): bool;

    /**
     * Creates a hosted checkout session/order in the payment gateway.
     *
     * @param Payment $payment The pending Payment entity (contains amount, currency, plan).
     * @param string $returnUrl URL the gateway redirects the user to after success.
     * @param string $cancelUrl URL the gateway redirects the user to after cancellation.
     *
     * @return PaymentCheckoutResult External order ID + approval URL for browser redirect.
     */
    public function createCheckoutSession(
        Payment $payment,
        string $returnUrl,
        string $cancelUrl,
    ): PaymentCheckoutResult;

    /**
     * Captures / confirms a previously approved payment order.
     *
     * Called from the return URL handler after the user approves the payment.
     *
     * @param string $externalId External order/session ID returned by createCheckoutSession.
     * @return PaymentWebhookResult Normalised result with final status and metadata.
     */
    public function captureOrder(string $externalId): PaymentWebhookResult;

    /**
     * Verifies and parses an inbound webhook request from the gateway.
     *
     * Throws \RuntimeException if the signature/payload is invalid.
     *
     * @return PaymentWebhookResult Normalised event data.
     */
    public function handleWebhook(Request $request): PaymentWebhookResult;
}
