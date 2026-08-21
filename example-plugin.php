<?php
/**
 * Plugin Name: RL Options Framework - Quick Start Example
 * Description: A basic example of how to implement the RL Options Framework in a standalone plugin.
 * Version: 1.0.0
 * Author: David Rosendo
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Include the framework main file (or use Composer autoload)
require_once __DIR__ . '/main.php';

add_action('plugins_loaded', function() {
    // 2. Initialize the framework
    // The framework will register the admin menu automatically.
    $framework = new RL_Options_Framework(
        'my_plugin_options', // Option Key (saved in wp_options)
        [
            'menu_title' => 'My Plugin Settings',
            'page_title' => 'My Plugin Configuration',
            'capability' => 'manage_options',
            'menu_slug'  => 'my-plugin-settings',
            'icon_url'   => 'dashicons-admin-generic',
            'position'   => 50,
        ]
    );

    // 3. Add a Tab
    $framework->add_tab([
        'id'    => 'general',
        'label' => 'General Settings',
    ]);

    // 4. Add a Section
    $framework->add_section([
        'tab_id' => 'general',
        'id'     => 'api_section',
        'title'  => 'API Configuration',
    ]);

    // 5. Add Fields
    $framework->add_field([
        'tab_id'     => 'general',
        'section_id' => 'api_section',
        'id'         => 'enable_api',
        'type'       => 'toggle',
        'label'      => 'Enable API Integration',
    ]);

    $framework->add_field([
        'tab_id'     => 'general',
        'section_id' => 'api_section',
        'id'         => 'api_key',
        'type'       => 'text',
        'label'      => 'API Key',
        // Show this field ONLY if the toggle above is ON
        'conditions' => [
            ['field' => 'enable_api', 'operator' => 'truthy']
        ],
    ]);
});
