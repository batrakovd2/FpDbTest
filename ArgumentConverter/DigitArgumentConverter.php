<?php

namespace FpDbTest\ArgumentConverter;

class DigitArgumentConverter extends ArgumentConverter implements IArgument {

    private $argument;

    public function __construct(int|float|bool|null $arg) {
        $this->argument = $arg;
    }

    public function convert(): int|string {
        if(is_null($this->argument)) {
            return $this->convertNull();
        }
        return (int) $this->argument;
    }
}
