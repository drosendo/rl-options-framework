# RL Options Framework v3 Roadmap

> **Vision:** Transform from "WordPress options UI generator" to "extensible, professional options framework for plugin/theme developers"

---

## Strategic Goals

**From v2 to v3:**
- ✅ v2: Clean, modular, well-documented (DONE)
- 🎯 v3: Hooks-driven, GUI-configurable, ecosystem-ready
- **Impact:** Enable developers to build plugins *on top of* this framework

---

## Phase 1: Clean Architecture (1 hour)

### 1.1 Extract Remaining Methods

**Current state:** Main class = 1,187 lines

**Target:** Main class = <900 lines (pure orchestration)

#### Methods to extract:

| Method | Lines | Target | Status |
|--------|-------|--------|--------|
| `render_section()` | 15 | Render Service | —— |
| `render_section_inner()` | 10 | Render Service | —— |
| `render_field()` | 60 | Render Service | —— |
| `render_field_control()` | 35 | Render Service | —— |
| `format_tooltip_content()` | 10 | Render Service | —— |
| `render_notices()` | 20 | Notices Service | —— |
| `is_options_page()` | 5 | Utilities Service | —— |
| `get_options()` | 5 | Already exists | —— |
| **Subtotal** | **160 lines** | | |

#### Outcome:
- Main class: 1,187 → 1,027 lines
- Render Service absorbs all page rendering
- New Utilities Service for lightweight helpers
- New Notices Service for admin messaging

### 1.2 Create Utilities Service

**File:** `services/class-rl-options-utilities-service.php`

**Methods:**
```php
- is_options_page(): bool
- is_ajax_request(): bool
- get_capability_for_option(): string
- sanitize_input_value($value, $type): mixed
```

**Keep it under 100 lines** — thin wrapper layer

### 1.3 Create Notices Service

**File:** `services/class-rl-options-notices-service.php`

**Responsibility:**
- Add admin notice (success/error/warning)
- Render notices on page load
- Clear old notices on redirect

**Methods:**
```php
- add_notice(string $type, string $message, string $code = ''): void
- get_notices(): array
- render_notices(): void
- clear_notices(): void
```

---

## Phase 2: Extensibility System (3-4 hours)

### 2.1 Comprehensive Hooks System

Add **20+ strategic action/filter hooks** throughout the framework lifecycle.

#### Before/After Save Hooks

```php
// Admin handler (POST save)
do_action('rl_options_before_save', $option_name, $values, $context = 'post');
apply_filters('rl_options_values_sanitize', $values, $option_name);
do_action('rl_options_after_save', $option_name, $values, $old_values);

// AJAX save
do_action('rl_options_before_save_ajax', $option_name, $values);
apply_filters('rl_options_ajax_response', $response, $option_name);
do_action('rl_options_after_save_ajax', $option_name, $values);
```

#### Field Processing Hooks

```php
// Field validation
apply_filters('rl_options_field_validate', $validation_result, $field, $value, $state);
apply_filters('rl_options_field_validate_' . $field['type'], $validation_result, $field, $value);

// Field sanitization
apply_filters('rl_options_field_sanitize', $sanitized_value, $field, $value);
apply_filters('rl_options_field_sanitize_' . $field['type'], $sanitized_value, $field, $value);

// Field rendering
apply_filters('rl_options_field_render', $field, $value, $context);
apply_filters('rl_options_field_render_' . $field['type'], $field, $value, $context);

// Field options (async providers)
apply_filters('rl_options_field_options', $options, $field, $state);
apply_filters('rl_options_field_options_provider', $provider_result, $field, $endpoint, $params);
```

#### Schema/Config Hooks

```php
// Schema building
apply_filters('rl_options_schema_tabs', $tabs, $option_name);
apply_filters('rl_options_schema_sections', $sections, $tab_id, $option_name);
apply_filters('rl_options_schema_fields', $fields, $section_id, $tab_id, $option_name);

// Bootstrap
do_action('rl_options_framework_loaded');
do_action('rl_options_framework_ready', $framework);
```

#### Asset & UI Hooks

```php
// Assets
do_action('rl_options_assets_before_enqueue');
apply_filters('rl_options_assets_url', $assets_url, $context);
do_action('rl_options_assets_after_enqueue');

// Page rendering
do_action('rl_options_page_before_render');
apply_filters('rl_options_page_heading', $heading, $option_name);
do_action('rl_options_page_after_heading');
do_action('rl_options_page_after_render');
```

### 2.2 Hook Documentation

Create `HOOKS.md` documenting:
- Every hook name, type (action/filter), parameters
- When it fires in the lifecycle
- Example usage in extensions
- Use cases for each hook

---

## Phase 3: Admin UI for Configuration (6-8 hours)

### 3.1 Field Group Manager UI

**Goal:** Non-developers can create/manage option pages without code.

#### Admin Pages:
1. **Field Groups List**
   - Table of all saved field groups
   - Edit | Duplicate | Delete | Export buttons
   - Bulk actions

2. **Field Group Editor**
   - Visual tab/section/field builder
   - Drag-to-reorder fields
   - Live preview of form
   - JSON/PHP export

3. **Field Group Importer**
   - Upload JSON or paste PHP code
   - Preview before import
   - Conflict detection (existing groups)

#### UI Flow:
```
Menu: RL Options Framework
  ├─ Field Groups (list)
  ├─ Add New
  ├─ Import
  └─ Settings
```

#### Database Schema:
```php
// Post Type: rl_field_group
[
  'slug'       => 'my_plugin_settings',      // Unique identifier
  'label'      => 'My Plugin Settings',
  'config'     => json_encode([              // Full framework config
    'option_name' => 'my_plugin_options',
    'tabs' => [...],
    'sections' => [...],
    'fields' => [...]
  ]),
  'version'    => '1.0.0',
  'author'     => 'Plugin Name',
  'meta'       => []                         // Custom metadata
]
```

### 3.2 Import/Export System

**Export formats:**
```php
// JSON (for sharing)
{
  "version": "3.0.0",
  "slug": "my_group",
  "config": {...}
}

// PHP (for version control)
<?php
return [
  'slug' => 'my_group',
  'config' => [...]
];
```

**Registry endpoint:** `/wp-json/rl-options/v1/field-groups`
- List all groups
- Get group config
- Import new group
- Export existing group

### 3.3 Field Templates & Presets

**Built-in templates:**
```php
// Contact form
- Email field
- Phone field
- Message textarea

// Location
- Country select
- State select
- City select

// Branding
- Logo (image)
- Primary color (color)
- Font (select)

// SEO
- Meta title (text)
- Meta description (textarea)
- Focus keyword (text)
```

Users select a template → fills in labels → ready to use.

---

## Phase 4: Developer Experience (2-3 hours)

### 4.1 Enhanced Documentation

#### New docs needed:
- **HOOKS.md** - Complete hook reference (20+ hooks)
- **EXTENDING.md** - How to build plugins on top
- **FIELD-GROUP-API.md** - Programmatic field group CRUD
- **TEMPLATES.md** - Creating custom field templates
- **MIGRATION.md** - v2 to v3 upgrade guide

### 4.2 Code Examples

Add `examples/` folder:
```
examples/
  ├─ basic-settings.php         (simple options page)
  ├─ conditional-fields.php     (visibility rules)
  ├─ custom-validation.php      (validate_callback hook)
  ├─ async-options.php          (options_provider)
  ├─ field-bundles.php          (presets + bundles)
  ├─ extension-plugin.php       (plugin built on framework)
  └─ hooks-example.php          (all hooks in action)
```

### 4.3 REST API Enhancements

**New endpoints:**
```
GET  /wp-json/rl-options/v1/field-groups
GET  /wp-json/rl-options/v1/field-groups/{slug}
POST /wp-json/rl-options/v1/field-groups/{slug}/import
GET  /wp-json/rl-options/v1/field-groups/{slug}/export
GET  /wp-json/rl-options/v1/templates
GET  /wp-json/rl-options/v1/schema/{option_name}
```

---

## Phase 5: Quality Assurance (2 hours)

### 5.1 Unit Tests

```php
// tests/
tests/
  ├─ HooksTest.php
  ├─ FieldGroupImportExportTest.php
  ├─ UtilitiesServiceTest.php
  ├─ NoticesServiceTest.php
  └─ ValidationTest.php
```

### 5.2 Performance Audit

- Measure option getter/setter time
- Profile AJAX responses
- Check transient cache effectiveness

---

## Implementation Checklist

### Phase 1 (Clean Architecture)
- [ ] Extract render methods to Render Service
- [ ] Create Utilities Service
- [ ] Create Notices Service
- [ ] Verify syntax + line count reduction
- [ ] Commit: "Phase 1: Extract rendering & utilities"

### Phase 2 (Hooks System)
- [ ] Add 20+ strategic hooks throughout codebase
- [ ] Create HOOKS.md documentation
- [ ] Update DOCUMENTATION.md with hook reference
- [ ] Test hooks with sample extension
- [ ] Commit: "Phase 2: Comprehensive hooks system"

### Phase 3 (Admin UI)
- [ ] Create Field Group post type
- [ ] Build Field Group list admin page
- [ ] Build Field Group editor page
- [ ] Build import/export UI
- [ ] Add field templates system
- [ ] REST API endpoints for CRUD
- [ ] Commit: "Phase 3: Field Group Manager UI"

### Phase 4 (Developer Experience)
- [ ] Write EXTENDING.md
- [ ] Write FIELD-GROUP-API.md
- [ ] Create example plugins
- [ ] Write migration guide (v2→v3)
- [ ] Commit: "Phase 4: Documentation & examples"

### Phase 5 (QA)
- [ ] Write unit tests
- [ ] Performance profiling
- [ ] Security audit
- [ ] Commit: "Phase 5: QA & tests"

---

## Success Metrics (v3 vs v2)

| Metric | v2 | v3 | Target |
|--------|----|----|--------|
| Main class lines | 1,187 | <900 | ✓ |
| Action hooks | 0 | 20+ | ✓ |
| Filter hooks | 0 | 15+ | ✓ |
| Admin pages | 1 | 4 | ✓ |
| Extensibility | Low | High | ✓ |
| Docs coverage | 70% | 100% | ✓ |
| Example plugins | 0 | 3+ | ✓ |

---

## Estimated Timeline

| Phase | Hours | Status |
|-------|-------|--------|
| Phase 1: Clean Architecture | 1 | —— |
| Phase 2: Hooks System | 3-4 | —— |
| Phase 3: Admin UI | 6-8 | —— |
| Phase 4: Developer Docs | 2-3 | —— |
| Phase 5: QA & Tests | 2 | —— |
| **Total** | **14-18 hours** | **—— |

**Realistic delivery:** 2-3 developer days

---

## Release Notes Template (v3.0.0)

```
# RL Options Framework v3.0.0 - Professional Edition

## 🎯 Major Features

- **Hooks System:** 35+ action/filter hooks for deep extensibility
- **Field Group Manager:** Visual UI for creating options pages (no code)
- **Import/Export:** Share field configurations as JSON/PHP
- **Templates:** Pre-built field templates for common patterns
- **REST API:** Full CRUD for field groups via REST endpoints

## 🏗️ Architecture

- Render Service now handles all page rendering
- New Utilities Service for lightweight helpers
- New Notices Service for admin messaging
- Main framework class: pure orchestration (890 lines)

## 📚 Documentation

- Complete hooks reference (HOOKS.md)
- Extension development guide (EXTENDING.md)
- 5+ working examples in examples/ folder
- v2→v3 migration guide

## ⚡ Performance

- Transient caching optimized
- AJAX response payload reduced 15%
- Asset enqueueing streamlined

## 🔒 Security

- All REST endpoints nonce-validated
- Input sanitization audited
- Capability checks on all admin pages

## ✅ QA

- 30+ unit tests added
- Performance profiling completed
- Security audit passed

---

## Breaking Changes

⚠️ **None.** v3 is fully backward compatible with v2.

Public API unchanged. All new features are opt-in.
```

---

## Notes for Future Developer

- Start with Phase 1 to reduce main class complexity
- Phase 2 (hooks) is the "flywheel" — enables all future extensions
- Phase 3 (UI) is what makes this feel "professional"
- Phase 4 (docs) is critical for adoption
- Don't skip Phase 5 (QA) — extensibility code must be solid

**Key decision:** Prioritize hooks + UI (Phase 2-3) over perfection in Phase 1.
Non-developers using the UI = viral adoption.
