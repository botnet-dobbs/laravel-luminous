<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Resources;

use Botnetdobbs\Luminous\Attributes\ApiProperty;
use Botnetdobbs\Luminous\Tests\Fixtures\Enums\PaymentStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    #[ApiProperty('Payment UUID', format: 'uuid', readOnly: true)]
    public string $id;

    #[ApiProperty('Current payment status', readOnly: true)]
    public PaymentStatus $status;

    #[ApiProperty('Amount in minor units', readOnly: true, example: 10000)]
    public int $amount;

    #[ApiProperty('ISO 4217 currency code', readOnly: true, example: 'USD')]
    public string $currency;

    #[ApiProperty('Creation timestamp', format: 'date-time', readOnly: true)]
    public string $created_at;

    #[ApiProperty('Settlement timestamp', format: 'date-time', nullable: true, optional: true, readOnly: true)]
    public ?string $settled_at;
}
