<?php

namespace com\servandserv\bot\domain\service;

class FuzzyString {

    // Стеммер Портера
    // https://gist.github.com/eigenein/5418094https://gist.github.com/eigenein/5418094
    const MINLENGTH = 3; //минимальна длина слова

    /**
     * https://habrahabr.ru/post/115394/
     */
    protected $dict = []; // словарь
    protected $tdict = []; // транслитерированный словарь
    protected $possible = []; // список возможных вариантов слов
    protected $result = [];
    protected $meta_result = [];

    public function __construct(array $dict = NULL) {
        if ($dict)
            $this->prepare($dict);
    }

    public function prepare(array $dict) {
        $this->dict = $dict;
        $this->tdict = $this->possible = $this->result = $this->meta_result = [];
        foreach ($dict as $word) {
            $this->tdict[strtolower($word)] = $this->encode(strtolower($word));
        }

        return $this;
    }

    public function search($string) {
        $found = $selected = [];
        $share = 0;
        $selected = $this->parse($string);
        foreach ($selected as $word) {
            $res = $this->correct($word);
            if ($res) {
                $found[] = $res;
            }
        }
        if (!empty($selected))
            $share = count($found) / count($selected);
        $res = ["words" => $selected, "found" => $found, "share" => $share];
        return $res;
    }

    public function correct($word) {
        $tword = $this->encode(strtolower($word));
        /**
         * запускаем цикл, который будет выбирать из массива те слова,
         * расстояние Левенштейна между «метафонами» которых не будет превышать половину
         * «метафона» введенного слова (грубо говоря, допускается до половины неправильно
         * написанных согласных букв), потом, среди выбранных вариантов, снова проверяем расстояние,
         * но по всему слову, а не по его «метафону» и подошедшие слова записываем в массив
         */
        foreach ($this->tdict as $n => $k) {
            if (levenshtein(metaphone($tword), metaphone($k)) < mb_strlen(metaphone($tword)) / 2) {
                if (levenshtein($tword, $k) < mb_strlen($tword) / 2) {
                    $this->possible[$n] = $k;
                }
            }
        }
        /**
         * зададим переменные, где расстояние Левенштейна будет равно заведомо большому числу, а «similar text» — заведомо малое число.
         */
        $similarity = 0;
        $meta_similarity = 0;
        $min_levenshtein = 1000;
        $meta_min_levenshtein = 1000;

        //Считаем минимальное расстояние Левенштейна
        foreach ($this->possible as $n) {
            $min_levenshtein = min($min_levenshtein, levenshtein($n, $tword));
        }

        // ищем максимальное значение «подобности» для тех слов, в которых расстояние Левенштейна будет минимальным
        foreach ($this->possible as $n) {
            if (levenshtein($n, $tword) == $min_levenshtein) {
                $similarity = max($similarity, similar_text($n, $tword));
                //echo $n, "\t", $tword, "\t", $similarity, "\n";
            }
        }

        // запускаем цикл, который выберет все слова с наименьшим расстоянием Левенштейна и
        // наибольшим значением «подобности» одновременно
        foreach ($this->possible as $n => $k) {
            echo $n, "\t", $k, "\n";
            if (levenshtein($k, $tword) <= $min_levenshtein) {
                if (similar_text($k, $tword) >= $similarity) {
                    $this->result[$n] = $k;
                }
            }
        }

        // определяем максимальное значение «подобности» между «метафонами» нашего слова и слов в массиве,
        // и минимальное расстояние Левенштейна
        foreach ($this->result as $n) {
            $meta_min_levenshtein = min($meta_min_levenshtein, levenshtein(metaphone($n), metaphone($tword)));
        }

        foreach ($this->result as $n) {
            if (levenshtein($k, $tword) == $meta_min_levenshtein) {
                $meta_similarity = max($meta_similarity, similar_text(metaphone($n), metaphone($tword)));
            }
        }

        // получаем окончательный массив, который, в идеале, должен содержать одно слово
        foreach ($this->result as $n => $k) {
            if (levenshtein(metaphone($k), metaphone($tword)) <= $meta_min_levenshtein) {
                if (similar_text(metaphone($k), metaphone($tword)) >= $meta_similarity) {
                    $this->meta_result[$n] = $k;
                }
            }
        }

        return key($this->meta_result);
    }

    public function parse($input_string, $min_length = self::MINLENGTH) {
        $res = [];
        $words = explode(" ", $input_string);
        foreach ($words as $word) {
            if (strlen($word) > $min_length) {
                $res[] = $word;
            }
        }

        return $res;
    }

    public function encode($str) {
        $tr = array(
            "А" => "A", "Б" => "B", "В" => "V", "Г" => "G",
            "Д" => "D", "Е" => "E", "Ё" => "YO", "Ж" => "J", "З" => "Z", "И" => "I",
            "Й" => "Y", "К" => "K", "Л" => "L", "М" => "M", "Н" => "N",
            "О" => "O", "П" => "P", "Р" => "R", "С" => "S", "Т" => "T",
            "У" => "U", "Ф" => "F", "Х" => "H", "Ц" => "TS", "Ч" => "CH",
            "Ш" => "SH", "Щ" => "SCH", "Ъ" => "", "Ы" => "YI", "Ь" => "",
            "Э" => "E", "Ю" => "YU", "Я" => "YA", "а" => "a", "б" => "b",
            "в" => "v", "г" => "g", "д" => "d", "е" => "e", "ё" => "yo", "ж" => "j",
            "з" => "z", "и" => "i", "й" => "y", "к" => "k", "л" => "l",
            "м" => "m", "н" => "n", "о" => "o", "п" => "p", "р" => "r",
            "с" => "s", "т" => "t", "у" => "u", "ф" => "f", "х" => "h",
            "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch", "ъ" => "y",
            "ы" => "yi", "ь" => "'", "э" => "e", "ю" => "yu", "я" => "ya"
        );

        return strtr($str, $tr);
    }

    public function getMetaResult() {
        return $this->meta_result;
    }

    public function getResult() {
        return $this->result;
    }

}
