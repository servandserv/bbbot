<?php

namespace com\servandserv\bot\domain\service;

class Synchronizer
{

    const TRY_TS = 60;

    protected $ms;

    public function __construct( $ms )
    {
        $this->ms = $ms;
    }
    
    /**
     * синхронизируем какие то действия
     * для того, чтобы интервал между действиями не был меньше определенного переменной $ms
     * значения
     * Контроль частоты действий ведется в разных потоках(контекстах) для чего кажодому потоку
     * присваивается уникальный идентификатор
     * Логика синхронизации следующая
     * создаем файл блокировки и файл для отметки времени последнего действия
     * при вызове функции  next проверяем не заблокирован ли файл блокировки
     * ждем пока не разблокируется
     * блокируем его, читаем время из файла отметки времени
     * время следующего действия = max( now(), время из файла + $ms )
     * записываем его в файл отметки времени
     * ждем  пока оно настанет если езе не настало
     * снимаем блокировку, завершаем
     * 
     */
    public function next( $unique )
    {
        $tempDir = $this->getTempDir();
        $lockFile = $tempDir.DIRECTORY_SEPARATOR.$unique.".lock";
        $tsFile = $tempDir.DIRECTORY_SEPARATOR.$unique.".ts";
        if ( !is_dir( $tempDir ) ) {
            mkdir( $tempDir, 0770 );
        }
        if( !file_exists( $tsFile ) ) {
            file_put_contents( $tsFile, 0 );
        }
        $start = time();
        
        //выставляем эксклюзивную блокировку файла
        $lockfp = fopen( $lockFile, "w" );
        // ждем пока освободится
        $success = FALSE;
        while( !$success && time() - $start < self::TRY_TS ) {
            if( flock( $lockfp, LOCK_EX | LOCK_NB ) ) {
                $success = TRUE;
                $lts = file_get_contents( $tsFile );
                while( microtime( true ) * 1000 < $this->ms + $lts ) {
                    // тормозим пока не откроется окно
                }
                //запишем новое время
                file_put_contents( $tsFile, microtime( true ) * 1000 );
                // снимем блокировку
                flock( $lockfp, LOCK_UN );
            }
        }
        fclose( $lockfp );
        //unlink( $lockFile );
    }
    
    public function getTempDir() {
        return sys_get_temp_dir() . "/Synchronizer-BBBot";
    }
}