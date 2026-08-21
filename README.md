# RL Options Framework

**by [David Rosendo](https://github.com/drosendo) · a [RosendoLabs](https://rosendolabs.com) product**

A modern, standalone, **plugin-agnostic** options framework for WordPress plugins and themes. Built for developers who want a clean, powerful settings UI without bloat or dependencies on any specific plugin.

---

## ✨ Features

**Field Types (17 built-in)**
- `text`, `textarea`, `number`, `date`, `datetime`
- `select`, `multiselect`, `radio`, `checkbox`, `toggle`
- `color` (WP Color Picker)
- `image` (WP Media Library)
- `image_select` (visual option picker)
- `country`, `state`, `city` (cascading geo fields)
- `html` / `info` (static content blocks)
- Custom field types via the field registry

**UI & Layout**
- Tabbed interface with icon support
- Collapsible accordion sections
- Sidebar navigation layout for complex pages
- Responsive design · Dashicons integration

**Advanced Logic**
- Nested `AND` / `OR` visibility conditions on fields, sections, and tabs
- Field validation (built-in + custom callbacks)
- Field sanitization per type
- AJAX save with SweetAlert2 notifications
- Import / Export settings as JSON (schema-validated)
- Backup & Restore
- Tab state persistence via `localStorage`

**Developer-Friendly**
- Zero plugin-specific coupling
- Type-safe PHP (typed properties & return types)
- Filter hooks for full extensibility
- `composer.json` included — installable via Composer

---

## 📦 Installation

### Option A – Copy & Require

```php
// In your plugin's main file:
require_once plugin_dir_path( __FILE__ ) . 'includes/library/rloptionsFramework/main.php';
```

### Option B – Composer

```bash
composer require rosendolabs/rl-options-framework
```

---

## 🚀 Quick Start

```php
add_action( 'plugins_loaded', function () {

    $framework = new RL_Options_Framework([
        'option_name'       => 'my_plugin_settings',
        'form_field_prefix' => 'my_plugin',
        'page_slug'         => 'my-plugin-settings',
        'menu_title'        => 'My Plugin',
        'page_title'        => 'My Plugin Settings',
        'capability'        => 'manage_options',
    ]);

    $framework->init();

    // Tab
    $framework->add_tab([
        'id'    => 'general',
        'label' => 'General',
    ]);

    // Section
    $framework->add_section([
        'tab_id' => 'general',
        'id'     => 'api',
        'title'  => 'API Settings',
    ]);

    // Fields
    $framework->add_field([
        'tab_id'     => 'general',
        'section_id' => 'api',
        'id'         => 'enable_api',
        'type'       => 'toggle',
        'label'      => 'Enable API',
        'default'    => false,
    ]);

    $framework->add_field([
        'tab_id'     => 'general',
        'section_id' => 'api',
        'id'         => 'api_key',
        'type'       => 'text',
        'label'      => 'API Key',
        'conditions' => [
            ['field' => 'enable_api', 'operator' => 'truthy'],
        ],
    ]);
});
```

### Retrieve a saved value

```php
$value = get_option('my_plugin_settings')['api_key'] ?? '';
// or
$value = $framework->get_option('api_key', '');
```

---

## 🧩 Field Types Reference

### `text`

```php
[
    'id'          => 'api_key',
    'type'        => 'text',
    'label'       => 'API Key',
    'description' => 'Your secret API key.',
    'placeholder' => 'sk-...',
    'default'     => '',
]
```

---

### `number`

```php
[
    'id'      => 'items_per_page',
    'type'    => 'number',
    'label'   => 'Items Per Page',
    'default' => 10,
    'min'     => 1,
    'max'     => 100,
    'step'    => 1,
]
```

---

### `textarea`

```php
[
    'id'      => 'custom_css',
    'type'    => 'textarea',
    'label'   => 'Custom CSS',
    'default' => '',
    'rows'    => 10,
]
```

---

### `toggle`

```php
[
    'id'      => 'enable_feature',
    'type'    => 'toggle',
    'label'   => 'Enable Feature',
    'default' => true,
]
```

---

### `checkbox`

```php
[
    'id'      => 'agree_terms',
    'type'    => 'checkbox',
    'label'   => 'Terms',
    'text'    => 'I agree to the terms and conditions.',
    'default' => false,
]
```

---

### `radio`

```php
[
    'id'      => 'layout',
    'type'    => 'radio',
    'label'   => 'Layout Style',
    'default' => 'grid',
    'options' => [
        'grid' => 'Grid',
        'list' => 'List',
    ],
]
```

---

### `select`

```php
[
    'id'      => 'environment',
    'type'    => 'select',
    'label'   => 'Environment',
    'default' => 'production',
    'options' => [
        'production'  => 'Production',
        'staging'     => 'Staging',
        'development' => 'Development',
    ],
]
```

---

### `multiselect`

```php
[
    'id'      => 'roles_allowed',
    'type'    => 'multiselect',
    'label'   => 'Allowed Roles',
    'default' => ['editor'],
    'options' => [
        'administrator' => 'Administrator',
        'editor'        => 'Editor',
        'author'        => 'Author',
        'subscriber'    => 'Subscriber',
    ],
]
```

---

### `color`

```php
[
    'id'      => 'primary_color',
    'type'    => 'color',
    'label'   => 'Primary Color',
    'default' => '#0073aa',
]
```

---

### `image`

Opens the WordPress Media Library picker. Saves the attachment URL.

```php
[
    'id'    => 'logo',
    'type'  => 'image',
    'label' => 'Site Logo',
]
```

---

### `image_select`

A visual radio-style picker using image thumbnails.

```php
[
    'id'      => 'theme_style',
    'type'    => 'image_select',
    'label'   => 'Theme Style',
    'default' => 'light',
    'options' => [
        'light' => 'Light',
        'dark'  => 'Dark',
    ],
]
```

---

### `date`

Renders a date picker (WP jQuery UI Datepicker). Saves as `Y-m-d`.

```php
[
    'id'      => 'start_date',
    'type'    => 'date',
    'label'   => 'Start Date',
    'default' => '',
]
```

---

### `datetime`

Date + time picker. Saves as `Y-m-d H:i`.

```php
[
    'id'      => 'launch_at',
    'type'    => 'datetime',
    'label'   => 'Launch Date & Time',
    'default' => '2026-01-01 09:00',
]
```

---

### `country`

Renders a country dropdown populated via a `geo_options_callback`. Cascades with `state` and `city`.

```php
[
    'id'          => 'billing_country',
    'type'        => 'country',
    'label'       => 'Country',
    'placeholder' => '— Select Country —',
]
```

---

### `state`

Cascades from a `country` field. Requires `country_field`.

```php
[
    'id'            => 'billing_state',
    'type'          => 'state',
    'label'         => 'State / Province',
    'country_field' => 'billing_country',
    'depends_on'    => ['billing_country'],
]
```

---

### `city`

Cascades from `country` + `state`. Requires `country_field` and `subdivision_field`.

```php
[
    'id'               => 'billing_city',
    'type'             => 'city',
    'label'            => 'City',
    'country_field'    => 'billing_country',
    'subdivision_field'=> 'billing_state',
    'depends_on'       => ['billing_country', 'billing_state'],
]
```

---

### `html`

Renders arbitrary HTML. Sanitized with `wp_kses_post()`.

```php
[
    'id'   => 'help_box',
    'type' => 'html',
    'html' => '<div class="notice notice-info"><p>This is a help notice.</p></div>',
]
```

---

### `info`

Renders the field's `description` as a styled info block (no input).

```php
[
    'id'          => 'upgrade_notice',
    'type'        => 'info',
    'label'       => 'Pro Feature',
    'description' => 'Upgrade to Pro to unlock this feature.',
]
```

---

## 👁 Visibility Conditions

Show or hide any field, section, or tab based on another field's value.

### Simple (AND — all rules must pass)

```php
'conditions' => [
    ['field' => 'enable_api', 'operator' => 'truthy'],
    ['field' => 'mode',       'operator' => 'equals', 'value' => 'advanced'],
]
```

### Nested OR / AND

```php
'conditions' => [
    'relation' => 'OR',
    ['field' => 'mode', 'operator' => 'equals', 'value' => 'custom'],
    [
        'relation' => 'AND',
        ['field' => 'mode',     'operator' => 'equals', 'value' => 'auto'],
        ['field' => 'override', 'operator' => 'equals', 'value' => '1'],
    ],
]
```

### Supported Operators

| Operator | Meaning |
|----------|---------|
| `equals` / `==` | Exactly equal |
| `not_equals` / `!=` | Not equal |
| `in` | Value is in array |
| `not_in` | Value is not in array |
| `>` / `greater_than` | Greater than |
| `>=` | Greater than or equal |
| `<` / `less_than` | Less than |
| `<=` | Less than or equal |
| `truthy` | Value is truthy / not empty |
| `falsy` | Value is falsy / empty |

> **Tip:** Toggle fields save as `"1"` (on) or `""` (off). Use `truthy` / `falsy` for the cleanest evaluation.

---

## ✅ Validation

### Built-in

```php
[
    'id'       => 'port',
    'type'     => 'number',
    'required' => true,
    'min'      => 1024,
    'max'      => 65535,
]
```

### Custom callback

```php
[
    'id'                => 'email',
    'type'              => 'text',
    'validate_callback' => function( $value, $field ) {
        return is_email( $value ) ? true : new WP_Error( 'invalid', 'Enter a valid email.' );
    },
]
```

### Conditional required (`required_if`)

```php
[
    'id'          => 'vat_number',
    'type'        => 'text',
    'label'       => 'VAT Number',
    'required_if' => [
        ['field' => 'business_type', 'operator' => 'equals', 'expected' => 'company'],
    ],
]
```

---

## 🔧 Configuration Reference

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `option_name` | `string` | `'rl_framework_settings'` | wp_options key |
| `form_field_prefix` | `string` | `'rl_options'` | HTML form field prefix |
| `page_slug` | `string` | `'rl-options-settings'` | Admin menu slug |
| `menu_title` | `string` | `'Plugin Settings'` | Sidebar menu text |
| `page_title` | `string` | `'Plugin Settings'` | `<h1>` heading |
| `capability` | `string` | `'manage_options'` | Required capability |
| `parent_menu` | `string` | `'options-general.php'` | Parent menu page |
| `text_domain` | `string` | `'rl-options-framework'` | i18n text domain |
| `ajax_action` | `string` | `'rl_save_options_ajax'` | AJAX action name |
| `assets_url` | `string` | Auto-detected | URL to the `assets/` folder |
| `version` | `string` | `'2.2.0'` | For cache-busting enqueued assets |

---

## 🪝 Filter Hooks

```php
// Register tabs/fields
add_filter( 'my_plugin_settings_framework_tabs', function( $tabs, $framework ) {
    // Modify $tabs and return
    return $tabs;
}, 10, 2 );

// Run logic after the framework boots
add_action( 'my_plugin_settings_framework_boot', function( $framework ) {
    // e.g. register custom field types
}, 10 );
```

---

## 💾 Import / Export / Backup

```php
$json   = $framework->export_settings();           // Export as JSON string
$result = $framework->import_settings( $json );    // Validates before saving
$backup = $framework->create_backup();             // Snapshot current state
        $framework->restore_backup();              // Roll back to last backup
        $framework->reset_to_defaults();           // Wipe and restore defaults
```

Imported JSON is validated against the registered field schema — unknown keys are silently discarded.

---

## 📋 Requirements

- WordPress 5.0+
- PHP 7.4+
- jQuery (bundled with WordPress)
- jQuery UI Datepicker (bundled with WordPress)
- WP Color Picker (bundled with WordPress)
- SweetAlert2 (local vendor or CDN fallback)
- Tippy.js + Popper.js (local vendor or CDN fallback)

---

## 📄 License

GPL-2.0-or-later — compatible with the WordPress ecosystem.

---

## 👤 Credits

Developed by **[David Rosendo](https://github.com/drosendo)** · a **[RosendoLabs](https://rosendolabs.com)** product.

---

## 📦 Changelog

### 2.2.0
- Added nested `AND` / `OR` condition groups for fields, sections and tabs
- Removed all legacy visibility aliases (`show_if`, `visibility_rules`)
- Renamed plugin-specific CSS classes to generic `rl-options-*` prefix
- Added GPL-2.0 `LICENSE` file and `example-plugin.php` quick-start

### 2.1.1
- Hardened `html` fields to sanitize rendered markup with a configurable allowlist
- Hardened JSON imports to validate and sanitize recognized fields before saving
- Removed nonce value logging from AJAX save debug logs
- Restricted remote geo reference fetches to HTTPS via `wp_safe_remote_get()`
- Added logger context redaction for sensitive keys (nonce/token/password/secret)

### 2.1.0
- Added `datetime` field type with WordPress datepicker calendar + time input
- Added server-side datetime validation and sanitization (`Y-m-d H:i`)

### 2.0.0
- Complete refactor to be plugin-agnostic
- Dynamic configuration via constructor
- Conditional tab/section/field visibility
- Type-safe PHP code

### 1.0.0
- Initial release
