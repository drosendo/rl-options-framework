# RL Options Framework v2.0.0 - Changelog

## 🎉 New Feature: Nested Subfields with Auto-Indentation

### Overview

Version 2.0.0 introduces the ability to nest fields within fields, creating hierarchical settings structures with automatic visual indentation. This makes complex settings easier to organize and understand.

### What's New

#### Nested `fields` Array

Any field can now contain a `fields` array of child fields:

```php
'parent_field' => [
    'id'       => 'parent_field',
    'type'     => 'toggle',
    'title'    => 'Enable Feature',
    'fields'   => [
        'child_field_1' => [
            'id'    => 'child_field_1',
            'type'  => 'number',
            'title' => '↳ Child Setting 1',
        ],
        'child_field_2' => [
            'id'    => 'child_field_2',
            'type'  => 'color',
            'title' => '↳ Child Setting 2',
        ],
    ],
],
```

#### Automatic Indentation

Children are automatically indented based on nesting depth:
- Level 0 (parent): No indent
- Level 1 (child): 32px indent
- Level 2 (grandchild): 64px indent
- Level 3+: 96px, 128px, 160px...

#### Recursive Rendering

The framework handles arbitrary nesting depth. Fields can contain fields which contain fields, etc.

#### Priority Sorting

Subfields sort by `priority` within their parent's `fields` array, giving you full control over display order.

#### Backward Compatible

Existing `indent` and `indent_level` properties still work. Use them to override automatic indentation when needed.

## Technical Changes

### Framework Core (`class-rl-options-framework.php`)

#### `render_field()` - Recursive Rendering

```php
// Before (v1.x)
private function render_field( array $field, array $options ): void

// After (v2.0)
private function render_field( array $field, array $options, int $level = 0 ): void
```

- Added `$level` parameter to track nesting depth
- Renders parent field first
- Recursively renders children with `$level + 1`
- Children sorted by `priority` before rendering

#### `get_fields_index()` - Flat Indexing

```php
// Now recursively flattens nested fields
private function get_fields_index(): array {
    $collect = function( array $items ) use ( & $fields, & $collect ) {
        foreach ( $items as $field ) {
            $fields[ $field['id'] ] = $field;
            if ( ! empty( $field['fields'] ) ) {
                $collect( $field['fields'] ); // Recurse
            }
        }
    };
    // ...
}
```

- Flattens nested structure for save/validation
- Each field indexed by its `id` regardless of depth
- Enables independent validation/sanitization

#### `normalize_field()` - Nested Normalization

```php
// Added 'fields' to default parameters
$field = wp_parse_args( $field, [
    // ...existing...
    'fields' => [],
] );

// Recursively normalize children
if ( ! empty( $field['fields'] ) ) {
    foreach ( $field['fields'] as $child_id => $child ) {
        $field['fields'][ $child['id'] ] = $this->normalize_field( $child );
    }
    // Sort by priority
    uasort( $field['fields'], ... );
}
```

### CSS (`options-framework.css`)

Added support for deeper indentation levels:

```css
.rl-options-page .rl-field-indent-level-4 {
	margin-left: 128px;
}

.rl-options-page .rl-field-indent-level-5 {
	margin-left: 160px;
}
```

## Migration Guide

### From Manual Indent to Nested Fields

**Before (v1.x):**
```php
'fields' => [
    'enable_feature' => [
        'id'   => 'enable_feature',
        'type' => 'toggle',
    ],
    'feature_option_1' => [
        'id'           => 'feature_option_1',
        'type'         => 'number',
        'indent_level' => 2,
        'condition'    => ['field' => 'enable_feature', ...],
    ],
    'feature_option_2' => [
        'id'           => 'feature_option_2',
        'type'         => 'color',
        'indent_level' => 2,
        'condition'    => ['field' => 'enable_feature', ...],
    ],
],
```

**After (v2.0):**
```php
'fields' => [
    'enable_feature' => [
        'id'     => 'enable_feature',
        'type'   => 'toggle',
        'fields' => [
            'feature_option_1' => [
                'id'        => 'feature_option_1',
                'type'      => 'number',
                'condition' => ['field' => 'enable_feature', ...],
            ],
            'feature_option_2' => [
                'id'        => 'feature_option_2',
                'type'      => 'color',
                'condition' => ['field' => 'enable_feature', ...],
            ],
        ],
    ],
],
```

**Benefits:**
- Clearer code structure
- No manual indent management
- Easier to add/remove/reorder children
- Visual hierarchy matches code hierarchy

## Real-World Example

### Showcase Images with Nested Settings

```php
'variation_thumbnails' => [
    'id'       => 'variation_thumbnails',
    'type'     => 'toggle',
    'label'    => 'Showcase Images Under Variations',
    'default'  => false,
    'priority' => 30,
    'fields'   => [
        'columns_showcase' => [
            'id'       => 'columns_showcase',
            'type'     => 'number',
            'label'    => '↳ Showcase Thumbnail Items',
            'default'  => 4,
            'min'      => 1,
            'max'      => 10,
            'priority' => 10,
        ],
        'showcase_gap' => [
            'id'       => 'showcase_gap',
            'type'     => 'number',
            'label'    => '↳ Showcase Thumbnails Gap (px)',
            'default'  => 10,
            'priority' => 20,
        ],
        'showcase_border_width' => [
            'id'       => 'showcase_border_width',
            'type'     => 'number',
            'label'    => '↳ Showcase Border Width (px)',
            'default'  => 1,
            'priority' => 30,
        ],
        'showcase_border_color' => [
            'id'       => 'showcase_border_color',
            'type'     => 'color',
            'label'    => '↳ Showcase Border Color',
            'default'  => '#dddddd',
            'priority' => 31,
            'condition' => [
                'field'    => 'showcase_border_width',
                'operator' => '>',
                'value'    => 0,
            ],
        ],
        // ...more children...
    ],
],
```

**Result:**
- All showcase settings visually grouped under parent toggle
- Automatic indent creates clear hierarchy
- Each child saves/validates independently
- Easy to maintain and extend

## Compatibility

### Backward Compatibility

✅ **100% backward compatible** with existing settings:
- Existing `indent` and `indent_level` still work
- No changes required to existing code
- Can mix old and new approaches

### Forward Compatibility

New `fields` property is optional:
- Fields without `fields` array work as before
- Gradually migrate to nested structure at your own pace

## Best Practices

1. **Use Visual Prefix**: Add `↳` or similar to child labels
2. **Logical Grouping**: Nest related settings together
3. **Limit Depth**: Avoid going beyond 3-4 levels
4. **Priority Gaps**: Use increments of 10 (10, 20, 30) for future insertions
5. **Conditions**: Always hide children until parent is enabled

## Performance

No performance impact:
- Fields still flatten for save/validation
- No additional database queries
- Same validation/sanitization flow
- Minimal rendering overhead

## Documentation

Updated documentation includes:
- Complete nested fields guide
- Real-world examples
- Migration patterns
- Best practices
- Visual hierarchy examples

See `DOCUMENTATION.md` for full details.

---

**Version:** 2.0.0  
**Release Date:** December 15, 2025  
**Breaking Changes:** None  
**Compatibility:** WordPress 5.0+, PHP 7.4+
