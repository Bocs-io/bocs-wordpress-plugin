<?php

public function register_endpoints() {
    add_rewrite_endpoint('my-subscriptions', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('bocs-switch-bocs', EP_ROOT | EP_PAGES);
    flush_rewrite_rules();
}

/**
 * Add new query vars
 */
public function add_query_vars($vars) {
    $vars[] = 'my-subscriptions';
    $vars[] = 'bocs-switch-bocs';
    return $vars;
}

/**
 * Load account page templates
 */
public function load_template($template) {
    global $wp_query, $wp;
    
    // Check if on the account page
    if (!is_page(wc_get_page_id('myaccount'))) {
        return $template;
    }
    
    // Check for our custom endpoint and load the appropriate template
    if (isset($wp->query_vars['bocs-subscriptions'])) {
        $template = $this->get_template_path('myaccount/subscription-list.php');
    } elseif (isset($wp->query_vars['bocs-switch-bocs'])) {
        $template = $this->get_template_path('myaccount/switch-bocs.php');
    }
    
    return $template;
}

/**
 * Get the path to a template file
 * 
 * @param string $template_name The name of the template file
 * @return string The full path to the template file
 */
public function get_template_path($template_name) {
    // Use the templates directory in the plugin
    return plugin_dir_path(dirname(__FILE__)) . 'templates/' . $template_name;
} 