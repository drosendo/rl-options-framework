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

## 4. Field Type Contract

- `image`: WordPress media uploader (single URL value).
- `image_select`: radio-like selector with image options.

`image_select` expects options shaped like:

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
