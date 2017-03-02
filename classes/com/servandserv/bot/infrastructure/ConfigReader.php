<?php

namespace com\servandserv\bot\infrastructure;

/**
 *
 * пример:
 * ConfigReader::read("web.xml", "context.xml");
 *
 */
class ConfigReader 
{

    const unixHome = "HOME";
    const contextDir = ".config";
    const contextFile = "context.xml";

    /**
     * загрузить значения из настроек в окружение скрипта
     *
     * @param string $envFile дескриптор приложения (файл с реестром всех настроек)
     * @param string $contextFile необязательный контекстный файл (например экземпляра приложения)
     * @return boolean - true если загружено из кеша
     */
    public static function load($envFile, $contextFile = null) 
    {
        //требуется проверить на пустые значения
        $nullcheck = array();

        //ищем пользовательский файл контекста
        $userCtxFile = null;
        if (array_key_exists(self::unixHome, $_SERVER)) { //unix
            $testFile = $_SERVER[self::unixHome] . DIRECTORY_SEPARATOR . self::contextDir . DIRECTORY_SEPARATOR . self::contextFile;
            if (file_exists($testFile)) {
                $userCtxFile = $testFile;
            }
        }

        //читаем дефолты и глобали
        $xr = new \XMLReader();
        if(!$xr->open($envFile)) {
            trigger_error( "web xml file \"$envFile\" not found" );
            exit;
        }
        while ($xr->nodeType != \XMLReader::ELEMENT) {
            $xr->read();
        }
        if ($xr->name != "web-app") {
            trigger_error("no <web-app> element in '" . $envFile . "'");
        }
        while ($xr->read()) {
            if ($xr->nodeType == \XMLReader::ELEMENT) {
                $name = $type = $value = null;
                if ($xr->name == "env-entry") {
                    while ($xr->read()) {
                        if ($xr->nodeType == \XMLReader::ELEMENT) {
                            if ($xr->name == "env-entry-name") {
                                $name = $xr->readString();
                            } else if ($xr->name == "env-entry-type") {
                                $type = $xr->readString();
                            } else if ($xr->name == "env-entry-value") {
                                $value = $xr->readString();
                            }
                        } else if ($xr->nodeType == \XMLReader::END_ELEMENT && $xr->name == "env-entry") {
                            //var_dump("def: " . $name . " " . $type . " " . $value);
                            $cginame = self::getCGIname($name);
                            if ($value) {
                                //конфиг приложения перебивает окружение и глобали
                                $_SERVER[$name] = self::convertType($type, $value);
                            } else if ($name != $cginame && array_key_exists($cginame, $_SERVER)) {
                                //есть пхп-вая переменная с "похожим" CGI-наименованием - считаем что "оно"
                                $value = $_SERVER[$cginame];
                                $_SERVER[$name] = self::convertType($type, $value);
                                //сбрасываем значение с CGI-транслитерированным наименованием, чтоб не дублировался и не путаться
                                self::unsetValue($cginame);
                            } else if (array_key_exists($name, $_SERVER)) {
                                //есть пхп-вая переменная - берем её и приводим тип
                                $value = $_SERVER[$name];
                                $_SERVER[$name] = self::convertType($type, $value);
                            } else {
                                //используем дефолтное значение только если его еще не установлено в окружении
                                //смотрим в реальное окружение, в $_ENV и $_SERVER имена уже искорёжены
                                $value = getenv($name);
                                // не допускаем пустых значений
                                //if ($value == false) {
                                //    trigger_error("no value for '" . $name . "' declared at " . $envFile . ":" . $xr->expand()->getLineNo());
                                //}
                                $_SERVER[$name] = self::convertType($type, $value);
                                $nullcheck[$name] = $envFile . ":" . $xr->expand()->getLineNo();
                            }
                            //var_dump(__LINE__, $cache);
                            $name = $type = $value = null;
                            break;
                        }
                    }
                }
            }
        }
        $xr->close();

        //читаем из контекста приложения - возможно их и не потребуется заполнять глобалями
        if ($contextFile) {
            $xr = new \XMLReader();
            if(!$xr->open($contextFile)) {
                trigger_error("context file \"$contextFile\" not found");
                exit;
            }
            while ($xr->nodeType != \XMLReader::ELEMENT) {
                $xr->read();
            }
            if ($xr->name != "Context") {
                trigger_error("no <Context> element in '" . $contextFile . "'");
            }
            if($xr->hasAttributes)
            {
                $attributes = array();
                while($xr->moveToNextAttribute())
                {
                    if($xr->name == "path" ) {
                        $_SERVER["config.context"] = $xr->value;
                    }
                }
            }
            
            while ($xr->read()) {
                $name = $type = $value = null;
                if ($xr->nodeType == \XMLReader::ELEMENT && $xr->name == "Environment") {
                    $name = $xr->getAttribute("name");
                    $type = $xr->getAttribute("type");
                    $value = $xr->getAttribute("value");
                    //var_dump("ctx: " . $name . " " . $type . " " . $value);
                    if (!array_key_exists($name, $_SERVER)) {
                        //незадекларированные элементы из контекста не принимаем - "таможня"
                        trigger_error("undeclared environment element '" . $name . "' declared at " . $contextFile . ":" . $xr->expand()->getLineNo());
                    }
                    //контекстное значение перебивает ранее установленные дефолты, глобали, окружение и проч.
                    $_SERVER[$name] = self::convertType($type, $value);
                }
            }
            $xr->close();
        }

        //перебиваем значениями из пользовательского контекста - только существующие!
        if ($userCtxFile && realpath($userCtxFile) != realpath($contextFile)) {
            if (!is_readable($userCtxFile)) { //перестраховка
                trigger_error("cant read file '" . $userCtxFile . "'");
            }
            $xr = new \XMLReader();
            $xr->open($userCtxFile);
            while ($xr->nodeType != \XMLReader::ELEMENT) {
                $xr->read();
            }
            if ($xr->name != "Context") {
                trigger_error("no <Context> element in '" . $userCtxFile . "'");
            }
            while ($xr->read()) {
                $name = $type = $value = null;
                if ($xr->nodeType == \XMLReader::ELEMENT && $xr->name == "Environment") {
                    $name = $xr->getAttribute("name");
                    $type = $xr->getAttribute("type");
                    $value = $xr->getAttribute("value");
                    //var_dump("userctx: " . $name . " " . $type . " " . $value);
                    if (array_key_exists($name, $_SERVER)) {
                        //незадекларированные элементы не принимаем - просто пропускаем - они могут быть "не наши"
                        //контекстное значение перебивает ранее установленные дефолты, глобали, окружение и проч.
                        $_SERVER[$name] = self::convertType($type, $value);
                    }
                }
            }
            $xr->close();
        }

        //проверим чтобы не было пустых
        foreach ($nullcheck as $name => $fileline) {
            if (empty($_SERVER[$name])) {
                self::unsetValue( $name );
                //error_log("no value for '" . $name . "' declared at " . $fileline);
            }
        }

        return false;
    }

    /**
     * приведение типов значений к пхп-ным по наименованию типа (ява "java.lang.boolean" или псевдо-тип "bool" и т.д.)
     *
     * @param string $type тип
     * @param string $value исходное значение
     * @return mixed значение приведенное к типу
     */
    private static function convertType($type, $value) {
        if ($value == null) {
            return $value;
        }
        $type = strtolower($type);
        if (strpos($type, "bool") !== false) {
            $value = strtolower($value);
            if ($value == "true") {
                return true;
            } else if ($value == "false") {
                return false;
            } else {
                trigger_error("not boolean value '" . $value . "'");
            }
        } else if (strpos($type, "int") !== false || strpos($type, "short") !== false || strpos($type, "long") !== false) {
            if (!ctype_digit($value)) {
                trigger_error("not integer value '" . $value . "'");
            }
            return intval($value);
        } else if (strpos($type, "float") !== false || strpos($type, "double") !== false) {
            if (!is_numeric($value)) {
                trigger_error("not numeric value '" . $value . "'");
            }
            return floatval($value);
        }
        //все остальные типы как есть - строкой
        return $value;
    }

    /**
     * привести наименование переменной окружения по правилам CGI
     *
     * @param string $name оригинальное наименование
     * @return string CGi-наименование переменной
     */
    private static function getCGIname($name) {
        return str_replace(array('-', '.'), '_', $name);
    }

    /**
     * удалить значение из _SERVER
     *
     * @param string $name ключ
     */
    private static function unsetValue($name) {
        if (array_key_exists($name, $_SERVER)) {
            unset($_SERVER[$name]);
        }
        if (array_key_exists("REDIRECT_" . $name, $_SERVER)) {
            unset($_SERVER["REDIRECT_" . $name]);
        }
    }
}