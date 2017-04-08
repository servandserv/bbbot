<?php

namespace com\servandserv\bot\domain\service;

use \com\servandserv\data\bot\Dict;

class FuzzyMean
{

    /**
     * https://habrahabr.ru/post/115394/
     */
    
    public function correct( array $dict , $word )
    {
        $possible = $result = $meta_result = [];
        $tword = $this->encode( strtolower( $word ) );
        /**
         * запускаем цикл, который будет выбирать из массива те слова, 
         * расстояние Левенштейна между «метафонами» которых не будет превышать половину 
         * «метафона» введенного слова (грубо говоря, допускается до половины неправильно 
         * написанных согласных букв), потом, среди выбранных вариантов, снова проверяем расстояние, 
         * но по всему слову, а не по его «метафону» и подошедшие слова записываем в массив
         */
        foreach( $dict as $w ) {
            if( levenshtein( metaphone( $tword ), metaphone( $w->getText() ) ) < mb_strlen( metaphone( $tword ) ) / 2 ) {
                if( levenshtein( $tword, $w->getText() ) < mb_strlen( $tword ) / 2 ) {
                    $possible[$w->getMean()] = $w->getText();
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
        foreach( $possible as $n ) {
            $min_levenshtein = min( $min_levenshtein, levenshtein( $n, $tword ) );
        }
    
        // ищем максимальное значение «подобности» для тех слов, в которых расстояние Левенштейна будет минимальным
        foreach( $possible as $n ) {
            if( levenshtein( $n, $tword ) == $min_levenshtein ) {
                $similarity = max( $similarity, similar_text( $n, $tword ) );
            }
        }
        
        // запускаем цикл, который выберет все слова с наименьшим расстоянием Левенштейна и 
        // наибольшим значением «подобности» одновременно
        foreach( $possible as $n=>$k ) {
            if( levenshtein( $k, $tword ) <= $min_levenshtein ) {
                if( similar_text( $k, $tword ) >= $similarity ) {
                    $result[$n] = $k;
                }
            }
        }
    
        // определяем максимальное значение «подобности» между «метафонами» нашего слова и слов в массиве, 
        // и минимальное расстояние Левенштейна
        foreach( $result as $n ) {
            $meta_min_levenshtein = min( $meta_min_levenshtein, levenshtein( metaphone( $n ), metaphone( $tword ) ) );
        }
     
        foreach( $result as $n ) {
            if( levenshtein( $k, $tword ) == $meta_min_levenshtein ) {
                $meta_similarity = max( $meta_similarity, similar_text( metaphone( $n ), metaphone( $tword ) ) );
            }
        }
        
        // получаем окончательный массив, который, в идеале, должен содержать одно слово
        foreach( $result as $n=>$k ) {
            if( levenshtein( metaphone( $k ), metaphone( $tword ) ) <= $meta_min_levenshtein ) {
                if( similar_text( metaphone( $k ), metaphone( $tword ) ) >= $meta_similarity ) {
                    $meta_result[$n] = $k;
                }
            }
        }
        
        return key( $meta_result );
    }
    
    public function encode($str)
    {
        $tr = array(
            "А"=>"A","Б"=>"B","В"=>"V","Г"=>"G",
            "Д"=>"D","Е"=>"E","Ё"=>"YO","Ж"=>"J","З"=>"Z","И"=>"I",
            "Й"=>"Y","К"=>"K","Л"=>"L","М"=>"M","Н"=>"N",
            "О"=>"O","П"=>"P","Р"=>"R","С"=>"S","Т"=>"T",
            "У"=>"U","Ф"=>"F","Х"=>"H","Ц"=>"TS","Ч"=>"CH",
            "Ш"=>"SH","Щ"=>"SCH","Ъ"=>"","Ы"=>"YI","Ь"=>"",
            "Э"=>"E","Ю"=>"YU","Я"=>"YA","а"=>"a","б"=>"b",
            "в"=>"v","г"=>"g","д"=>"d","е"=>"e","ё"=>"yo","ж"=>"j",
            "з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
            "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
            "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
            "ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
            "ы"=>"yi","ь"=>"'","э"=>"e","ю"=>"yu","я"=>"ya"
        );
          
        return strtr( $str, $tr );
    }
}