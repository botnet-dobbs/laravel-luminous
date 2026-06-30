<?php

namespace Botnetdobbs\Luminous\Tests\Fixtures\Resources;

use Botnetdobbs\Luminous\Attributes\ApiProperty;
use Illuminate\Http\Resources\Json\JsonResource;

class TreeNodeResource extends JsonResource
{
    #[ApiProperty('Node label')]
    public string $label;

    // No #[ApiProperty]. Picked up by the secondary JsonResource loop in ResourceExtractor.
    // Used by the cycle-guard test: TreeNodeResource references itself.
    public ?TreeNodeResource $parent;

    // No #[ApiProperty]. Non-nullable. Used to verify secondary loop adds property to required[].
    public PaymentResource $metadata;

    // Has #[ApiProperty] AND is typed as a JsonResource subclass.
    // Used by the annotation-wins test: annotation ref must not be overwritten.
    #[ApiProperty('Explicit schema reference', ref: '#/components/schemas/CustomSchema')]
    public PaymentResource $annotated;
}
