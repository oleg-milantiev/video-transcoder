<?php
declare(strict_types=1);

namespace App\Infrastructure\Payment\PayPal;

use App\Application\Service\Payment\PaymentCheckoutResult;
use App\Application\Service\Payment\PaymentProviderInterface;
use App\Application\Service\Payment\PaymentWebhookResult;
use App\Domain\User\Entity\Payment;
use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentMethod;
use App\Domain\User\ValueObject\PaymentStatus;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PayPal Orders v2 payment provider.
 *
 * Uses PayPal REST API (no external SDK — only symfony/http-client).
 *
 * Environment variables required:
 *   PAYPAL_CLIENT_ID
 *   PAYPAL_CLIENT_SECRET
 *   PAYPAL_MODE           (sandbox | live, default: sandbox)
 *   PAYPAL_WEBHOOK_ID     (PayPal webhook ID for signature verification)
 */
final readonly class PayPalPaymentProvider implements PaymentProviderInterface
{
    private const string SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
    private const string LIVE_URL = 'https://api-m.paypal.com';

    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(PAYPAL_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(PAYPAL_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%env(PAYPAL_WEBHOOK_ID)%')]
        private string $webhookId,
        #[Autowire('%env(PAYPAL_MODE)%')]
        private string $mode,
    ) {
        $this->baseUrl = ($this->mode === 'live') ? self::LIVE_URL : self::SANDBOX_URL;
    }

    public function supports(PaymentGateway $gateway): bool
    {
        return $gateway === PaymentGateway::PAYPAL;
    }

    public function createCheckoutSession(
        Payment $payment,
        string $returnUrl,
        string $cancelUrl,
    ): PaymentCheckoutResult {
        $accessToken = $this->getAccessToken();

        $amountValue = number_format($payment->amount()->toMajorUnits(), 2, '.', '');
        $currency = $payment->currency()->value();
        $description = $payment->planSnapshot()->value();

        $response = $this->httpClient->request('POST', $this->baseUrl.'/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'description' => $description,
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => $amountValue,
                        ],
                        'custom_id' => $payment->id()?->toRfc4122() ?? '',
                    ],
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'landing_page' => 'LOGIN',
                            'user_action' => 'PAY_NOW',
                            'return_url' => $returnUrl,
                            'cancel_url' => $cancelUrl,
                        ],
                    ],
                ],
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->toArray(false);

        if ($statusCode !== 201) {
            throw new RuntimeException(
                sprintf(
                    'PayPal create order failed with status %d: %s',
                    $statusCode,
                    json_encode($body),
                )
            );
        }

        $orderId = $body['id'] ?? throw new RuntimeException('PayPal response missing order ID.');
        $approvalUrl = $this->extractApprovalUrl($body['links'] ?? []);

        return new PaymentCheckoutResult(
            externalId: $orderId,
            approvalUrl: $approvalUrl,
        );
    }

    private function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl.'/v1/oauth2/token', [
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => 'grant_type=client_credentials',
        ]);

        $body = $response->toArray();

        return $body['access_token'] ?? throw new RuntimeException('PayPal access token not found in response.');
    }

    /**
     * @param list<array{rel: string, href: string}> $links
     */
    private function extractApprovalUrl(array $links): string
    {
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'payer-action') {
                return $link['href'];
            }
        }

        throw new RuntimeException('PayPal response missing payer-action approval URL.');
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    public function captureOrder(string $externalId): PaymentWebhookResult
    {
        $accessToken = $this->getAccessToken();

        $response = $this->httpClient->request(
            'POST',
            $this->baseUrl.'/v2/checkout/orders/'.$externalId.'/capture',
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        $statusCode = $response->getStatusCode();
        $body = $response->toArray(false);

        if ($statusCode !== 201 && $statusCode !== 200) {
            throw new RuntimeException(
                sprintf(
                    'PayPal capture order %s failed with status %d: %s',
                    $externalId,
                    $statusCode,
                    json_encode($body),
                )
            );
        }

        $orderStatus = strtoupper($body['status'] ?? '');
        $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
        $captureId = $capture['id'] ?? $externalId;

        $status = match ($orderStatus) {
            'COMPLETED' => PaymentStatus::completed(),
            default => PaymentStatus::failed(),
        };

        $payerEmail = $body['payer']['email_address'] ?? null;
        $method = $payerEmail !== null
            ? new PaymentMethod('paypal_account')
            : null;

        return new PaymentWebhookResult(
            externalId: $captureId,
            status: $status,
            paymentMethod: $method,
            meta: [
                'paypal_capture_id' => $captureId,
                'paypal_order_id' => $externalId,
                'paypal_order_status' => $orderStatus,
                'payer_email' => $payerEmail,
            ],
        );
    }

    public function handleWebhook(Request $request): PaymentWebhookResult
    {
        $this->verifyWebhookSignature($request);

        $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $event = $body['event_type'] ?? '';

        return match (true) {
            str_starts_with($event, 'PAYMENT.CAPTURE.COMPLETED')
                => $this->buildCaptureCompletedResult($body),
            str_starts_with($event, 'PAYMENT.CAPTURE.DENIED')
            || str_starts_with($event, 'PAYMENT.CAPTURE.DECLINED')
                => $this->buildCaptureDeniedResult($body),
            str_starts_with($event, 'PAYMENT.CAPTURE.REFUNDED')
                => $this->buildCaptureRefundedResult($body),
            default
                => $this->buildUnknownEventResult($body),
        };
    }

    private function verifyWebhookSignature(Request $request): void
    {
        $accessToken = $this->getAccessToken();

        $payload = [
            'auth_algo' => $request->headers->get('Paypal-Auth-Algo', ''),
            'cert_url' => $request->headers->get('Paypal-Cert-Url', ''),
            'transmission_id' => $request->headers->get('Paypal-Transmission-Id', ''),
            'transmission_sig' => $request->headers->get('Paypal-Transmission-Sig', ''),
            'transmission_time' => $request->headers->get('Paypal-Transmission-Time', ''),
            'webhook_id' => $this->webhookId,
            'webhook_event' => json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR),
        ];

        $response = $this->httpClient->request(
            'POST',
            $this->baseUrl.'/v1/notifications/verify-webhook-signature',
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ],
        );

        $body = $response->toArray();

        if (($body['verification_status'] ?? '') !== 'SUCCESS') {
            throw new RuntimeException('PayPal webhook signature verification failed.');
        }
    }

    private function buildCaptureCompletedResult(array $body): PaymentWebhookResult
    {
        $resource = $body['resource'] ?? [];
        $captureId = $resource['id'] ?? '';

        return new PaymentWebhookResult(
            externalId: $captureId,
            status: PaymentStatus::completed(),
            paymentMethod: new PaymentMethod('paypal_account'),
            meta: [
                'paypal_event_type' => $body['event_type'] ?? '',
                'paypal_capture_id' => $captureId,
                'paypal_event_id' => $body['id'] ?? '',
            ],
        );
    }

    private function buildCaptureDeniedResult(array $body): PaymentWebhookResult
    {
        $resource = $body['resource'] ?? [];
        $captureId = $resource['id'] ?? '';

        return new PaymentWebhookResult(
            externalId: $captureId,
            status: PaymentStatus::failed(),
            meta: [
                'paypal_event_type' => $body['event_type'] ?? '',
                'paypal_capture_id' => $captureId,
                'paypal_event_id' => $body['id'] ?? '',
            ],
        );
    }

    private function buildCaptureRefundedResult(array $body): PaymentWebhookResult
    {
        $resource = $body['resource'] ?? [];
        $captureId = $resource['links'][0]['href'] ?? ($resource['id'] ?? '');

        // The refund resource links back to the original capture via 'up' rel
        foreach (($resource['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'up') {
                $parts = explode('/', rtrim($link['href'], '/'));
                $captureId = end($parts);
                break;
            }
        }

        return new PaymentWebhookResult(
            externalId: $captureId,
            status: PaymentStatus::refunded(),
            meta: [
                'paypal_event_type' => $body['event_type'] ?? '',
                'paypal_refund_id' => $resource['id'] ?? '',
                'paypal_event_id' => $body['id'] ?? '',
            ],
        );
    }

    private function buildUnknownEventResult(array $body): PaymentWebhookResult
    {
        return new PaymentWebhookResult(
            externalId: '',
            status: PaymentStatus::pending(),
            meta: [
                'paypal_event_type' => $body['event_type'] ?? 'unknown',
                'paypal_event_id' => $body['id'] ?? '',
            ],
        );
    }
}
