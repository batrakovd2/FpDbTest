<?php

namespace FpDbTest;

use Exception;
use FpDbTest\ArgumentConverter\ArrayArgumentConverter;
use FpDbTest\ArgumentConverter\CommonArgumentConverter;
use FpDbTest\ArgumentConverter\DigitArgumentConverter;
use FpDbTest\ArgumentConverter\FloatArgumentConverter;
use FpDbTest\ArgumentConverter\IdentificatorArgumentConverter;
use FpDbTest\Enum\SpecifierSymbol;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;
    private string $specifierRegex;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->specifierRegex = $this->getQueryRegex();
    }

    /**
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {

        $countIdentifier = preg_match_all($this->specifierRegex, $query, $querySpecifiers);
        $querySpecifiers = !empty($querySpecifiers[0]) ? $querySpecifiers[0] : [];

        if(count($args) != $countIdentifier) {
            throw new Exception();
        }

        if(empty($querySpecifiers)) {
            return $query;
        } else {
            if(in_array($this->skip(), $args, true)) {
                $query = $this->removeConditionBlocksBySpecSymbol($query, $args);
            }
            $query = str_replace(['{', '}'], "", $query);

            $convertedArguments = [];
            $argumentObjects = $this->getArgumentObjects($querySpecifiers, $args);
            foreach ($argumentObjects as $arOb) {
                $convertedArguments[] = $arOb->convert();
            }
        }

        foreach ($convertedArguments as $key => $convArg) {
            $query = preg_replace("/\\" . $querySpecifiers[$key] . "/", $convArg, $query, 1);
        }

        $query = preg_replace("/\s+/", " ", $query);

        return $query;
    }

    public function skip()
    {
        return "!";
    }

    /**
     * Формируем регулярное выражение
     * '/\?d|\?f|\?a|\?#|\?/iu'
     * с полученными спецификаторами
     */
    private function getQueryRegex(): string {
        $specifiers = Specifier::getSpecifiers();
        $regex = '/';
        foreach($specifiers as $sp) {
            $regex .= "\\?" . $sp . "|";
        }
        $regex .= "\?/iu";

        return $regex;
    }

    /**
     * Формирование массива объектов аргументов по типам спецификаторов
     */
    private function getArgumentObjects($specifier, $args) {
        return array_map(function($sp, $arg) {
            $sp = str_replace('?', '', $sp);

            switch ($sp) {
                case SpecifierSymbol::DIGIT:
                    return new DigitArgumentConverter($arg);
                case SpecifierSymbol::FLOAT:
                    return new FloatArgumentConverter($arg);
                case SpecifierSymbol::ARRAY:
                    return new ArrayArgumentConverter($arg);
                case SpecifierSymbol::IDENTIFICATOR:
                    return new IdentificatorArgumentConverter($arg);
                default:
                    return new CommonArgumentConverter($arg);

            }
        }, $specifier, $args);
    }

    /**
     * @throws Exception
     */
    private function removeConditionBlocksBySpecSymbol(string $query, array &$args): string {
        // получаем индексы аргементов-спец.символов
        $specIndexes = [];
        foreach ($args as $spInd => $arg) {
            if($arg === $this->skip()) {
                $specIndexes[] = $spInd;
            }
        }
        // массив для сбора индексов аргументов,
        // которые нужно удалить вместе с условными блоками
        $deletingArgIndex = $specIndexes;

        //получаем массив строк между фигурными скобками
        $bracketCount = preg_match_all('/\{.+?\}/iu', $query, $brackets);

        if( !empty($bracketCount) ) {

            //получаем массив строк, не входящий, в фигурные скобки
            preg_match_all('/\}.+?\{/iu', $query, $btwBrackets);

            //получаем строку до первого вхождения фигурной скобки
            $startBracketIndex = stripos($query, "{");
            $startStr = substr($query, 0, $startBracketIndex);

            // получаем конец строки после последней скобки
            $lastBracketIndex = strripos($query, "}");
            $lastStr = substr($query, $lastBracketIndex, strlen($query));

            // убераем убертку массивом функции preg_match_all
            $brackets = !empty($brackets[0]) ? $brackets[0] : [];
            $btwBrackets = !empty($btwBrackets[0]) ? $btwBrackets[0] : [];

            $strResult = !empty($startStr) ? $startStr : "";

            $countIdentifier = preg_match_all($this->specifierRegex, $strResult);
            // вычитаем 1 для сравнения с идексами
            $includeSpecifierCounter = $countIdentifier - 1;

            foreach ($brackets as $key => $br) {
                // проверяем вхождение спецификаторов в условные блоки
                $countIdentifier = preg_match_all($this->specifierRegex, $br);

                // если в блоке 1 спецификатор
                if($countIdentifier == 1) {

                    // то прибавляем к счетчику
                    $includeSpecifierCounter += $countIdentifier;

                    // проверяем есть ли среди индексов спец значений.
                    // Если есть, то пропускаем соединение этого условного блока
                    if (in_array($includeSpecifierCounter, $specIndexes)) {
                        // добавляем индекс в массив индексов аргументов на удаление
                        $deletingArgIndex[] = $includeSpecifierCounter;
                    } else {
                        $strResult .= $br;
                    }

                // если в блоке более 1 спецификатора
                }
                elseif($countIdentifier > 1) {

                    // ставим флажок, что блок не пропускаем
                    $isSkipBlock = false;

                    // запоминаем первоначальный индекс
                    $startIndex = $includeSpecifierCounter;

                    // прибавляем количество спецификаторов к счетчику
                    $includeSpecifierCounter += $countIdentifier;

                    // задаем диапазон между первоначальным и получившимся индексом
                    $rangeIndexes = range($startIndex + 1, $includeSpecifierCounter);

                    // значения диапазона
                    foreach ($rangeIndexes as $rngItem) {
                        // если индекс из диапазона есть в массиве индексов спец значений
                        if(in_array($rngItem, $specIndexes)) {
                            //то ставим флажок - пропустить условный блок
                            $isSkipBlock = true;
                        }
                    }
                    // добавляем индексы в массив индексов аргументов на удаление
                    if($isSkipBlock) $deletingArgIndex = array_merge($deletingArgIndex, $rangeIndexes);

                    // пропускаем соединение условного блокв
                    if(!$isSkipBlock)  $strResult .= $br;
                }

                // работаем с блоками текста между фигурными скобками если такие есть
                if ($key <= $bracketCount && !empty($btwBrackets[$key])) {

                    // проверяем наличие спецификаторов в этих блоках
                    $countIdentifier = preg_match_all($this->specifierRegex, $btwBrackets[$key]);
                    // и считаем их
                    $includeSpecifierCounter += $countIdentifier;

                    $strResult .= $btwBrackets[$key];
                }

                // удалякм аргументы, соответствующие удаленным условным блокам
                foreach ($deletingArgIndex as $spi) {
                    unset($args[$spi]);
                }
            }

            // добавляем конец строки
            $strResult .= $lastStr;
        } else {
            //ошибка, если есть спец символ среди аргументов, но нет условных блоков
            throw new Exception();
        }

        return $strResult;
    }
}
