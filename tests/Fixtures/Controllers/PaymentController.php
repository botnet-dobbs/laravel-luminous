<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Controllers;

use Botnetdobbs\Luminous\Attributes\ApiBody;
use Botnetdobbs\Luminous\Attributes\ApiComposedOf;
use Botnetdobbs\Luminous\Attributes\ApiDeprecated;
use Botnetdobbs\Luminous\Attributes\ApiExample;
use Botnetdobbs\Luminous\Attributes\ApiHeader;
use Botnetdobbs\Luminous\Attributes\ApiIgnore;
use Botnetdobbs\Luminous\Attributes\ApiNoSecurity;
use Botnetdobbs\Luminous\Attributes\ApiOperation;
use Botnetdobbs\Luminous\Attributes\ApiParam;
use Botnetdobbs\Luminous\Attributes\ApiQuery;
use Botnetdobbs\Luminous\Attributes\ApiResponse;
use Botnetdobbs\Luminous\Attributes\ApiSecurity;
use Botnetdobbs\Luminous\Attributes\ApiTag;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\CancelPaymentRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Requests\CreatePaymentRequest;
use Botnetdobbs\Luminous\Tests\Fixtures\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiTag('Payments', 'Payment lifecycle: create, retrieve, cancel')]
#[ApiSecurity('bearerAuth')]
class PaymentController
{
    #[ApiOperation('List payments', 'Returns cursor-paginated list of payments')]
    #[ApiQuery('status', 'Filter by status', enum: ['initiated', 'processing', 'succeeded', 'failed', 'timeout_pending'])]
    #[ApiQuery('limit', 'Results per page', 'integer', example: 20)]
    #[ApiQuery('cursor', 'Opaque pagination cursor')]
    #[ApiResponse(200, PaymentResource::class, 'Payments list', paginated: true)]
    #[ApiResponse(401, description: 'Unauthenticated', ref: '#/components/schemas/ErrorResponse')]
    public function index(Request $request): JsonResponse
    {
        return response()->json([]);
    }

    #[ApiOperation('Create a payment')]
    #[ApiHeader('Idempotency-Key', 'UUID v4. Same key returns cached response.', required: true, format: 'uuid')]
    #[ApiBody(CreatePaymentRequest::class)]
    #[ApiResponse(201, PaymentResource::class, 'Payment created')]
    #[ApiResponse(409, description: 'Idempotency conflict', ref: '#/components/schemas/ErrorResponse')]
    #[ApiResponse(422, description: 'Validation error', ref: '#/components/schemas/ErrorResponse')]
    #[ApiExample('usd-payment', 'USD payment example', ['amount' => 10000, 'currency' => 'USD'])]
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        return response()->json([], 201);
    }

    #[ApiOperation('Get a payment')]
    #[ApiParam('id', 'Payment UUID', 'string', 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')]
    #[ApiResponse(200, PaymentResource::class, 'Payment found')]
    #[ApiResponse(404, description: 'Not found', ref: '#/components/schemas/ErrorResponse')]
    public function show(string $id): JsonResponse
    {
        return response()->json([]);
    }

    // No #[ApiBody]. cancel() tests auto-detection of the last FormRequest parameter
    #[ApiOperation('Cancel a payment')]
    #[ApiParam('id', 'Payment UUID', 'string', 'uuid')]
    #[ApiResponse(200, PaymentResource::class, 'Payment cancelled')]
    #[ApiResponse(422, description: 'Invalid state transition', ref: '#/components/schemas/ErrorResponse')]
    public function cancel(string $id, CancelPaymentRequest $request): JsonResponse
    {
        return response()->json([]);
    }

    // Tests #[ApiComposedOf] with forStatus. Schema applied to the named response status
    #[ApiOperation('Merge payment types')]
    #[ApiResponse(200, description: 'Merged type')]
    #[ApiComposedOf('oneOf', ['#/components/schemas/CardOption', '#/components/schemas/BankOption'], forStatus: 200)]
    public function merge(Request $request): JsonResponse
    {
        return response()->json([]);
    }

    // Tests #[ApiComposedOf] without forStatus. Fallback applies to first description-only response
    #[ApiOperation('Payment type options')]
    #[ApiResponse(200, description: 'Available payment types')]
    #[ApiComposedOf('anyOf', ['#/components/schemas/CardOption', '#/components/schemas/BankOption'])]
    public function paymentOptions(): JsonResponse
    {
        return response()->json([]);
    }

    #[ApiIgnore]
    public function internalDump(): JsonResponse
    {
        return response()->json([]);
    }

    #[ApiDeprecated('Superseded by POST /v2/payments', 'POST /v2/payments')]
    #[ApiOperation('Create payment (v1 legacy)')]
    public function legacyStore(Request $request): JsonResponse
    {
        return response()->json([], 201);
    }

    #[ApiNoSecurity]
    #[ApiOperation('Public payment status check')]
    #[ApiParam('id', 'Payment UUID', 'string', 'uuid')]
    #[ApiResponse(200, description: 'Status retrieved')]
    public function publicStatus(string $id): JsonResponse
    {
        return response()->json([]);
    }
}
