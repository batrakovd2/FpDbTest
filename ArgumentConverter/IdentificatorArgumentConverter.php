<?php

namespace FpDbTest\ArgumentConverter;

class IdentificatorArgumentConverter extends ArgumentConverter implements IArgument {

    private $argument;

    public function __construct(string|array|null $arg) {
        $this->argument = $arg;
    }
    public function convert(): string {
        if(is_array($this->argument)) {
            $args = array_map(function ($arg) {
                return $this->convertIdentificator($arg);
            }, $this->argument);
            return implode(', ', $args);
        } else {
            return $this->convertIdentificator($this->argument);
        }
    }
}
