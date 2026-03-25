# RL Options Framework Documentation

A portable options framework for WordPress plugins and themes.

## 1. Installation

```php
require_once __DIR__ . '/includes/library/rloptionsFramework/main.php';

$config = [
    'option_name'       => 'my_project_options',
    'form_field_prefix' => 'my_project',
    'page_slug'         => 'my-project-settings',
    'menu_title'        => 'My Project',
    'page_title'        => 'My Project Settings',
    'text_domain'       => 'my-project',
];

$framework = new RL_Options_Framework($config);
$framework->init();
```

## 2. Canonical Hooks (Runtime Contract)

Hook names are generated from `option_name`:

- Tabs filter: `{option_name}_framework_tabs`
- Boot action: `{option_name}_framework_boot`

Example:

```php
add_filter('my_project_options_framework_tabs', function(array $tabs) {
    $tabs['general'] = [
        'label' => __('General', 'my-project'),
        'priority' => 10,
        'sections' => [
            'main' => [
                'id' => 'main',
                'title' => __('Main', 'my-project'),
                'fields' => [
                    'enable_feature' => [
                        'id' => 'enable_feature',
                        'type' => 'toggle',
                        'label' => __('Enable Feature', 'my-project'),
                        'default' => true,
                    ],
                ],
            ],
        ],
    ];

    return $tabs;
});
```

## 3. Configuration Options

- `context`: `auto | plugin | theme`
- `register_menu`: `true | false`
- `sync_history`: `true | false`
- `debug_level`: `error | warn | info | debug`
- `swal_fallback`: `true | false`
- `use_local_assets_toggle`: `true | false`
- `local_assets_field_id`: option key used by the local-assets toggle

Notes:

- If `register_menu` is `false`, host code must create its own menu/submenu and point callback to `$framework->render_page()`.
- If `assets_url` is set, it has highest priority. Otherwise framework resolves URL by context.
- `use_local_assets_toggle` only affects RL Options Framework UI libraries (SweetAlert2, Tippy). It does not change assets from other plugins/themes.

## 4. Field Type Contract

| Type | Saved value | Notes |
|------|-------------|-------|
| `toggle` | `bool` | On/off switch |
| `checkbox` | `bool` | Single checkbox with `text` label |
| `select` | `string` | Requires `options` map |
| `multiselect` | `string[]` | Checkboxes, requires `options` map |
| `radio` | `string` | Requires `options` map |
| `image_select` | `string` | Each option: `['src' => '...', 'label' => '...']` |
| `text` | `string` | Standard text input |
| `textarea` | `string` | Multi-line, `rows` optional |
| `number` | `int\|float` | Use `min`, `max`, `step`; optional `suffix` |
| `color` | `string` (hex) | WP Color Picker; default must be valid hex |
| `image` | `string` (URL) | WP media uploader (single image) |
| `datetime` | `string` (`Y-m-d H:i`) | Calendar + time picker — see §4a |
| `html` | — | Read-only HTML block; use `html` key |

### §4a — `datetime` field

Renders a WordPress **jQuery UI datepicker** calendar alongside a native `<input type="time">`. The canonical saved value is a single string in `Y-m-d H:i` format.

```php
'fields' => [
    'launch_at' => [
        'id'          => 'launch_at',
        'type'        => 'datetime',
        'label'       => __( 'Launch Date & Time', 'my-plugin' ),
        'description' => __( 'Date the feature becomes active.', 'my-plugin' ),
        'default'     => '2026-01-01 09:00',  // Y-m-d H:i
        'required'    => false,
        'time_step'   => 900,               // seconds between time options (default 60)
    ],
],
```

**Reading the saved value:**

```php
$raw = get_option('my_project_options')['launch_at'] ?? '';

if ( $raw !== '' ) {
    $timestamp = strtotime( $raw );              // Unix timestamp
    $display   = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $timestamp );
}
```

**Field options:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `default` | `string` | `''` | Pre-fill value in `Y-m-d H:i` format |
| `required` | `bool` | `false` | Blocks save when empty |
| `time_step` | `int` | `60` | Browser time-picker increment in seconds (min 60) |
| `placeholder` | `string` | `'Select date'` | Visible hint in the date input |

**Validation rules:**

- If `required => true` and value is empty → validation error.
- If a value is present it must match `Y-m-d H:i` and be a valid calendar date.

**Sanitization:**

- Non-empty values are round-tripped through `strtotime()` and re-formatted as `Y-m-d H:i`.
- Empty string is stored as-is (allows clearing an optional field).
- Invalid values fall back to the field `default` when one is provided, otherwise `''`.

**Dependencies added automatically when the settings page loads:**

- `jquery-ui-datepicker` (bundled with WordPress)
- `wp_localize_jquery_ui_datepicker()` is called for locale-aware month/day names and date strings
- `wp-color-picker` was already enqueued
- Datepicker popup visuals are provided by framework CSS (self-contained, no image sprite dependency)

### §4b — `image_select` schema

```php
'options' => [
    'grid' => ['src' => 'https://example.com/grid.png', 'label' => 'Grid'],
    'list' => ['src' => 'https://example.com/list.png', 'label' => 'List'],
]
```

Invalid schema combinations are logged as warnings.

## 5. Validation Behavior

- Fields are optional by default.
- `required => true` enables required validation.
- Validation errors return structured metadata in AJAX responses:
  - `field_id`
  - `field_label`
  - `tab_id`
  - `section_id`
  - `message`

## 6. Save UX and Resilience

- AJAX save uses SweetAlert when available.
- If SweetAlert is missing/fails and `swal_fallback` is enabled, framework falls back to `window.alert`.
- On validation error, JS focuses the first invalid field and activates its tab/section.

## 7. Migration Note (Hook Naming)

If you previously used custom hook names like `my_plugin_settings_tabs`, migrate to:

- `my_plugin_options_framework_tabs` (where `my_plugin_options` is `option_name`)

The framework runtime only reads `{option_name}_framework_tabs`.
