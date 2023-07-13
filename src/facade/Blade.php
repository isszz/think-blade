<?php
declare (strict_types = 1);

namespace think\blade\facade;

use think\Facade;
use think\App;

class Blade extends Facade
{
    protected static function getFacadeClass()
    {
        return 'blade.compiler';
    }
}