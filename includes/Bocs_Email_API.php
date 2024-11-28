<?php

class Bocs_Email_API
{
    public function permissions_check($request)
    {
        return current_user_can('manage_woocommerce');
    }

    public function register_routes()
    {
        register_rest_route('wc/v3', '/send-bocs-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_bocs_email'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => array(
                'to' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_email($param);
                    }
                ),
                'from' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_email($param);
                    }
                ),
                'title' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'content' => array(
                    'required' => true,
                    'sanitize_callback' => 'wp_kses_post'
                ),
                'headers' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
            ),
        ));
    }

    public function send_bocs_email(WP_REST_Request $request)
    {
        // Get the parameters from the request
        $to = $request->get_param('to');
        $from = $request->get_param('from');
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $headers_param = $request->get_param('headers');

        // Set the headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if ($headers_param) {
            $headers[] = $headers_param;
        }
        // $headers[] = 'From: ' . $from;

        // Send the email
        $mail_sent = wp_mail($to, $title, $content, $headers);

        // Check if the email was sent successfully
        if ($mail_sent) {
            return new WP_REST_Response('Email sent successfully', 200);
        } else {
            return new WP_REST_Response('Failed to send email', 500);
        }
    }
}
