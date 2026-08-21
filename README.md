# RL Options Framework

A robust, flexible, **plugin-agnostic** options framework for WordPress plugins with extensive features and no dependencies on any specific plugin.

## Features

✨ **Comprehensive Field Types**
- Text, Textarea, Number, DateTime
- Select, Multiselect, Radio, Checkbox, Toggle
- Color picker (WP Color Picker)
- HTML/Info fields
- Custom field types via filters

🎨 **Modern UI Components**
- Tabbed interface with icon support
- Accordion sections
- Sidebar navigation for complex tabs
- Responsive design
- Dashicons integration
- SweetAlert2 notifications

🔄 **Advanced Functionality**
- Conditional field display (show/hide based on other fields)
- Conditional tab visibility
- Field validation with custom validators
- Field sanitization per type
- Schema-aware import sanitization and validation
- Priority-based sorting (tabs, sections, fields)
- Backup and restore functionality
- Import/export settings as JSON
- AJAX save with visual feedback
- Tab persistence with localStorage

🔧 **Developer-Friendly**
- Extensive filter hooks for extensibility
- Clean, documented code
- Type-safe PHP (typed properties, return types)
- No hardcoded plugin-specific values
- Fully configurable via constructor

## Installation

1. Copy the `rloptionsFramework` folder to your plugin's library/includes directory
2. Require the main file in your plugin
3. Instantiate with your configuration

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/library/rloptionsFramework/main.php';
```

## Basic Usage

### Initialize Framework

```php
$config = [
    'option_name'       => 'my_plugin_settings',       // Database option name
    'form_field_prefix' => 'my_plugin',                // Form field name prefix
    'page_slug'         => 'my-plugin-settings',       // Admin menu page slug
    'menu_title'        => 'My Plugin',                // Menu title
    'page_title'        => 'My Plugin Settings',       // Page <h1> title
    'capability'        => 'manage_options',           // Required capability
    'parent_menu'       => 'options-general.php',      // Parent menu (or 'woocommerce', etc.)
    'text_domain'       => 'my-plugin',                // i18n text domain
    'ajax_action'       => 'my_plugin_save_ajax',      // AJAX action name
    'version'           => '1.0.0',                    // Your plugin version
];

$framework = new RL_Options_Framework( $config );
$framework->init();
```

### Register Settings via Filter Hook

The recommended approach is to use the filter hook pattern:

```php
add_filter( 'my_plugin_settings_framework_tabs', 'my_plugin_register_settings', 10, 2 );

function my_plugin_register_settings( array $tabs, RL_Options_Framework $framework ): array {
    $tabs['general'] = [
        'label'    => __( 'General', 'my-plugin' ),
        'priority' => 10,
        'sections' => [
            'basic' => [
                'id'          => 'basic',
                'title'       => __( 'Basic Settings', 'my-plugin' ),
                'description' => __( 'Configure basic options', 'my-plugin' ),
                'accordion'   => true,  // Make it collapsible
                'priority'    => 10,
                'fields'      => [
                    'enabled' => [
                        'id'          => 'enabled',
                        'type'        => 'toggle',
                        'label'       => __( 'Enable Feature', 'my-plugin' ),
                        'description' => __( 'Turn this feature on or off', 'my-plugin' ),
                        'default'     => true,
                        'priority'    => 10,
                    ],
                    'mode' => [
                        'id'          => 'mode',
                        'type'        => 'select',
                        'label'       => __( 'Display Mode', 'my-plugin' ),
                        'description' => __( 'Choose how to display content', 'my-plugin' ),
                        'default'     => 'grid',
                        'options'     => [
                            'grid' => __( 'Grid View', 'my-plugin' ),
                            'list' => __( 'List View', 'my-plugin' ),
                        ],
                        'priority'    => 20,
                        'conditions'  => [  // Show only when enabled=true
                            [
                                'field'    => 'enabled',
                                'operator' => 'equals',
                                'value'    => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    return $tabs;
}
```

### Retrieve Settings

```php
// Get entire options array
$options = get_option( 'my_plugin_settings', [] );

// Or use framework method
$framework = new RL_Options_Framework( $config );
$value = $framework->get_option( 'enabled', false );
```

## Field Types

### Text Field
```php
[
    'id'          => 'api_key',
    'type'        => 'text',
    'label'       => __( 'API Key', 'my-plugin' ),
    'description' => __( 'Enter your API key', 'my-plugin' ),
    'default'     => '',
    'placeholder' => 'abc123...',
]
```

### Number Field
```php
[
    'id'          => 'items_per_page',
    'type'        => 'number',
    'label'       => __( 'Items Per Page', 'my-plugin' ),
    'description' => __( 'Number of items to display', 'my-plugin' ),
    'default'     => 10,
    'min'         => 1,
    'max'         => 100,
    'step'        => 1,
    'required'    => true,
]
```

### DateTime Field
```php
[
    'id'          => 'launch_at',
    'type'        => 'datetime',
    'label'       => __( 'Launch Date & Time', 'my-plugin' ),
    'description' => __( 'Pick a date from the calendar and set the time.', 'my-plugin' ),
    'default'     => '2026-03-25 09:30', // Y-m-d H:i
    'required'    => false,
]
```

### Select Field
```php
[
    'id'          => 'layout',
    'type'        => 'select',
    'label'       => __( 'Layout', 'my-plugin' ),
    'description' => __( 'Choose a layout', 'my-plugin' ),
    'default'     => 'default',
    'options'     => [
        'default' => __( 'Default', 'my-plugin' ),
        'compact' => __( 'Compact', 'my-plugin' ),
        'wide'    => __( 'Wide', 'my-plugin' ),
    ],
]
```

### Toggle Field
```php
[
    'id'          => 'enable_feature',
    'type'        => 'toggle',
    'label'       => __( 'Enable Feature', 'my-plugin' ),
    'description' => __( 'Toggle to enable/disable', 'my-plugin' ),
    'default'     => true,
]
```

### Color Field
```php
[
    'id'          => 'primary_color',
    'type'        => 'color',
    'label'       => __( 'Primary Color', 'my-plugin' ),
    'description' => __( 'Choose a color', 'my-plugin' ),
    'default'     => '#0073aa',
]
```

### Textarea Field
```php
[
    'id'          => 'custom_css',
    'type'        => 'textarea',
    'label'       => __( 'Custom CSS', 'my-plugin' ),
    'description' => __( 'Add custom styles', 'my-plugin' ),
    'default'     => '',
    'rows'        => 10,
]
```

### Checkbox Field
```php
[
    'id'          => 'agree_terms',
    'type'        => 'checkbox',
    'label'       => __( 'Terms', 'my-plugin' ),
    'text'        => __( 'I agree to the terms', 'my-plugin' ),
    'default'     => false,
]
```

### Radio Field
```php
[
    'id'          => 'size',
    'type'        => 'radio',
    'label'       => __( 'Size', 'my-plugin' ),
    'description' => __( 'Select a size', 'my-plugin' ),
    'default'     => 'medium',
    'options'     => [
        'small'  => __( 'Small', 'my-plugin' ),
        'medium' => __( 'Medium', 'my-plugin' ),
        'large'  => __( 'Large', 'my-plugin' ),
    ],
]
```

### HTML/Info Field
```php
[
    'id'   => 'help_text',
    'type' => 'html',
    'html' => '<div class="notice notice-info"><p>' . __( 'This is an info box.', 'my-plugin' ) . '</p></div>',
]
```

`html` field markup is sanitized with `wp_kses()` before rendering. To allow custom tags or attributes, pass an `allowed_html` array in the field definition.

## Advanced Features

### Conditional Field Display

Show/hide fields based on other field values:

```php
[
    'id'         => 'api_endpoint',
    'type'       => 'text',
    'label'      => __( 'API Endpoint', 'my-plugin' ),
    'conditions' => [
        [
            'field'    => 'enable_api',     // Field to check
            'operator' => 'equals',         // Supports 'equals', 'not_equals', 'in', '>', '<', 'truthy', etc.
            'value'    => true,             // Value to match
        ],
    ],
]
```

Multiple conditions (AND logic):

```php
'conditions' => [
    ['field' => 'enable_api', 'operator' => 'equals', 'value' => true],
    ['field' => 'mode', 'operator' => 'equals', 'value' => 'advanced'],
]
```

Complex nested conditions (OR / AND logic):

```php
'conditions' => [
    'relation' => 'OR',
    ['field' => 'mode', 'operator' => 'equals', 'value' => 'advanced'],
    [
        'relation' => 'AND',
        ['field' => 'enable_api', 'operator' => 'equals', 'value' => true],
        ['field' => 'environment', 'operator' => 'equals', 'value' => 'production'],
    ],
]
```

### Conditional Tab/Section Visibility

Hide entire tabs or sections based on field values:

```php
$tabs['advanced'] = [
    'label'      => __( 'Advanced', 'my-plugin' ),
    'conditions' => [
        'field' => 'enable_advanced_mode',
        'value' => true,
    ],
    'sections'   => [...],
];

// Or inside a section:
$framework->add_section([
    'tab_id'     => 'advanced',
    'id'         => 'section_advanced_opts',
    'title'      => __( 'Advanced Options', 'my-plugin' ),
    'conditions' => [
        ['field' => 'advanced_type', 'value' => 'pro'],
    ],
]);
```

Multiple conditions for tabs or sections:

```php
'conditions' => [
    ['field' => 'enabled', 'value' => true],
    ['field' => 'mode', 'value' => 'pro'],
],
```

### Field Validation

Built-in validation:

```php
[
    'id'       => 'port',
    'type'     => 'number',
    'required' => true,
    'min'      => 1024,
    'max'      => 65535,
]
```

Custom validation callback:

```php
[
    'id'                => 'email',
    'type'              => 'text',
    'validate_callback' => function( $value, $field ) {
        if ( ! is_email( $value ) ) {
            return new WP_Error( 'invalid_email', __( 'Please enter a valid email.', 'my-plugin' ) );
        }
        return true;
    },
]
```

### Custom Sanitization

```php
[
    'id'                => 'custom_field',
    'type'              => 'text',
    'sanitize_callback' => function( $value, $field ) {
        return strtoupper( sanitize_text_field( $value ) );
    },
]
```

### Backup & Restore

```php
// Create backup
$backup = $framework->create_backup();

// Restore from backup
$framework->restore_backup();

// Export settings as JSON
$json = $framework->export_settings();

// Import settings from JSON
$result = $framework->import_settings( $json );

// Reset to defaults
$framework->reset_to_defaults();
```

Imported settings are validated and sanitized against the registered field schema before they are saved. Unknown field IDs are discarded.

## Filter Hooks

### Main Tabs Filter

Filter name format: `{option_name}_framework_tabs`

```php
add_filter( 'my_plugin_settings_framework_tabs', function( $tabs, $framework ) {
    // Modify $tabs array
    return $tabs;
}, 10, 2 );
```

### Framework Boot Hook

Hook name format: `{option_name}_framework_boot`

```php
add_action( 'my_plugin_settings_framework_boot', function( $framework ) {
    // Do something when framework initializes
}, 10 );
```

## Configuration Options

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `option_name` | string | Database option name | `'rl_framework_settings'` |
| `form_field_prefix` | string | Form field name prefix | `'rl_options'` |
| `page_slug` | string | Admin page slug | `'rl-options-settings'` |
| `menu_title` | string | Menu title | `'Plugin Settings'` |
| `page_title` | string | Page title | `'Plugin Settings'` |
| `capability` | string | Required capability | `'manage_options'` |
| `parent_menu` | string | Parent menu | `'options-general.php'` |
| `text_domain` | string | i18n text domain | `'rl-options-framework'` |
| `ajax_action` | string | AJAX action name | `'rl_save_options_ajax'` |
| `assets_url` | string | URL to assets folder | Auto-detected |
| `version` | string | Plugin version for cache busting | `'2.1.0'` |

## Structure Overview

```
rloptionsFramework/
├── class-rl-options-framework.php  # Main framework class
├── main.php                         # Bootstrap file
├── README.md                        # This file
└── assets/
    ├── css/
    │   └── options-framework.css    # Framework styles
    └── js/
        └── options-framework.js     # Framework JavaScript
```

## CSS Classes Reference

All CSS classes use the `.rl-` prefix:

- `.rl-options-page` - Main wrapper
- `.rl-tab-panel` - Tab content panel
- `.rl-field` - Field wrapper
- `.rl-sidebar-layout` - Sidebar navigation layout
- `.rl-section` - Section container
- `.rl-accordion-toggle` - Accordion header
- `.rl-toggle` - Toggle field wrapper
- `.rl-submit-bar` - Submit button bar

## Data Attributes Reference

- `data-rl-tab` - Tab identifier
- `data-rl-panel` - Panel identifier
- `data-rl-section` - Section identifier
- `data-conditions` - Field conditions (JSON)
- `data-tab-conditions` - Tab conditions (JSON)

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Dependencies

- WordPress 5.0+
- PHP 7.4+
- jQuery (bundled with WordPress)
- jQuery UI Datepicker (bundled with WordPress)
- WP Color Picker (bundled with WordPress)
- SweetAlert2 (local vendor by default, CDN fallback supported)
- Tippy.js + Popper.js (local vendor by default, CDN fallback supported)

### Local vs CDN Assets

The framework supports both local vendor assets and CDN-hosted assets.

- Enable local vendor assets with `use_local_assets_toggle` and `local_assets_field_id`.
- Set `assets_url` correctly so local vendor files resolve from `assets/vendor/`.
- If local mode is disabled, framework falls back to CDN URLs for SweetAlert2, Tippy.js, and Popper.js.

## License

This framework is designed to be portable and can be used in any WordPress plugin project.

## Credits

Developed by Rosendo Labs as a generic, reusable options framework for WordPress plugins.

## Changelog

### 2.1.1
- Hardened `html` fields to sanitize rendered markup with a configurable allowlist
- Hardened JSON imports to validate and sanitize recognized fields before saving
- Removed nonce value logging from AJAX save debug logs
- Restricted remote geo reference fetches to HTTPS via `wp_safe_remote_get()`
- Added logger context redaction for sensitive keys (nonce/token/password/secret)

### 2.1.0
- Added `datetime` field type with WordPress datepicker calendar + time input
- Added server-side datetime validation and sanitization (`Y-m-d H:i`)
- Enqueued WordPress jQuery UI datepicker for datetime UI
- Clarified README dependency strategy for local vendor assets and CDN fallback

### 2.0.0
- Complete refactor to be plugin-agnostic
- Removed all plugin-specific references
- Dynamic configuration via constructor
- Conditional tab visibility
- Enhanced documentation
- Type-safe PHP code

### 1.0.0
- Initial release
