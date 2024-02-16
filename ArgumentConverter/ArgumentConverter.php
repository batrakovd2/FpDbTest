<?php

namespace FpDbTest\ArgumentConverter;

class ArgumentConverter {
    protected function convertNull() {
        return 'NULL';
    }

    protected function convertString(string $arg) {
        return "'" . $arg . "'";
    }

    protected function convertIdentificator(string $arg) {
        return "`" . $arg . "`";
    }
}
