# RL Options Framework Documentation

> Portable WordPress options framework for plugins/themes with validation, async providers, and reusable geo metadata APIs.

---

## 1. Installation

### What it is

Load and initialize the framework in your plugin or theme context.

### How to use

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

---

## 2. Canonical Hooks

### What it is

Runtime hook names are derived from `option_name`.

### Hook map

| Hook Type | Pattern | Example |
|----------|---------|---------|
| Tabs filter | `{option_name}_framework_tabs` | `my_project_options_framework_tabs` |
| Boot action | `{option_name}_framework_boot` | `my_project_options_framework_boot` |

### Example

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

---

## 3. Configuration

### Options

| Option | Values | Description |
|--------|--------|-------------|
| `context` | `auto`, `plugin`, `theme` | Asset URL resolution strategy |
| `register_menu` | `true`, `false` | Let framework register menu/submenu |
| `sync_history` | `true`, `false` | Enable tab history sync |
| `debug_level` | `error`, `warn`, `info`, `debug` | JS/PHP runtime logging level |
| `swal_fallback` | `true`, `false` | Fallback to `window.alert` when SweetAlert fails |
| `use_local_assets_toggle` | `true`, `false` | Show support toggle for framework-owned local/CDN assets |
| `local_assets_field_id` | `string` | Option key used by local-assets toggle |

### Notes

- If `register_menu` is `false`, host code must wire menu callback to `$framework->render_page()`.
- If `assets_url` is provided, it takes precedence over context-based resolution.
- `use_local_assets_toggle` only affects RL Options Framework libraries, not unrelated plugin/theme assets.

---

## 4. Field Types

### Field Architecture (Best Practice)

Framework fields now follow an ACF-style architecture:

- Single responsibility: each field type has its own class file under [fields/class-rl-field-*.php](fields).
- Registry/dispatcher: renderers are resolved through [fields/class-rl-field-registry.php](fields/class-rl-field-registry.php).
- Bootstrap loader: built-in renderers are registered in [fields/class-rl-field-bootstrap.php](fields/class-rl-field-bootstrap.php).
- Backward compatible: unknown types still fall back to legacy rendering path.
- Service-based core: the orchestration layer delegates to 4 independent services (Render, Admin Handler, Schema Manager, REST API). See [Section 11](#11-internal-service-architecture).

This makes field maintenance and additions safer than editing a single large switch statement.

### Register custom field renderer

```php
class My_Project_Field_Rating implements RL_Field_Interface {
    public function type(): string {
        return 'rating';
    }

    public function render(array $field, $value, array $context = []): void {
        $input_id = (string) ($context['input_id'] ?? '');
        $field_name = (string) ($context['field_name'] ?? '');
        printf('<input type="number" min="1" max="5" id="%1$s" name="%2$s" value="%3$s" />', esc_attr($input_id), esc_attr($field_name), esc_attr((string) $value));
    }
}

add_action('my_project_options_framework_boot', function(RL_Options_Framework $framework) {
    $framework->register_field_renderer(new My_Project_Field_Rating());
});
```

### Best-practice notes

- Keep renderers output-only (no save logic inside renderer classes).
- Keep sanitize/validate logic in framework validation pipeline.
- Use preset/bundle registry for reusable field config, not renderer classes.
- Add new field types by creating a new file in [fields](fields) and registering it.

### Type contract

| Type | Saved Value | Notes |
|------|-------------|-------|
| `toggle` | `bool` | On/off switch |
| `checkbox` | `bool` | Single checkbox with `text` |
| `select` | `string` | Requires options map |
| `multiselect` | `string[]` | Requires options map |
| `radio` | `string` | Requires options map |
| `image_select` | `string` | Option schema `['src','label']` |
| `text` | `string` | Standard input |
| `textarea` | `string` | Multi-line input |
| `number` | `int|float` | Supports `min`, `max`, `step` |
| `color` | `string` | Hex / rgb / rgba |
| `image` | `string` | URL from media picker |
| `country` | `string` | ISO2 country code |
| `state` | `string` | Subdivision code/name key |
| `city` | `string` | Municipality code/name key |
| `country_state_city` | `array` | Combined location payload |
| `date` | `string` | `Y-m-d` |
| `datetime` | `string` | `Y-m-d H:i` |
| `html` | n/a | Read-only HTML block |

### 4.1 `country` field

#### What it is

Single dropdown with all normalized countries.

#### How to use

```php
'country' => [
    'id'    => 'country_field',
    'type'  => 'country',
    'label' => __('Country', 'my-plugin'),
],
```

#### Saved format

```php
'PT' // ISO2 country code
```

### 4.2 `state` field

#### What it is

Subdivision dropdown tied to either a fixed country or another country field.

#### How to use (fixed country)

```php
'state' => [
    'id'      => 'state_field',
    'type'    => 'state',
    'country' => 'pt',
    'label'   => __('Distrito', 'acro-manager'),
],
```

#### How to use (linked country field)

```php
'state' => [
    'id'            => 'state_field',
    'type'          => 'state',
    'country_field' => 'country_field',
    'label'         => __('State', 'my-plugin'),
],
```

### 4.3 `city` field

#### What it is

Municipality dropdown tied to fixed or linked country/subdivision.

#### How to use

```php
'city' => [
    'id'      => 'city_field',
    'type'    => 'city',
    'country' => 'pt',
    'label'   => __('Localidade', 'acro-manager'),
],
```

### 4.4 `country_state_city` field

#### What it is

Combined Country > State > City field rendered as three coordinated selects.

#### How to use

```php
'club_location' => [
    'id'            => 'club_location',
    'type'          => 'country_state_city',
    'label'         => __('Location', 'my-plugin'),
    'country_label' => __('Pais', 'my-plugin'),
    'state_label'   => __('Distrito', 'my-plugin'),
    'city_label'    => __('Localidade', 'my-plugin'),
],
```

#### Saved format

```php
[
    'country' => 'PT',
    'state'   => 'lisboa',
    'city'    => 'sintra',
]
```

### 4.5 `date` field

#### What it is

Native HTML date input.

#### How to use

```php
'club_founding_date' => [
    'id'          => 'club_founding_date',
    'type'        => 'date',
    'label'       => __('Data de Fundacao', 'acro-manager'),
    'description' => __('Founding Date (YYYY-MM-DD)', 'acro-manager'),
    'default'     => '2026-01-01',
],
```

#### Field options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `default` | `string` | `''` | Initial value in `Y-m-d` |
| `required` | `bool` | `false` | Enforce non-empty |
| `min` | `string` | `''` | Minimum `Y-m-d` |
| `max` | `string` | `''` | Maximum `Y-m-d` |

#### Behavior

- Strict regex validation: `^\d{4}-\d{2}-\d{2}$`.
- Calendar date validity checked server-side.
- Stored format is always `Y-m-d`.

### 4.6 `datetime` field

#### What it is

jQuery UI datepicker + native time input.

#### How to use

```php
'launch_at' => [
    'id'          => 'launch_at',
    'type'        => 'datetime',
    'label'       => __('Launch Date & Time', 'my-plugin'),
    'description' => __('Date the feature becomes active.', 'my-plugin'),
    'default'     => '2026-01-01 09:00',
    'required'    => false,
    'time_step'   => 900,
],
```

#### Behavior

- Backward compatible with existing `Y-m-d H:i` values.
- No migration needed for existing datetime fields.
- Datepicker localization uses `wp_localize_jquery_ui_datepicker()`.

### 4.7 `image_select` schema

```php
'options' => [
    'grid' => ['src' => 'https://example.com/grid.png', 'label' => 'Grid'],
    'list' => ['src' => 'https://example.com/list.png', 'label' => 'List'],
]
```

---

## 5. Declarative Dependencies and Async Providers

### What it is

Opt-in dependency engine for reactive field visibility/options/validation without reloading the page.

### Supported field keys

| Key | Type | Purpose |
|-----|------|---------|
| `depends_on` | `string[]` | Declares parent fields |
| `visibility_rules` | `array` | Declarative visibility logic |
| `options_provider` | `array` | Async options source config |
| `required_if` | `array` | Conditional required rules |

### Example

```php
'country' => [
    'id'    => 'country',
    'type'  => 'country',
],
'district' => [
    'id'          => 'district',
    'type'        => 'state',
    'country_field' => 'country',
    'depends_on'  => ['country'],
    'required_if' => [
        ['field' => 'country', 'operator' => 'truthy'],
    ],
],
'municipality' => [
    'id'               => 'municipality',
    'type'             => 'city',
    'country_field'    => 'country',
    'subdivision_field'=> 'district',
    'depends_on'       => ['country', 'district'],
],
```

### Behavior

- Runs on change and on save.
- Shows inline field errors.
- Blocks save when dependency validation fails.
- Falls back to static/local options if provider request fails.

---

## 6. Framework Preset and Bundle Tools

### What it is

The framework provides a neutral preset/bundle registry. It does not ship business presets.
Theme/plugin developers register their own reusable field definitions and bundle resolvers.

### Registry API

```php
$framework->register_field_preset('my_email', [
    'id' => 'contact_email',
    'type' => 'email',
    'label' => __('Email', 'my-plugin'),
]);

$framework->register_field_bundle('contact_bundle', function(array $config, $registry): array {
    return [
        $registry->get_preset('my_email'),
    ];
});

$framework->add_preset_field('general', 'main', 'my_email');
$framework->add_bundle_fields('general', 'main', 'contact_bundle');
```

### Preset registry access

```php
$registry = $framework->presets();
$field = $registry ? $registry->get_preset('my_email', ['id' => 'support_email']) : [];
```

---

## 7. Typed Field Aliases

### What it is

Aliases map semantic field types to a base field with built-in sanitize and validate behavior.

### Supported aliases

| Alias | Base Type | Built-in behavior |
|------|-----------|-------------------|
| `email` | `text` | `sanitize_email` + `is_email` |
| `phone` | `text` | phone regex validate + tel attributes |
| `postal_code` | `text` | postal format regex validate |
| `url` | `text` | `esc_url_raw` + `wp_http_validate_url` |
| `nif` | `text` | PT NIF sanitize + checksum validate |

### Example

```php
'club_email' => [
    'id' => 'club_email',
    'type' => 'email',
],
'club_phone' => [
    'id' => 'club_phone',
    'type' => 'phone',
],
```

---

## 8. Provider Shortcut and Schema Defaults

### Provider shortcut

You can use shorthand syntax:

```php
'district' => [
    'id' => 'district',
    'type' => 'select',
    'provider' => 'subdivisions',
],
```

Framework normalizes to:

```php
'options_provider' => [
    'endpoint' => 'subdivisions',
]
```

### Callback aliases

- `sanitize` is normalized to `sanitize_callback` when callable.
- `validate` is normalized to `validate_callback` when callable.

### Pattern normalization

If `pattern` is provided without regex delimiters, framework wraps it as anchored regex automatically.

---

## 6. Country Reference Service and API Layer

### What it is

Global geo reference service (`RL_Options_Rest_Api`) with normalized outputs for framework and external consumers. Logic lives in `services/class-rl-options-rest-api.php`. The framework exposes proxy methods for backward compatibility.

### Data model keys

- `code`
- `name`
- `region`
- `capital`

### Caching behavior

- Transient cache is effectively eternal by default (`TTL = 0`).
- Data refreshes when transients are cleared or cache payload is empty.
- Source timeout and graceful fallback are built-in.

### REST endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/rl-options/v1/countries` | `GET` | Country list |
| `/wp-json/rl-options/v1/countries/{code}/subdivisions` | `GET` | Country subdivisions |
| `/wp-json/rl-options/v1/countries/{code}/municipalities?subdivision=...` | `GET` | Municipalities |

### PHP helper functions

```php
rl_options_framework_get_countries();
rl_options_framework_get_country_subdivisions('PT');
rl_options_framework_get_country_municipalities('PT', 'lisboa');
```

### Extensibility hooks

#### Filters

- `rl_options_framework_country_reference_sources`
- `rl_options_framework_country_reference_data`
- `rl_options_framework_country_subdivisions`
- `rl_options_framework_country_municipalities`
- `rl_options_framework_resolved_provider_options`

#### Actions

- `rl_options_framework_country_reference_warmed`
- `rl_options_framework_field_dependency_resolved`

---

## 9. Validation and Save UX

### Validation behavior

- Fields are optional by default.
- `required => true` enforces required validation.
- `required_if` adds dependency-aware required validation.
- AJAX responses include field-level metadata (`field_id`, `field_label`, `tab_id`, `section_id`, `message`).

### Save behavior

- AJAX save uses SweetAlert when available.
- If SweetAlert fails and `swal_fallback` is enabled, fallback is `window.alert`.
- On validation errors, framework focuses invalid field and opens the relevant tab/section.

---

## 10. Security and Performance Notes

### Security

- All AJAX endpoints validate nonce and user capability.
- Incoming request params are sanitized before use.

### Performance

- Geo metadata is cached in transients.
- Empty payloads are not persisted as long-lived cache values.
- Remote source requests use timeout and fallback strategy.

---

## 11. Internal Service Architecture

### What it is

The framework core (`class-rl-options-framework.php`) is an orchestration layer. All feature domains are delegated to dedicated service classes under `services/`.

### Service map

| Service class | File | Responsibility |
|---|---|---|
| `RL_Options_Render_Service` | `services/class-rl-options-render-service.php` | Admin page rendering, field output, tooltip formatting |
| `RL_Options_Admin_Handler` | `services/class-rl-options-admin-handler.php` | Form save (POST), AJAX save, AJAX field options, AJAX inline validation |
| `RL_Options_Schema_Manager` | `services/class-rl-options-schema-manager.php` | Tab/section/field normalization, schema building, condition filtering |
| `RL_Options_Rest_Api` | `services/class-rl-options-rest-api.php` | REST route registration, geo reference data, transient caching |

### Service access

All services are accessible via framework accessor methods after `init()` is called:

```php
$framework->rest_api();      // RL_Options_Rest_Api
```

### REST API service — public methods

The `RL_Options_Rest_Api` service exposes geo data methods for use in field providers, custom endpoints, or third-party integrations:

```php
$rest = $framework->rest_api();

// Countries: [{code, name, region, capital}]
$countries = $rest->get_country_reference_countries();

// Raw dataset: ['PT' => ['code', 'name', 'region', 'capital'], ...]
$data = $rest->get_country_reference_data();

// Subdivisions: [{value, label}]
$states = $rest->get_country_subdivisions_data('PT');

// Municipalities: [{value, label}]
$cities = $rest->get_country_municipalities_data('PT', 'lisboa');
```

The same methods are also proxied on the framework instance for backward compatibility:

```php
$framework->get_country_reference_countries();
$framework->get_country_subdivisions('PT');
$framework->get_country_municipalities('PT', 'lisboa');
```

### normalize_options_for_transport()

This utility method is public on `RL_Options_Framework` so services and custom field renderers can normalize their options into the `[{value, label}]` transport format:

```php
$options = $framework->normalize_options_for_transport([
    'PT' => 'Portugal',
    'ES' => 'Spain',
]);
// → [['value' => 'PT', 'label' => 'Portugal'], ['value' => 'ES', 'label' => 'Spain']]
```

---

## 12. Migration Notes

### Hook naming

Old custom naming:

```text
my_plugin_settings_tabs
```

Framework canonical naming:

```text
my_plugin_options_framework_tabs
```

The framework runtime reads only `{option_name}_framework_tabs`.
