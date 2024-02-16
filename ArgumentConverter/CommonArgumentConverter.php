<?php

namespace FpDbTest\ArgumentConverter;

class CommonArgumentConverter extends ArgumentConverter implements IArgument {

    private $argument;

    public function __construct(string|int|float|bool|NULL $arg) {
        $this->argument = $arg;
    }
    public function convert(): string|int|float {
        if(is_null($this->argument)) {
            return $this->convertNull();
        }
        elseif(is_bool($this->argument)) {
            return (int) $this->argument;
        }
        elseif (is_string($this->argument)) {
            return $this->convertString($this->argument);
        }
        return $this->argument;
    }
}
