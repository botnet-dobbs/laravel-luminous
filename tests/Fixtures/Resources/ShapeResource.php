<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Resources;

use Botnetdobbs\Luminous\Attributes\ApiShape;
use Botnetdobbs\Luminous\Support\Shape;
use Botnetdobbs\Luminous\Tests\Fixtures\Enums\PaymentStatus;
use Illuminate\Http\Resources\Json\JsonResource;

#[ApiShape]
class ShapeResource extends JsonResource
{
    public static function schema(): Shape
    {
        return Shape::object([
            'id' => Shape::uuid()->readOnly(),
            'status' => Shape::enum(PaymentStatus::class)->readOnly(),
            'amount' => Shape::integer()->min(1)->readOnly(),
            'name' => Shape::string()->nullable()->optional()->description('Optional display name'),
            'payment' => Shape::ref(PaymentResource::class)->optional(),
        ]);
    }
}
