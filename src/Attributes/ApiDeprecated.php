<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class ApiDeprecated
{
    public function __construct(
        public readonly string $reason = '',
        public readonly string $replacement = '',
    ) {}
}
