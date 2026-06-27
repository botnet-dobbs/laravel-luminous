<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Controllers;

use Botnetdobbs\Luminous\Attributes\ApiIgnore;
use Illuminate\Http\JsonResponse;

#[ApiIgnore]
class IgnoredController
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}
