<?php

namespace FpDbTest\ArgumentConverter;

class FloatArgumentConverter extends ArgumentConverter implements IArgument {

    private $argument;

    public function __construct(int|float|bool|NULL $arg) {
        $this->argument = $arg;
    }
    public function convert(): float|string {
        if(is_null($this->argument)) {
            return $this->convertNull();
        }
        return (float) $this->argument;
    }
}
