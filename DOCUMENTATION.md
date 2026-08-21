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
| Before reset action | `rl_options_before_reset_{option_name}` | `rl_options_before_reset_my_project_options` |
| After reset action | `rl_options_after_reset_{option_name}` | `rl_options_after_reset_my_project_options` |

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
| `html` | n/a | Read-only HTML block sanitized with `wp_kses` |

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

### 4.7 `html` field

#### What it is

Read-only HTML content block for admin-only help, notices, and structured markup.

#### How to use

```php
'help_text' => [
    'id'   => 'help_text',
    'type' => 'html',
    'html' => '<div class="notice notice-info"><p>' . __( 'This is an info box.', 'my-plugin' ) . '</p></div>',
],
```

#### Supported keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `html` | `string` | `''` | HTML markup rendered inside the field wrapper |
| `allowed_html` | `array` | `wp_kses_allowed_html('post')` | Optional `wp_kses` allowlist override |

#### Saved value format

- No option value is stored. The field is render-only.

#### Validation and sanitization rules

- The `html` field does not participate in save sanitization because it is not persisted.
- Rendered markup is sanitized with `wp_kses()`.
- Default allowed tags use `wp_kses_allowed_html('post')`.
- You may override the allowlist per field with `allowed_html` or globally with the `rl_options_framework_html_allowed_html` filter.
- Do not rely on this field for arbitrary trusted admin HTML; explicit allowlists are required for non-standard markup.

### 4.8 `image_select` schema

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
- Imports are validated and sanitized against the registered field schema before being saved.
- The `html` field sanitizes rendered markup with `wp_kses()` and a configurable allowlist.
- Remote geo reference sources are fetched over HTTPS with `wp_safe_remote_get()`.
- Logger context redacts sensitive keys such as nonce/token/password/secret values.

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

---

## 13. Requirements & Dependencies

### PHP

- **Minimum:** PHP 7.4+
- **Recommended:** PHP 8.0+
- Required extensions: `json`, `filter`

### WordPress

- **Minimum:** WordPress 5.0+
- **Recommended:** WordPress 6.0+
- Admin context required (framework only works in wp-admin)

### Browsers

| Browser | Support |
|---------|---------|
| Chrome | Latest 2 versions |
| Firefox | Latest 2 versions |
| Safari | Latest 2 versions |
| Edge | Latest 2 versions |
| IE 11 | Not supported |

---

## 14. Complete Field Definition Schema Reference

### Universal field properties

Every field accepts these properties:

| Property | Type | Default | Required | Description |
|----------|------|---------|----------|-------------|
| `id` | `string` | — | ✅ | Unique field identifier within section |
| `type` | `string` | — | ✅ | Field type (see type reference below) |
| `label` | `string` | `''` | ❌ | Display label shown to admin |
| `desc` / `description` | `string` | `''` | ❌ | Help text below field |
| `placeholder` | `string` | `''` | ❌ | Input placeholder text |
| `default` | mixed | `null` | ❌ | Default value if option not saved |
| `required` | `bool` | `false` | ❌ | Enforce non-empty on save |
| `required_if` | `array` | `[]` | ❌ | Conditional required rules (see Section 5) |
| `depends_on` | `array` | `[]` | ❌ | List of parent field IDs |
| `conditions` | `array` | `[]` | ❌ | Visibility rules (JS frontend / Backend filtering) |
| `sanitize_callback` | `callable` | `null` | ❌ | Custom sanitize function |
| `sanitize` | `callable` | `null` | ❌ | Alias for sanitize_callback |
| `validate_callback` | `callable` | `null` | ❌ | Custom validation function |
| `validate` | `callable` | `null` | ❌ | Alias for validate_callback |
| `class` | `string` | `''` | ❌ | CSS classes for wrapper |
| `input_class` | `string` | `''` | ❌ | CSS classes for input element |
| `attr` | `array` | `[]` | ❌ | Additional HTML attributes |
| `tooltip` | `string` | `''` | ❌ | Inline help (shows on hover) |

### Type-specific properties

#### `text` / `email` / `url` / `phone` / `postal_code` / `nif`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `maxlength` | `int` | `''` | Character limit |
| `pattern` | `string` | `''` | Regex (anchored automatically) |
| `input_type` | `string` | `text` | HTML input type attribute |

#### `textarea`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `rows` | `int` | `4` | Number of rows |
| `maxlength` | `int` | `''` | Character limit |

#### `number`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `min` | `float\|int` | `''` | Minimum allowed value |
| `max` | `float\|int` | `''` | Maximum allowed value |
| `step` | `float\|int` | `1` | Increment step |
| `fallback` | `float\|int` | `0` | Value if invalid (non-numeric) |

#### `select` / `multiselect` / `radio` / `checkbox`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `options` | `array` | `[]` | Static key-value options |
| `options_provider` | `array` | `[]` | Async provider config (see Section 5) |
| `provider` | `string` | `''` | Shorthand for provider endpoint |

#### `html`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `html` | `string` | `''` | Render-only HTML markup |
| `allowed_html` | `array` | `wp_kses_allowed_html('post')` | Optional `wp_kses` allowlist for rendered markup |

#### `country` / `state` / `city`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `country` | `string` | `''` | Fixed ISO2 country code |
| `country_field` | `string` | `''` | Field ID to link country |
| `subdivision_field` | `string` | `''` | Field ID to link state (for city only) |

#### `country_state_city`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `country_label` | `string` | `'Country'` | Label for country select |
| `state_label` | `string` | `'State'` | Label for state select |
| `city_label` | `string` | `'City'` | Label for city select |

#### `date` / `datetime`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `min` | `string` | `''` | Minimum date (Y-m-d) |
| `max` | `string` | `''` | Maximum date (Y-m-d) |
| `time_step` | `int` | `900` | Time increment in seconds (datetime only) |

#### `color`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `palette` | `array` | `[]` | Predefined color palette |
| `alpha` | `bool` | `true` | Allow alpha channel (rgba) |

#### `image` / `image_select`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `button_label` | `string` | `'Select Image'` | Media picker button text |

#### `html`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `content` | `string` | `''` | HTML content to display |

#### `toggle` / `checkbox`

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `text` | `string` | `''` | Label text next to checkbox/toggle |

---

## 15. Public API Reference

### Constructor & Initialization

```php
public function __construct(array $config = []): void
```

Initialize framework with configuration array. See Section 3 for config keys.

```php
public function init($plugin = null): void
```

Boot framework: lock config, instantiate services, register hooks. Call once in `plugins_loaded` or `after_setup_theme`.

```php
public function boot($plugin): void
```

Register WordPress menu/submenu and AJAX handlers. Called automatically by `init()` if `register_menu` is `true`.

### Tab/Section/Field Registration

```php
public function add_tab(array $config): void
public function set_tabs(array $tabs): void

public function add_section(array $config): void

public function add_field(array $config): void
```

Register tabs, sections, and fields. Can be called anytime before rendering.

**Keys:**

- Tab: `id` (string), `label` (string), `priority` (int), `sections` (array), `conditions` (array)
- Section: `tab_id` (string), `id` (string), `title` (string), `class` (string), `conditions` (array)
- Field: See Section 14

### Preset & Bundle Registry

```php
public function register_field_preset(string $preset_id, array $definition): void
```

Register reusable field configuration. Usage: `$framework->add_preset_field('tab_id', 'section_id', 'preset_id')`.

```php
public function register_field_bundle(string $bundle_id, callable $resolver): void
```

Register field bundle resolver. Resolver signature:

```php
function($config, $registry): array { return [/* fields */]; }
```

```php
public function add_preset_field(string $tab_slug, string $section_id, string $preset_id, array $overrides = []): void

public function add_bundle_fields(string $tab_slug, string $section_id, string $bundle_id, array $config = []): void
```

Add preset or bundle instance to a section.

```php
public function presets(): ?RL_Field_Presets
```

Get preset registry accessor.

### Option Access

```php
public function get_option(string $key = '', $default = null): mixed
public function get_options(): array
```

Retrieve single option or all options. Maps to `get_option('{option_name}')` internally.

```php
public function set_option(string $key, $value): bool
```

Update single option.

```php
public function reset_to_defaults(): bool
```

Reset all framework options to their default values, creating a backup first. Fires `rl_options_before_reset` and `rl_options_after_reset` hooks.

### Service Accessors

```php
public function rest_api(): ?RL_Options_Rest_Api
```

Access REST API service for geo data. See Section 11.

```php
public function field_registry(): ?RL_Field_Registry
```

Access field type registry.

### Field Renderer Registration

```php
public function register_field_renderer(RL_Field_Interface $renderer): void
```

Register custom field type. Callback must implement `RL_Field_Interface`:

```php
interface RL_Field_Interface {
    public function type(): string;
    public function render(array $field, $value, array $context = []): void;
}
```

### Rendering & Display

```php
public function render_page(): void
```

Render admin options page. Called by WordPress menu callback if `register_menu` is `true`.

```php
public function get_country_reference_countries(): array

public function get_country_subdivisions(string $country_code): array

public function get_country_municipalities(string $country_code, string $subdivision): array
```

Retrieve geo reference data. See Section 6.

```php
public function normalize_options_for_transport(array $options, array $mapping = []): array
```

Normalize to `[{value, label}]` format. Used internally; available for custom renderers.

### Validation & Hooks

```php
public function filter_tabs_by_conditions(array $tabs, array $options): array
```

Apply visibility rules. Filters tabs based on `conditions`.

```php
public function register_menu(): void
```

Register WordPress menu/submenu manually if `register_menu` is `false`.

---

## 16. Validation Rules & Required-If Syntax

### Dependency operators

| Operator | Type | Example | Meaning |
|----------|------|---------|---------|
| `equals` | `string\|int\|bool` | `['field' => 'status', 'operator' => 'equals', 'expected' => 'active']` | Field value equals exactly |
| `in` | `array` | `['field' => 'role', 'operator' => 'in', 'expected' => ['admin', 'editor']]` | Field value is one of |
| `empty` | `bool` | `['field' => 'optional_text', 'operator' => 'empty', 'expected' => true]` | Field is empty/falsy |
| `truthy` | `bool` | `['field' => 'has_details', 'operator' => 'truthy', 'expected' => true]` | Field is truthy |
| `contains` | `string` | `['field' => 'tags', 'operator' => 'contains', 'expected' => 'featured']` | Field value contains string |
| `match` | `string` | `['field' => 'email', 'operator' => 'match', 'expected' => '^[^@]+@.+$']` | Regex match (anchored) |

### Example: Complex required_if

```php
'newsletter_frequency' => [
    'id'    => 'newsletter_frequency',
    'type'  => 'select',
    'label' => __('Newsletter Frequency', 'my-plugin'),
    'options' => [
        'weekly'  => 'Weekly',
        'monthly' => 'Monthly',
        'never'   => 'Never',
    ],
    'required_if' => [
        // Required if email is not empty AND newsletter toggle is ON
        ['field' => 'email', 'operator' => 'truthy', 'expected' => true],
        ['field' => 'enable_newsletter', 'operator' => 'equals', 'expected' => true],
    ],
],
```

---

## 17. Callback Signatures

### Custom sanitize callback

```php
/**
 * @param mixed $value Current value to sanitize
 * @param array $field Field definition
 * @return mixed Sanitized value
 */
function my_custom_sanitize($value, $field) {
    // Return sanitized value
    return sanitize_text_field($value);
}

'my_field' => [
    'id' => 'my_field',
    'type' => 'text',
    'sanitize_callback' => 'my_custom_sanitize',
]
```

### Custom validate callback

```php
/**
 * @param mixed $value Value to validate
 * @param array $field Field definition
 * @param array $state All form values
 * @return array|true Validation result: true for pass, [ 'error' => 'message' ] for fail
 */
function my_custom_validate($value, $field, $state) {
    if (empty($value)) {
        return true; // Let required rule handle it
    }
    
    if (strlen($value) < 3) {
        return ['error' => 'Value must be at least 3 characters'];
    }
    
    return true;
}

'my_field' => [
    'id' => 'my_field',
    'type' => 'text',
    'validate_callback' => 'my_custom_validate',
]
```

### Field bundle resolver

```php
function my_bundle_resolver(array $config, $registry) {
    $label_prefix = $config['label_prefix'] ?? '';
    
    return [
        [
            'id' => 'first_name',
            'type' => 'text',
            'label' => $label_prefix . ' First Name',
        ],
        [
            'id' => 'last_name',
            'type' => 'text',
            'label' => $label_prefix . ' Last Name',
        ],
    ];
}

$framework->register_field_bundle('name_pair', 'my_bundle_resolver');
```

---

## 18. Common Patterns & Recipes

### Pattern 1: Multi-step form with conditional sections

```php
$framework->add_tab([
    'id'    => 'onboarding',
    'label' => __('Onboarding', 'my-plugin'),
]);

$framework->add_section([
    'tab_id' => 'onboarding',
    'id'     => 'step1_business',
    'title'  => __('Business Type', 'my-plugin'),
]);

$framework->add_field([
    'tab_id'     => 'onboarding',
    'section_id' => 'step1_business',
    'id'         => 'business_type',
    'type'       => 'select',
    'label'      => __('Business Type', 'my-plugin'),
    'options'    => [
        'partnership'   => 'Partnership',
        'sole_trader'   => 'Sole Trader',
        'company'       => 'Limited Company',
    ],
]);

// Show only for partnerships
$framework->add_section([
    'tab_id'     => 'onboarding',
    'id'         => 'step2_partners',
    'title'      => __('Partner Details', 'my-plugin'),
    'conditions' => [
        ['field' => 'business_type', 'operator' => 'equals', 'value' => 'partnership'],
    ],
]);

$framework->add_field([
    'tab_id'       => 'onboarding',
    'section_id'   => 'step2_partners',
    'id'           => 'num_partners',
    'type'         => 'number',
    'label'        => __('Number of Partners', 'my-plugin'),
    'required_if'  => [
        ['field' => 'business_type', 'operator' => 'equals', 'expected' => 'partnership'],
    ]
]);
```

### Pattern 3: Nested Conditions (AND / OR logic)

The `conditions` array supports nested groups with explicit `relation` keys (`AND` or `OR`). This allows you to build complex visibility logic.

```php
$framework->add_field([
    'tab_id'     => 'main',
    'section_id' => 'section_global',
    'id'         => 'custom_banner',
    'type'       => 'image',
    'label'      => __('Custom Banner', 'my-plugin'),
    'conditions' => [
        'relation' => 'OR', // Either condition must be true
        ['field' => 'display_style', 'operator' => 'equals', 'value' => 'custom'],
        [
            'relation' => 'AND', // Nested AND group
            ['field' => 'display_style', 'operator' => 'equals', 'value' => 'automatic'],
            ['field' => 'override_automatic', 'operator' => 'equals', 'value' => '1'],
        ]
    ]
]);
```

### Pattern 4: Geographic location form with cascading selects

```php
$framework->add_field([
    'tab_id'       => 'location',
    'section_id'   => 'address',
    'id'           => 'office_country',
    'type'         => 'country',
    'label'        => __('Country', 'my-plugin'),
    'required'     => true,
]);

$framework->add_field([
    'tab_id'        => 'location',
    'section_id'    => 'address',
    'id'            => 'office_region',
    'type'          => 'state',
    'label'         => __('Region / State', 'my-plugin'),
    'country_field' => 'office_country',
    'depends_on'    => ['office_country'],
    'required_if'   => [
        ['field' => 'office_country', 'operator' => 'truthy', 'expected' => true],
    ],
]);

$framework->add_field([
    'tab_id'            => 'location',
    'section_id'        => 'address',
    'id'                => 'office_city',
    'type'              => 'city',
    'label'             => __('City', 'my-plugin'),
    'country_field'     => 'office_country',
    'subdivision_field' => 'office_region',
    'depends_on'        => ['office_country', 'office_region'],
]);
```

### Pattern 3: Email subscription with validation

```php
$framework->add_field([
    'tab_id'     => 'general',
    'section_id' => 'contact',
    'id'         => 'contact_email',
    'type'       => 'email',
    'label'      => __('Contact Email', 'my-plugin'),
    'required'   => true,
    'validate_callback' => function($value, $field, $state) {
        // Built-in email validation handles basic checks
        // Add custom checks here
        if (strpos($value, '+') !== false) {
            return ['error' => 'Email aliases (+) not supported'];
        }
        return true;
    },
]);

$framework->add_field([
    'tab_id'     => 'general',
    'section_id' => 'contact',
    'id'         => 'subscribe_newsletter',
    'type'       => 'toggle',
    'label'      => __('Subscribe to Newsletter', 'my-plugin'),
    'text'       => __('Yes, subscribe me', 'my-plugin'),
]);

$framework->add_field([
    'tab_id'      => 'general',
    'section_id'  => 'contact',
    'id'          => 'newsletter_frequency',
    'type'        => 'radio',
    'label'       => __('Newsletter Frequency', 'my-plugin'),
    'options'     => [
        'daily'   => 'Daily Digest',
        'weekly'  => 'Weekly Newsletter',
        'monthly' => 'Monthly Summary',
    ],
    'depends_on'     => ['subscribe_newsletter'],
    'required_if'    => [
        ['field' => 'subscribe_newsletter', 'operator' => 'equals', 'expected' => true],
    ],
]);
```

---

## 19. Troubleshooting & FAQ

### Issue: Fields not appearing

**Check:**
1. Is `init()` called on `plugins_loaded`?
2. Are tab/section IDs spelled consistently?
3. Are `$section_id` keys matching between `add_section` and `add_field`?
4. Run: `echo json_encode($framework->rest_api()->get_schema());` to inspect schema

### Issue: Validation not firing

**Check:**
1. Is `required` or `required_if` set?
2. Is custom `validate_callback` returning true for valid, array for errors?
3. Check browser console for AJAX errors
4. Verify nonce is present: `wp_nonce_field('rl_options_save', 'rl_options_nonce')`

### Issue: Async options not loading

**Check:**
1. Is REST API registered? `get_transient('rl_options_rest_initialized')`
2. Is `options_provider` config correct? Should have `endpoint` key
3. Check Network tab: is `/wp-json/rl-options/v1/*` responding?
4. Is field `depends_on` correct?

### Issue: Geo fields (country/state/city) showing no options

**Check:**
1. Is REST API warmed? `get_transient('rl_options_geo_countries')`
2. Try clearing transients: `delete_transients_like('rl_options%')`
3. Check `RL_Logger` debug output if `debug_level` is `debug`
4. Is data source responding? `curl https://restcountries.com/v3.1/all?fields=cca2,name,region,capital`

### Q: Can I use this outside WordPress admin?

**A:** No. Framework is designed for admin context only (tabs, admin styles, nonces). For frontend use, integrate only the field type classes.

### Q: Does framework track save history?

**A:** No. Use backup/restore features or implement custom logging with `rl_options_framework_option_saved` hook.

### Q: Can I migrate from older framework versions?

**A:** Yes. Hook names changed; update any `my_plugin_settings_tabs` filters to `my_plugin_options_framework_tabs`. See Section 12.

### Q: How do I add custom CSS?

**A:** Use `'class'` and `'input_class'` on fields, or hook `wp_enqueue_scripts` to load theme CSS.

---

## 20. Error Messages Reference

### Validation errors

| Error | Trigger | Message |
|-------|---------|---------|
| `field_required` | `required: true` + empty | `"This field is required"` |
| `field_required_if` | `required_if` rules matched + empty | `"This field is required"` |
| `field_invalid_email` | `type: 'email'` + invalid format | `"Invalid email address"` |
| `field_invalid_url` | `type: 'url'` + invalid URL | `"Invalid URL"` |
| `field_invalid_phone` | `type: 'phone'` + invalid format | `"Invalid phone number"` |
| `field_invalid_nif` | `type: 'nif'` + invalid PT NIF | `"Invalid NIF"` |
| `field_invalid_date` | `type: 'date'` + invalid format | `"Invalid date (YYYY-MM-DD)"` |
| `field_pattern_mismatch` | `pattern` regex no match | `"Value does not match required format"` |
| `field_min_length` | text < minlength | `"Minimum length: N characters"` |
| `field_max_length` | text > maxlength | `"Maximum length: N characters"` |
| `field_min_value` | number < min | `"Minimum value: N"` |
| `field_max_value` | number > max | `"Maximum value: N"` |

### AJAX errors

| Error | Cause | Response |
|-------|-------|----------|
| `invalid_nonce` | Nonce missing/invalid | HTTP 403 + `"Security check failed"` |
| `insufficient_capability` | User can't manage options | HTTP 403 + `"Insufficient permissions"` |
| `invalid_payload` | Malformed request body | HTTP 400 + `"Invalid request"` |
| `save_failed` | `update_option()` returned false | HTTP 500 + `"Failed to save options"` |
| `validation_failed` | Field validation error | HTTP 422 + validation details |
