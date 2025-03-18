<?php

class Error_Logs_List_Table extends WP_List_Table {

    public function get_columns(){

        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'module'    => 'Module',
            'code'      => 'Error Code',
            'message'   => 'Error Message',
            'details'   => 'Details',
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
                "SELECT * FROM {$table_name} WHERE source = %s ORDER BY log_id DESC",
                "bocs"
            )
        );

        foreach( $logs as $log ){
            $context = $log->context; 
            $decoded_context = json_decode( $context, true );

            // Initialize default values
            $module = '';
            $code = '';
            $details = '';
            $method = '';

            // Only proceed if we have valid decoded context
            if (is_array($decoded_context)) {
                if( $log->message === 'success' ){
                    $code = 200;
                } else {
                    $code = isset($decoded_context['code']) ? $decoded_context['code'] : '';
                }

                $module = isset($decoded_context['module']) ? $decoded_context['module'] : '';
                $method = isset($decoded_context['method']) ? $decoded_context['method'] : '';

                // Build details string
                if (!empty($method)) {
                    $details .= "Method: " . $method . ", ";
                }
                
                if ($module === 'contacts' && isset($decoded_context['id'])) {
                    $details .= "WordPress User ID: " . $decoded_context['id'] . ", ";
                }

                if (!empty($decoded_context['data'])) {
                    $details .= "Parameters: " . $decoded_context['data'] . ", ";
                }

                if (!empty($decoded_context['url'])) {
                    $details .= "Endpoint: " . $decoded_context['url'];
                }
            }

            $result[] = array(
                'id' => $log->log_id,
                'module' => $module,
                'code' => $code,
                'message' => $log->message,
                'details' => $details,
                'log_time' => $log->timestamp
            );
        }

        return $result;

    }

}