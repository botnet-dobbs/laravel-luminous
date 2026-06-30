<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Controllers;

use Botnetdobbs\Luminous\Attributes\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrderController
{
    #[ApiResponse(200, description: 'OK')]
    public function show(int $orderId): JsonResponse
    {
        return new JsonResponse([]);
    }

    #[ApiResponse(200, description: 'OK')]
    public function item(int $orderId, string $itemId): JsonResponse
    {
        return new JsonResponse([]);
    }
}
