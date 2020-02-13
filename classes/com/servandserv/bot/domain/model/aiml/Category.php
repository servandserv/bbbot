<?php

namespace com\servandserv\bot\domain\model\aiml;

class Category {

    const STAR_REG = "/<star[^>]*index=\"(\d{1,2})\"[^>]*\/>/";

    private $pattern;
    private $template;
    private $tokens = [];
    private $stars = []; // переменные паттерна
    private $occs = [];
    private $score;
    private $that;
    private $topic;
    private $replacements; // замены в шаблонах

    public function __construct(array $replacements = []) {
        $this->replacements = $replacements;
    }

    // разбиваем pattern категории на отдельные токены
    // они бывают простыми словами и специальными масками
    public function parseTokens($pattern) {
        $tokens = explode(" ", $pattern);
        foreach ($tokens as $token) {
            if (isset($this->replacements[$token])) {
                $stokens[] = [$token, $this->replacements[$token]];
            } else {
                $stokens[] = [$token, "/" . $token . "/"];
            }
        }

        return $stokens;
    }

    // оценка похожести фразы и категории

    /**
     * @param phrase string фраза которую проверяем на соответствие категории
     * @param history string строка последнего ответа бота
     */
    public function calcSimilarity($phrase, $history) {
        $this->stars = []; // чистим переменные паттерна под каждый запрос
        $this->score = 0; // обнуляем рейтинг
        // проверяем на соответствие that если он установлен
        // если он установлен а истории никакой нет то всегда возвращаем 0
        if ($this->that !== NULL && $history == NULL) {
            return $this->score;
        }
        // если that установлен и есть история, но она не соответствует паттерну, то тоже возвращаем 0
        if ($this->that !== NULL && $history !== NULL && FALSE === $this->match($history, $this->parseTokens($this->that))) {
            return $this->score;
        }
        // каждый запрос с историей имеет некоторую премию
        if ($this->that !== NULL) {
            $this->score = 0.1;
        }
        // полное совпадение строк
        // рейтинг сразу равен количеству токенов + премия
        if ($this->pattern == $phrase) {
            $this->score += count($this->tokens) * 10 + 9;
            return $this->score;
        }
        // частичное совпадение по подстроке
        // if( strstr( $this->pattern, $phrase ) ) return $score + 2;
        // строим карту расположения токенов в тексте запроса
        $occs = $this->match($phrase, $this->tokens);
        // в итоге и тут ничего не сошлось возвращаем 0
        if ($occs === FALSE) {
            $this->score = 0;
            return FALSE;
        } elseif (strpos($this->pattern, " ") === FALSE && strpos($phrase, " ") === FALSE) {
            // если паттерн и фраза состоят из одного слова то еще даем премию
            $this->score += 9;
        }

        // теперь разобъем фразу на куски соответствующие токенам, уберем наложение
        foreach ($occs as $k => &$occ) {
            $this->score += $occ[3] == "*" ? 1 : 10;

            // если токен описан звездочкой, значит он скорее всего содержит всю фразу, надо его обрезать по соседям
            if ($occ[3] == "*" && isset($occs[$k + 1])) {
                // если есть сосед справа
                $end = max(0, $occs[$k + 1][0]); //последний символ до соседа справа
                // длина разница между началом токена и последним символом перед соседом справа
                //выкусываем текст
                $occ = [$occ[0], $end - $occ[0], trim(substr($phrase, $occ[0], $occ[1])), $occ[3]];
            }

            if ($occ[3] == "*" && isset($occs[$k - 1])) {
                // если есть сосед слева
                $start = $occs[$k - 1][0] + $occs[$k - 1][1]; // начало - первый символ после соседа слева
                // длина равна полной строке, поэтому сократим ее длину на отступ с начала
                $len = max(0, $occ[1] - $start);
                $occ = [$start, $len, trim(substr($phrase, $start, $len)), $occ[3]];
            }

            // если в итоге паттерн со звездочкой имеет нулевую длину, то тоже не подходит
            if ($occ[3] == "*" && $occ[1] == 0) {
                $this->score -= 2;
            }

            if (isset($this->replacements[$occ[3]])) {
                $this->stars[] = $occ[2];
            }
        }

        $this->occs = $occs;

        return $this->score;
    }

    /**
     *  ищем соответствие паттернам
     *
     *
     *
     */
    public function match($phrase, $tokens) {
        $occs = [];
        // фразу к нижнему регистру
        $phrase = strtolower($phrase);
        // ищем каждый токен в строке фразы
        foreach ($tokens as $k => $token) {
            // по текстовой строке, работает быстрее чем регулярка
            $offset = strpos($phrase, $token[0]);
            if ($offset !== FALSE) {
                $len = strlen($token[0]);
                $occs[] = [$offset, $len, trim(substr($phrase, $offset, $len)), $token[0]];
            } elseif (preg_match($token[1], $phrase, $m, PREG_OFFSET_CAPTURE)) {
                // по регулярному выражению
                $offset = mb_strlen(mb_strcut($phrase, 0, trim($m[0][1]))); // боремся с utf8
                $occs[] = [$offset, strlen($m[0][0]), trim($m[0][0]), $token[0]];
            } else {
                // если токен не найден то возвращаем FALSE и дальше уже не ищем
                return FALSE;
            }
        }

        return $occs;
    }

    public function getTopic() {
        return $this->topic;
    }

    public function setTopic($topic) {
        $this->topic = $topic;
        return $this;
    }

    public function getPattern() {
        return $this->pattern;
    }

    public function setPattern($pattern) {
        $this->pattern = strtolower($pattern);
        $this->tokens = $this->parseTokens($this->pattern);

        return $this;
    }

    public function getTokens() {
        return $this->tokens;
    }

    public function getScore() {
        return $this->score;
    }

    public function setTemplate(Template $template) {
        $this->template = $template;
        return $this;
    }

    public function getTemplate(array $vars = []) {
        $templ = $this->template->getTemplateText($vars);
        if (preg_match_all(self::STAR_REG, $templ, $m)) {
            foreach ($m[0] as $k => $str) {
                if (isset($this->stars[$m[1][$k] - 1])) {
                    $templ = str_replace($str, $this->stars[$m[1][$k] - 1], $templ);
                } else {
                    $templ = str_replace($str, "", $templ);
                }
            }
        }

        return $templ;
    }

    public function setThat($that) {
        $this->that = strtolower($that);
        return $this;
    }

    public function getThat() {
        return $this->that;
    }

}
