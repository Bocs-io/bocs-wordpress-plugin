<?php

class Bocs_Log_Handler {

    public function process_log_from_result( $result, $url = false, $params = false, $method = 'get', $module = '', $id = '' ){

        if( empty($result) ) return false;
        if( empty($result->code) ) return false;
        if( empty($result->message) ) return false;

        

        $level = 'notice';
        $message = $result->message;

        $context = array(
            'module'    => $module,
            'id'        => $id,
            'method'    => $method,
            'data'      => $params
        );
        

        if( $result->code == 200 ){
            $level = 'notice';
        } else {
            $level = 'error';
        }

        $this->insert_log($level, $message, $context);

    }
    
    /**
     * Adds logs on the woocommerce_log table
     * 
     * @param string $level 
     *
     * The status numbers and their corresponding severity levels are as follows:
     * 
     * 0 - Emergency: The highest severity level, indicating a system-wide critical failure.
     * 1 - Alert: An urgent situation that requires immediate attention.
     * 2 - Critical: A critical condition that needs to be addressed promptly. 
     * 3 - Error: An error has occurred, but the system can still function.
     * 4 - Warning: An issue that may require attention but doesn’t disrupt the system.
     * 5 - Notice: General information about the system’s operation.
     * 6 - Informational: Additional information about system events.
     * 7 - Debug: Detailed debugging information, useful for troubleshooting.
     *
     * @param string $message
     * @param string $context
     * 
     * @return void
     */
    public function insert_log( $level = 'debug', $message, $context ) {

        global $wpdb;

        $table_name = $wpdb->prefix . "woocommerce_log";

        $level_number = 7;

        switch( $level ){

            case 'emergency':
                $level_number = 0;
                break;
            
            case 'alert':
                $level_number = 1;
                break;
            
            case 'critical':
                $level_number = 2;
                break;
            
            case 'error':
                $level_number = 3;
                break;
            
            case 'warning':
                $level_number = 4;
                break;
            
            case 'notice':
                $level_number = 5;
                break;
            
            case 'info':
            case 'information':
                $level_number = 6;
                break;
            
            case 'debug':

            default:
                $level_number = 7;
                break;

        }

        if( is_array( $context ) || is_object( $context ) ){
            $context = json_encode( $context );
        }

        return $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table_name} (timestamp, level, source, message, context) VALUES ( NOW(), %d, %s, %s, %s )",
                $level_number,
                'bocs',
                $message,
                $context
            )
        );
        
    }

}