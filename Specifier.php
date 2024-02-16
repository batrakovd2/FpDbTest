<?php

namespace FpDbTest;

use FpDbTest\Enum\SpecifierSymbol;
use FpDbTest\Enum\SpecifierSymbol as SpecifierType;
use ReflectionClass;

class Specifier{
    public static function getSpecifiers(): array {
        $specEnum = new SpecifierSymbol();
        $oClass = new ReflectionClass(get_class($specEnum));
        return $oClass->getConstants();
    }
}
