<?php
declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Logging\LogServiceInterface;
use App\Application\Service\Payment\PaymentProviderInterface;
use App\Domain\Shared\ValueObject\Uuid;
use App\Domain\User\Entity\Payment;
use App\Domain\User\Exception\UserNotFound;
use App\Domain\User\Repository\PaymentRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\PaymentAmount;
use App\Domain\User\ValueObject\PaymentCurrency;
use App\Domain\User\ValueObject\PaymentExternalId;
use App\Domain\User\ValueObject\PaymentGateway;
use App\Domain\User\ValueObject\PaymentPlanSnapshot;
use App\Infrastructure\Persistence\Doctrine\User\UserEntity;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Handles payment flows.
 * Currently supports PayPal Orders v2 (checkout → capture → webhook).
 *
 * Routes:
 *   GET  /payment/paypal/checkout   — Start PayPal checkout session
 *   GET  /payment/paypal/return     — PayPal redirects user here after approval
 *   GET  /payment/paypal/cancel     — PayPal redirects user here on cancellation
 *   POST /payment/paypal/webhook    — PayPal server-side event notifications
 */
#[Route('/payment/paypal')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LogServiceInterface $logService,
        #[Autowire('%env(PAYPAL_AMOUNT_CENTS)%')]
        private readonly int $amountCents,
        #[Autowire('%env(PAYPAL_CURRENCY)%')]
        private readonly string $currency,
        #[Autowire('%env(PAYPAL_PLAN_TITLE)%')]
        private readonly string $planTitle,
    ) {
    }

    /**
     * Initiates a PayPal checkout session.
     * Creates a pending Payment entity, then redirects the user to PayPal.
     */
    #[Route('/checkout', name: 'payment_paypal_checkout', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function checkout(): Response
    {
        /** @var UserEntity $userEntity */
        $userEntity = $this->getUser();
        $userId = Uuid::fromString($userEntity->id->toRfc4122());

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw UserNotFound::byId($userId->toRfc4122());
        }

        $payment = Payment::create(
            userId: $userId,
            gateway: PaymentGateway::paypal(),
            currency: new PaymentCurrency($this->currency),
            amount: new PaymentAmount($this->amountCents),
            planSnapshot: new PaymentPlanSnapshot(
                $user->tariff()?->title()->value() ?? $this->planTitle
            ),
        );
        $payment = $this->paymentRepository->save($payment);

        $returnUrl = $this->generateUrl('payment_paypal_return', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('payment_paypal_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $result = $this->paymentProvider->createCheckoutSession($payment, $returnUrl, $cancelUrl);
        } catch (Throwable $e) {
            $this->logService->log(
                'payment',
                'checkout',
                $payment->id(),
                LogLevel::ERROR,
                'PayPal order creation failed',
                [
                    'error' => $e->getMessage(),
                    'paymentId' => $payment->id()?->toRfc4122(),
                ]
            );
            $this->addFlash('danger', 'Payment initiation failed. Please try again.');

            return $this->redirectToRoute('tariffs');
        }

        // Persist the external order ID so we can look it up on return
        $payment->updateExternalId(new PaymentExternalId($result->externalId));
        $this->paymentRepository->save($payment);

        $this->logService->log(
            'payment',
            'checkout',
            $payment->id(),
            LogLevel::INFO,
            'PayPal checkout session created',
            [
                'paymentId' => $payment->id()?->toRfc4122(),
                'externalId' => $result->externalId,
            ]
        );

        return $this->redirect($result->approvalUrl);
    }

    /**
     * User returns here after approving the payment on PayPal.
     * Captures the order and marks the Payment entity as completed.
     */
    #[Route('/return', name: 'payment_paypal_return', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function return(Request $request): Response
    {
        $token = $request->query->get('token'); // PayPal order ID
        if (empty($token)) {
            $this->addFlash('danger', 'Invalid payment return request.');

            return $this->redirectToRoute('tariffs');
        }

        $payment = $this->paymentRepository->findByExternalId(
            PaymentGateway::paypal(),
            new PaymentExternalId($token),
        );

        if ($payment === null) {
            $this->logService->log('payment', 'return', null, LogLevel::WARNING, 'Payment not found for PayPal order', [
                'token' => $token,
            ]);
            $this->addFlash('warning', 'Payment not found.');

            return $this->redirectToRoute('tariffs');
        }

        try {
            $captureResult = $this->paymentProvider->captureOrder($token);
        } catch (Throwable $e) {
            $this->logService->log('payment', 'capture', $payment->id(), LogLevel::ERROR, 'PayPal capture failed', [
                'error' => $e->getMessage(),
                'token' => $token,
                'paymentId' => $payment->id()?->toRfc4122(),
            ]);
            $payment->fail(['capture_error' => $e->getMessage()]);
            $this->paymentRepository->save($payment);
            $this->addFlash('danger', 'Payment capture failed. Please contact support.');

            return $this->redirectToRoute('tariffs');
        }

        if ($captureResult->status->isCompleted()) {
            $payment->complete(
                externalId: $captureResult->toExternalId(),
                paymentMethod: $captureResult->paymentMethod,
                validUntil: new \DateTimeImmutable('+1 month'),
                meta: $captureResult->meta,
            );
            $this->paymentRepository->save($payment);

            $this->logService->log('payment', 'completed', $payment->id(), LogLevel::INFO, 'PayPal payment completed', [
                'paymentId' => $payment->id()?->toRfc4122(),
                'externalId' => $captureResult->externalId,
            ]);
            $this->addFlash('success', 'Payment successful! Your Premium plan is now active.');
        } else {
            $payment->fail($captureResult->meta);
            $this->paymentRepository->save($payment);

            $this->addFlash('warning', 'Payment was not completed. Please try again.');
        }

        return $this->redirectToRoute('tariffs');
    }

    /**
     * User cancelled the payment on PayPal.
     */
    #[Route('/cancel', name: 'payment_paypal_cancel', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function cancel(Request $request): Response
    {
        $token = $request->query->get('token');

        if (!empty($token)) {
            $payment = $this->paymentRepository->findByExternalId(
                PaymentGateway::paypal(),
                new PaymentExternalId($token),
            );

            if ($payment !== null && $payment->status()->isPending()) {
                $payment->cancel();
                $this->paymentRepository->save($payment);
            }
        }

        $this->addFlash('info', 'Payment was cancelled.');

        return $this->redirectToRoute('tariffs');
    }

    /**
     * Receives server-side webhook events from PayPal.
     * Verifies signature before processing any event.
     */
    #[Route('/webhook', name: 'payment_paypal_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        try {
            $result = $this->paymentProvider->handleWebhook($request);
        } catch (RuntimeException $e) {
            $this->logService->log('payment', 'webhook', null, LogLevel::WARNING, 'PayPal webhook rejected', [
                'error' => $e->getMessage(),
            ]);

            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        } catch (Throwable $e) {
            $this->logService->log('payment', 'webhook', null, LogLevel::ERROR, 'PayPal webhook processing error', [
                'error' => $e->getMessage(),
            ]);

            return new Response('Internal Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Skip unknown / no-op events
        if (empty($result->externalId)) {
            return new Response('OK', Response::HTTP_OK);
        }

        $payment = $this->paymentRepository->findByExternalId(
            PaymentGateway::paypal(),
            $result->toExternalId(),
        );

        if ($payment === null) {
            // This can happen for events about orders we haven't captured yet;
            // log at debug level and return 200 so PayPal doesn't retry.
            $this->logService->log('payment', 'webhook', null, LogLevel::DEBUG, 'PayPal webhook: payment not found', [
                'externalId' => $result->externalId,
            ]);

            return new Response('OK', Response::HTTP_OK);
        }

        try {
            if ($result->status->isCompleted() && $payment->status()->isPending()) {
                $payment->complete(
                    externalId: $result->toExternalId(),
                    paymentMethod: $result->paymentMethod,
                    validUntil: new \DateTimeImmutable('+1 month'),
                    meta: $result->meta,
                );
            } elseif ($result->status->isFailed() && $payment->status()->isPending()) {
                $payment->fail($result->meta);
            } elseif ($result->status->isRefunded() && $payment->status()->isCompleted()) {
                $payment->refund($result->meta);
            } else {
                $payment->updateMeta($result->meta);
            }

            $this->paymentRepository->save($payment);
        } catch (Throwable $e) {
            $this->logService->log(
                'payment',
                'webhook',
                $payment->id(),
                LogLevel::ERROR,
                'PayPal webhook state transition failed',
                [
                    'error' => $e->getMessage(),
                    'status' => $result->status->value,
                ]
            );

            return new Response('Internal Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->logService->log('payment', 'webhook', $payment->id(), LogLevel::INFO, 'PayPal webhook processed', [
            'externalId' => $result->externalId,
            'status' => $result->status->value,
        ]);

        return $this->redirectToRoute('tariffs');
    }

    // ── Private helpers ──────────────────────────────────────────────────────
}
