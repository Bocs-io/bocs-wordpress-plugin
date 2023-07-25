<?php

class Bocs_Log_Handler extends WC_Log_Handler_DB {
    
    public function handle($timestamp, $level, $message, $context){

        parent::handle($timestamp, $level, $message, $context);

    }

}