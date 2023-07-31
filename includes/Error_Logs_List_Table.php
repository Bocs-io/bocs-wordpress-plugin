<?php

class Error_Logs_List_Table extends WP_List_Table {

    public function get_columns(){

        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'module'    => 'Module',
            'code'      => 'Error Code',
            'message'   => 'Error Message',
            'details'   => 'Details'
            'log_time'  => 'Date/Time'
        );

        return $columns;

    }

    public function prepare_items(){

        $columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $this->_get_table_data();

    }

    public function column_default($item, $column_name)
	{
		switch ($column_name){
			case 'module':
			case 'code':
			case 'message':
            case 'details':
			case 'log_time':
			default:
				return $item[$column_name];
		}

	}

    public function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="element[]" value="%s" />',
			$item['id']
		);
	}

    private function _get_table_data(){

        $result = array();

        // we will get all the logs related to bocs
        // id, module, code, message, context
        global $wpdb;

        $table_name = $wpdb->prefix . 'woocommerce_log';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE source = %s",
                "bocs"
            )
        );

        foreach( $logs as $log ){

            $context = $log->context; 
            $decoded_context = json_decode( $context, true );

            if( $log->message = 'success' ){
                $decoded_context['code'] = 200;
            }

            $result[] = array(
                'id' => $log->log_id,
                'module' => $decoded_context['module'],
                'code' => isset($decoded_context['code']) ? $decoded_context['code'] : '',
                'message' => $log->message,
                'details' => $context,
                'log_time' => $log->timestamp
            );
        }

        return $result;

    }

}