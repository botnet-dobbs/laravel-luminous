<?php

namespace Botnetdobbs\Luminous;

use Illuminate\Support\Facades\Facade;

class LuminousFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'luminous';
    }
}
