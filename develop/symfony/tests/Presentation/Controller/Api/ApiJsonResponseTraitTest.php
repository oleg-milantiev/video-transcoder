<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Controller\Api;

use App\Presentation\Controller\Api\ApiJsonResponseTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @covers \App\Presentation\Controller\Api\ApiJsonResponseTrait
 */
class ApiJsonResponseTraitTest extends TestCase
{
    use ApiJsonResponseTrait;

    public function testApiSuccessReturnsJsonResponseWithData(): void
    {
        $data = ['key' => 'value', 'number' => 42];
        $response = $this->apiSuccess($data, 201);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(201, $response->getStatusCode());
        self::assertJson($response->getContent());
        $this->assertJsonStringEqualsJsonString(
            json_encode($data),
            $response->getContent()
        );
    }

    public function testApiSuccessReturnsJsonResponseWithNullData(): void
    {
        $response = $this->apiSuccess(null, 204);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(204, $response->getStatusCode());
        self::assertJson($response->getContent());
        // JsonResponse encodes null as empty object {}, not JSON null
        $this->assertJsonStringEqualsJsonString('{}', $response->getContent());
    }

    public function testApiSuccessDefaultsToStatus200(): void
    {
        $response = $this->apiSuccess(['test' => true]);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testApiErrorReturnsJsonResponseWithErrorStructure(): void
    {
        $code = 'TEST_ERROR';
        $message = 'Test error message';
        $status = 400;
        $details = ['field' => 'value'];

        $response = $this->apiError($code, $message, $status, $details);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame($status, $response->getStatusCode());
        self::assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        self::assertArrayHasKey('error', $data);
        self::assertSame($code, $data['error']['code']);
        self::assertSame($message, $data['error']['message']);
        self::assertSame($details, $data['error']['details']);
    }

    public function testApiErrorDefaultsToEmptyDetailsArray(): void
    {
        $response = $this->apiError('ERROR', 'Message', 500);

        $data = json_decode($response->getContent(), true);
        self::assertSame([], $data['error']['details']);
    }
}