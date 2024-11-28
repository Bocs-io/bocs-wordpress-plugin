<?php

class Api
{

    private $url = BOCS_API_URL;

    private $api_token;

    public function __construct()
    {
        $this->api_token = get_option(BOCS_SLUG . '_key');
    }

    public function custom_api_routes()
    {

        // registering router for the updating of the the collections
        register_rest_route('bocs-woocommerce-api/' . CUSTOM_API_VERSION, 'update-collections', array(
            'methods' => 'POST',
            'callback' => array(
                $this,
                'update_collections'
            )
        ));

        // registering router for the updating of the widgets
        register_rest_route('bocs-woocommerce-api/' . CUSTOM_API_VERSION, 'update-widgets', array(
            'methods' => 'POST',
            'callback' => array(
                $this,
                'update_widgets'
            )
        ));
    }

    /**
     *
     * Updated the collections list
     *
     * @param array $post
     * @return WP_REST_Response
     */
    public function update_collections($post)
    {
        $header_auth = isset($_SERVER['Authorization']) ? $_SERVER['Authorization'] : '';

        $data = array(
            'message' => 'failed',
            'notes' => 'Not valid headers'
        );

        if (empty($header_auth))
            return new WP_REST_Response($data, 400);

        // then check with our settings
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        if ($options['bocs_headers']['store'] !== $header_auth)
            return new WP_REST_Response($data, 400);

        $data = array(
            'message' => 'success',
            'notes' => 'No data.'
        );

        // get all the collections posted
        if (! empty($post['collections'])) {
            // we will get all the collections and save it

            $active_collections = array();

            foreach ($post['collections'] as $collection) {

                $active_collections[] = array(
                    'id' => $collection['id'],
                    'type' => $collection['type'],
                    'name' => $collection['name'],
                    'description' => $collection['description']
                );
            }

            // update_option("bocs_collections", $active_collections);
            // update_option("bocs_last_update_time", time());

            $data = array(
                'message' => 'success',
                'notes' => 'Records retrieved.'
            );
        }

        return new WP_REST_Response($data, 200);
    }

    public function update_widgets($post)
    {

        // check the headers
        $header_auth = isset($_SERVER['Authorization']) ? $_SERVER['Authorization'] : '';

        $data = array(
            'message' => 'failed',
            'notes' => 'Not valid headers'
        );

        if (empty($header_auth))
            return new WP_REST_Response($data, 400);

        // then check with our settings
        $options = get_option('bocs_plugin_options');
        $options['bocs_headers'] = $options['bocs_headers'] ?? array();

        if ($options['bocs_headers']['store'] !== $header_auth)
            return new WP_REST_Response($data, 400);

        $data = array(
            'message' => 'success',
            'notes' => 'No data.'
        );

        // get all the collections posted
        if (! empty($post['bocs'])) {
            // we will get all the collections and save it

            $active_widgets = array();

            foreach ($post['bocs'] as $bocs) {

                $active_widgets[] = array(
                    'id' => $bocs['id'],
                    'type' => $bocs['type'],
                    'name' => $bocs['name'],
                    'description' => $bocs['description']
                );
            }

            // update_option("bocs_widgets", $active_widgets);
            update_option("bocs_last_update_time", time());

            $data = array(
                'message' => 'success',
                'notes' => 'Records retrieved.'
            );
        }

        return new WP_REST_Response($data, 200);
    }
}
