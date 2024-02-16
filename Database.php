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

    private $querySpecifiers = [];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->specifierRegex = $this->findSpecifierInQueryRegex();
    }

    /**
     * Конвертирование строки запроса шаблона
     * в готовую строку запроса
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {

        $countSpecifierInQuery = $this->findSpecifierInQuery($query);

        if(count($args) != $countSpecifierInQuery) {
            throw new Exception("The number of arguments does not match the number of specifiers");
        }

        if(empty($this->querySpecifiers)) {
            return $query;
        } else {

            if(in_array($this->skip(), $args, true)) {
                $query = $this->removeConditionBlocksBySpecSymbol($query, $args);
            }

            $query = $this->removeAllBracketsFromQuery($query);

            $convertedArguments = [];
            $argumentObjects = $this->getArgumentConvertObjects($this->querySpecifiers, $args);
            foreach ($argumentObjects as $arOb) {
                $convertedArguments[] = $arOb->convert();
            }
        }

        $query = $this->replaceSpecifiersOnConvertedArgument($query, $convertedArguments);

        $query = $this->removeAllDoubleSpaceSymbols($query);

        return $query;
    }

    /**
     * Спец значение для пропуска условного блока
     */
    public function skip()
    {
        return "!";
    }

    /**
     * Формирование регулярного выражения
     * '/\?d|\?f|\?a|\?#|\?/iu'
     * с полученными спецификаторами
     */
    private function findSpecifierInQueryRegex(): string {
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
    private function getArgumentConvertObjects($specifier, $args) {
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
     * Проверка и удаление условных блоков
     * Вывод измененной (или нет) строки запроса
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
            throw new Exception("Missing conditional block for argument with special character");
        }

        return $strResult;
    }

    /**
     * Находим спецификаторы в строке запроса
     * присваивает найденые спецификаторы в querySpecifiers
     * выводит количество спецификаторов в строке
     * @param $query
     * @return false|int
     */
    private function findSpecifierInQuery($query) {
        $countSpecifierInQuery = preg_match_all($this->specifierRegex, $query, $this->querySpecifiers);
        $this->querySpecifiers = !empty($this->querySpecifiers[0]) ? $this->querySpecifiers[0] : [];
        return $countSpecifierInQuery;
    }

    /**
     * Удаление фигурных скобок из строки запроса
     */
    private function removeAllBracketsFromQuery($query) {
        return str_replace(['{', '}'], "", $query);
    }

    /**
     * Замена спецификаторов на конвертированные аргументы в строке запроса
     */
    private function replaceSpecifiersOnConvertedArgument($query, $convertedArguments) {
        foreach ($convertedArguments as $key => $convArg) {
            $query = preg_replace("/\\" . $this->querySpecifiers[$key] . "/", $convArg, $query, 1);
        }
        return $query;
    }

    /**
     * Удаление дублирующихся пробельных символов
     */
    private function removeAllDoubleSpaceSymbols($query) {
        return preg_replace("/\s+/", " ", $query);
    }
}
