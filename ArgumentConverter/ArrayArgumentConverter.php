<?php

namespace FpDbTest\ArgumentConverter;

class ArrayArgumentConverter extends ArgumentConverter implements IArgument {

    private array $argument;

    public function __construct(array $arg) {
        $this->argument = $arg;
    }
    public function convert(): string {
        $is_associate = false;
        foreach($this->argument as $item) {
            if(!is_numeric($item)) {
                $is_associate = true;
                break;
            }
        }
        if($is_associate) {
            $arguments = [];
            foreach ($this->argument as $key => $arg) {
                $convArg = $arg;
                if(is_string($arg)) {
                    $convArg = $this->convertString($arg);
                }
                elseif(is_null($arg)) {
                    $convArg = $this->convertNull();
                }
                $arguments[] = $this->convertIdentificator($key) . " = " . $convArg;
            }
        } else {
            $arguments = array_map(function($arg) {
                if(is_string($arg)) {
                    return $this->convertString($arg);
                }
                elseif(is_null($arg)) {
                    return $this->convertNull();
                }
                return $arg;
            }, $this->argument);
        }

        return implode(', ', $arguments);

    }
}
