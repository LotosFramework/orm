<?php

namespace Lotos\ORM;

class Utils {

    static $plural = [
        '/(quiz)$/i'               => "$1zes",
        '/^(ox)$/i'                => "$1en",
        '/([m|l])ouse$/i'          => "$1ice",
        '/(matr|vert|ind)ix|ex$/i' => "$1ices",
        '/(x|ch|ss|sh)$/i'         => "$1es",
        '/([^aeiouy]|qu)y$/i'      => "$1ies",
        '/(hive)$/i'               => "$1s",
        '/(?:([^f])fe|([lr])f)$/i' => "$1$2ves",
        '/(shea|lea|loa|thie)f$/i' => "$1ves",
        '/sis$/i'                  => "ses",
        '/([ti])um$/i'             => "$1a",
        '/(tomat|potat|ech|her|vet)o$/i'=> "$1oes",
        '/(bu)s$/i'                => "$1ses",
        '/(alias)$/i'              => "$1es",
        '/(octop)us$/i'            => "$1i",
        '/(ax|test)is$/i'          => "$1es",
        '/(us)$/i'                 => "$1es",
        '/s$/i'                    => "s",
        '/$/'                      => "s"
    ];

    static $singular = [
        '/(quiz)zes$/i'             => "$1",
        '/(matr)ices$/i'            => "$1ix",
        '/(vert|ind)ices$/i'        => "$1ex",
        '/^(ox)en$/i'               => "$1",
        '/(alias)es$/i'             => "$1",
        '/(octop|vir)i$/i'          => "$1us",
        '/(cris|ax|test)es$/i'      => "$1is",
        '/(shoe)s$/i'               => "$1",
        '/(o)es$/i'                 => "$1",
        '/(bus)es$/i'               => "$1",
        '/([m|l])ice$/i'            => "$1ouse",
        '/(x|ch|ss|sh)es$/i'        => "$1",
        '/(m)ovies$/i'              => "$1ovie",
        '/(s)eries$/i'              => "$1eries",
        '/([^aeiouy]|qu)ies$/i'     => "$1y",
        '/([lr])ves$/i'             => "$1f",
        '/(tive)s$/i'               => "$1",
        '/(hive)s$/i'               => "$1",
        '/(li|wi|kni)ves$/i'        => "$1fe",
        '/(shea|loa|lea|thie)ves$/i'=> "$1f",
        '/(^analy)ses$/i'           => "$1sis",
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'  => "$1$2sis",
        '/([ti])a$/i'               => "$1um",
        '/(n)ews$/i'                => "$1ews",
        '/(h|bl)ouses$/i'           => "$1ouse",
        '/(corpse)s$/i'             => "$1",
        '/(us)es$/i'                => "$1",
        '/s$/i'                     => ""
    ];

    static $irregular = [
        'move'   => 'moves',
        'foot'   => 'feet',
        'goose'  => 'geese',
        'sex'    => 'sexes',
        'child'  => 'children',
        'man'    => 'men',
        'tooth'  => 'teeth',
        'person' => 'people',
        'valve'  => 'valves'
    ];

    static $uncountable = [
        'sheep',
        'fish',
        'deer',
        'series',
        'species',
        'money',
        'rice',
        'information',
        'equipment'
    ];

    public static function pluralize($string) {
        if(in_array(strtolower($string), self::$uncountable)) {
            return $string;
        }
        foreach (self::$irregular as $pattern => $result) {
            $pattern = '/' . $pattern . '$/i';
            if(preg_match($pattern, $string)) {
                return preg_replace($pattern, $result, $string);
            }
        }

        foreach (self::$plural as $pattern => $result) {
            if(preg_match($pattern, $string)) {
                return preg_replace($pattern, $result, $string);
            }
        }
        return $string;
    }

    public static function singularize($string) {
        if(in_array(strtolower($string), self::$uncountable)) {
            return $string;
        }

        foreach ( self::$irregular as $result => $pattern ) {
            $pattern = '/' . $pattern . '$/i';
            if(preg_match($pattern, $string)) {
               return preg_replace($pattern, $result, $string);
            }
        }

        foreach (self::$singular as $pattern => $result) {
            if (preg_match($pattern, $string)) {
               return preg_replace($pattern, $result, $string);
            }
          }
        return $string;
    }

    public static function pluralize_if($count, $string) {
        if ($count == 1) {
          return "1 $string";
        }
        return $count . " " . self::pluralize($string);
    }

    public static function arrayToString($arguments) {
        if(is_array($arguments) && count($arguments) == 1) {
            return self::arrayToString($arguments[0]);
        }
        return $arguments;
    }

    public static function getCellName(string $methodName):string {
        $arr = explode('\\', $methodName);
        $methodName = end($arr);
        preg_match_all('/((?:^|[A-Z])[a-z]+)/',$methodName, $matches);
        return strtolower(implode('_', $matches[0]));
    }

    public static function getTableName(string $modelName):string {
        return self::toPlural(self::getCellName($modelName));
    }

    public static function toPlural(string $name):string {
        $arr = explode('_', $name);
        array_push($arr, self::pluralize(array_pop($arr)));
        return implode('_', $arr);
    }

    public static function toCellName(string $cell):string {
        return '`'.$cell.'`';
    }

    public static function toCellValue(string $value):string {
        return "'" . $value . "'";
    }

    public static function toCellValuesArray(array $values):array {
        foreach($values as $k => $value) {
            $values[$k] = "'" . $value . "'";
        }
        return $values;
    }

    public static function toCellNamesArray(array $cells):array {
        foreach($names as $k => $name) {
            $names[$k] = '`' . $name . '`';
        }
        return $names;
    }

    public static function convertPropertyToCell(string $propertyName) : string
    {
        preg_match_all('/((?:^|[A-Z])[a-z]+)/', $propertyName, $matches);
        return strtolower(implode('_',$matches[0]));
    }
}
