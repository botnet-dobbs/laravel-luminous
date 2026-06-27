<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Controllers;

use Botnetdobbs\Luminous\Attributes\ApiOperation;
use Illuminate\Http\JsonResponse;

class PlainController
{
    #[ApiOperation('Simple action')]
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}
