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

## 3a. Declarative Dependencies and Async Providers (Opt-in)

Each field can declare dependency and provider metadata without breaking existing static fields.

```php
'country' => [
    'id'      => 'country',
    'type'    => 'select',
    'label'   => __('Country', 'my-plugin'),
    'options_provider' => [
        'endpoint' => 'countries',
    ],
],
'district' => [
    'id'       => 'district',
    'type'     => 'select',
    'label'    => __('District', 'my-plugin'),
    'depends_on' => ['country'],
    'required_if' => [
        ['field' => 'country', 'operator' => 'truthy'],
    ],
    'options_provider' => [
        'endpoint' => 'country_subdivisions',
        'params'   => ['country' => 'country'],
    ],
],
'municipality' => [
    'id'       => 'municipality',
    'type'     => 'select',
    'label'    => __('Municipality', 'my-plugin'),
    'depends_on' => ['country', 'district'],
    'required_if' => [
        ['field' => 'district', 'operator' => 'truthy'],
    ],
    'options_provider' => [
        'endpoint' => 'country_municipalities',
        'params'   => [
            'country' => 'country',
            'subdivision' => 'district',
        ],
    ],
],
```

Supported field-level keys:

- `depends_on` (`string[]`): declarative parent field IDs.
- `visibility_rules` (`array`): conditional visibility rules (same grammar as `conditions`).
- `options_provider` (`array`): async options source (`endpoint`, optional `action`, `params`, `mapping`).
- `required_if` (`array`): declarative required conditions evaluated on change and save.

Behavior:

- Runs without page reload.
- Inline validation triggers on change.
- Save is blocked when dependency/required/provider rules fail.
- If async provider fails, field keeps existing local/static options.

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
| `date` | `string` (`Y-m-d`) | Native date input — see §4a |
| `datetime` | `string` (`Y-m-d H:i`) | Calendar + time picker — see §4b |
| `html` | — | Read-only HTML block; use `html` key |

### §4a — `date` field

Renders a native `<input type="date">`. The canonical saved value is a single string in `Y-m-d` format.

```php
'club_founding_date' => array(
    'id'          => 'club_founding_date',
    'type'        => 'date',
    'label'       => __( 'Data de Fundação', 'acro-manager' ),
    'description' => __( 'Founding Date (YYYY-MM-DD)', 'acro-manager' ),
    'default'     => '2026-01-01',
),
```

**Field options:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `default` | `string` | `''` | Pre-fill value in `Y-m-d` format |
| `required` | `bool` | `false` | Blocks save when empty |
| `min` | `string` | `''` | Optional minimum date (`Y-m-d`) |
| `max` | `string` | `''` | Optional maximum date (`Y-m-d`) |

**Validation rules:**

- If `required => true` and value is empty → validation error.
- If a value is present it must match `Y-m-d` and be a valid calendar date.

**Sanitization:**

- Non-empty values are normalized to `Y-m-d`.
- Empty string is stored as-is (allows clearing an optional field).
- Invalid values fall back to the field `default` when one is provided, otherwise `''`.

### §4b — `datetime` field

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

### §4c — `image_select` schema

```php
'options' => [
    'grid' => ['src' => 'https://example.com/grid.png', 'label' => 'Grid'],
    'list' => ['src' => 'https://example.com/list.png', 'label' => 'List'],
]
```

Invalid schema combinations are logged as warnings.

## 5. Cascading Validation and Save Blocking

- Validation runs on field change (`rl_options_framework_field_validate`) and on save.
- Field-level errors are shown inline and save is blocked until fixed.
- For provider-backed selects, submitted values must exist in resolved options.
- `required_if` rules are evaluated server-side using current submitted form state.

## 6. Global Country Reference Service

The framework ships a global country reference layer with transient caching, source timeout, and graceful fallback.

- Base metadata keys: `code`, `name`, `region`, `capital`
- Optional hierarchies: subdivisions and municipalities
- Empty/error source responses are cached for short TTL only
- Sources are pluggable via filters

### REST Endpoints

- `GET /wp-json/rl-options/v1/countries`
- `GET /wp-json/rl-options/v1/countries/{code}/subdivisions`
- `GET /wp-json/rl-options/v1/countries/{code}/municipalities?subdivision=...`

### Helper Functions

- `rl_options_framework_get_countries()`
- `rl_options_framework_get_country_subdivisions($country_code)`
- `rl_options_framework_get_country_municipalities($country_code, $subdivision = '')`

### Extensibility Hooks

Filters:

- `rl_options_framework_country_reference_sources`
- `rl_options_framework_country_reference_data`
- `rl_options_framework_country_subdivisions`
- `rl_options_framework_country_municipalities`
- `rl_options_framework_resolved_provider_options`

Actions:

- `rl_options_framework_country_reference_warmed`
- `rl_options_framework_field_dependency_resolved`

## 7. Validation Behavior

- Fields are optional by default.
- `required => true` enables required validation.
- Validation errors return structured metadata in AJAX responses:
  - `field_id`
  - `field_label`
  - `tab_id`
  - `section_id`
  - `message`

## 8. Save UX and Resilience

- AJAX save uses SweetAlert when available.
- If SweetAlert is missing/fails and `swal_fallback` is enabled, framework falls back to `window.alert`.
- On validation error, JS focuses the first invalid field and activates its tab/section.

## 9. Migration Note (Hook Naming)

If you previously used custom hook names like `my_plugin_settings_tabs`, migrate to:

- `my_plugin_options_framework_tabs` (where `my_plugin_options` is `option_name`)

The framework runtime only reads `{option_name}_framework_tabs`.
