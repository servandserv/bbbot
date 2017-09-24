<?php

namespace com\servandserv\bot\domain\service;

class Timer
{

    private static $instance;
    private $limitpersecond;
    private $ticks;
    private $from;

    private function __construct( $limitpersecond )
    {
        $this->limitpersecond = $limitpersecond;
        $this->ticks = [];
    }
    
    public static function getInstance( $limitpersecond )
    {
        if(!static::$instance) {
            static::$instance = new self( $limitpersecond );
        }
        return static::$instance;
    }
    
    public function next()
    {
        // время последнего события
        $last = microtime( TRUE );
        $this->ticks[] = $last;
        //если событий столько же сколько ограничение то достанем первое из них
        // то же сделаем если это первой событие после создания таймера
        if( !$this->from || count( $this->ticks ) >= $this->limitpersecond ) {
            while( count( $this->ticks ) >= $this->limitpersecond ) {
                $this->from = array_shift( $this->ticks );
            }
        }
        // посчитаем сколько времени прошло с первого до последнего события в стеке
        $d = $last - $this->from;
        // если больше секунды то ничего не делаем уходим
        if( $last - $this->from > 1 ) return;
        // если меньше секунды, но при этом и число меньше лимита, то тоже ничего не делаем уходим
        else if( count( $this->ticks ) < $this->limitpersecond - 1 ) return;
        // иначе зависаем на период пока не наберется 1 секунда с первого события
        else {
            usleep( ( 1 - $d ) * 1000000 );
            return;
        }
    }
}