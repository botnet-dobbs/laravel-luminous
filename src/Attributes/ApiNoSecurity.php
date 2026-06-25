<?php

namespace Botnetdobbs\Luminous\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class ApiNoSecurity
{
    public function __construct() {}
}
