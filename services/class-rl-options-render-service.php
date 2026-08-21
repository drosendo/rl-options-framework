<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
/**
 * RL Options Framework – Render Service
 *
 * Encapsulates all admin page rendering logic separated from the main framework orchestration.
 * This service handles page layout, tabs, sections, fields, and tooltips.
 *
 * @package RL_Options_Framework
 */

if (!defined('ABSPATH')) {
	return;
}

/**
 * Render service for the options framework.
 * Delegates core framework logic through dependency injection.
 */
class RL_Options_Render_Service
{
	/**
	 * Framework instance reference.
	 *
	 * @var RL_Options_Framework
	 */
	private $framework;

	/**
	 * Constructor.
	 *
	 * @param RL_Options_Framework $framework Framework instance.
	 */
	public function __construct(RL_Options_Framework $framework)
	{
		$this->framework = $framework;
	}

	/**
	 * Render the full settings page.
	 */
	public function render_page(): void
	{
		$tabs = $this->framework->get_tabs();

		// Filter tabs based on show_if conditions
		$options = get_option($this->framework->config['option_name'], []);
		if (!is_array($options)) {
			$options = [];
		}
		$tabs = $this->framework->filter_tabs_by_conditions($tabs, $options);

		$current_tab = $this->framework->get_current_tab_slug($tabs);
		$header_meta_html = apply_filters('rl_options_framework_header_meta_html', '', $this->framework->config, $this->framework);
		?>
		<div class="wrap rl-options-page">
			<div class="rl-page-header">
				<h1><?php echo esc_html($this->framework->config['page_title']); ?></h1>
				<?php if (!empty($header_meta_html)): ?>
					<div class="rl-page-header-meta">
						<?php echo wp_kses_post($header_meta_html); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if (empty($tabs)): ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('No settings tabs have been registered yet.', 'smart-variations-images-premium'); ?></p>
					<p><?php 
						/* translators: %s: filter name */
						printf(esc_html__('Use the %s filter to add settings tabs.', 'smart-variations-images-premium'), '<code>' . esc_html($this->framework->config['option_name'] . '_framework_tabs') . '</code>'); 
					?></p>
					</p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if (!empty($tabs)): ?>
				<nav class="nav-tab-wrapper" role="tablist">
					<?php foreach ($tabs as $slug => $tab): ?>
						<?php
						$active = $slug === $current_tab ? ' nav-tab-active' : '';
						$hidden = !empty($tab['_hidden']) ? ' style="display:none;"' : '';
						$tab_conditions = !empty($tab['show_if']) ? ' data-tab-conditions=\'' . esc_attr(wp_json_encode($tab['show_if'])) . '\'' : '';
						?>
						<a class="nav-tab<?php echo esc_attr($active); ?>" href="<?php echo esc_url($this->framework->get_tab_url($slug)); ?>"
							data-rl-tab="<?php echo esc_attr($slug); ?>" <?php echo $tab_conditions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo esc_html($tab['label']); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" novalidate>
				<input type="hidden" name="action" value="<?php echo esc_attr($this->framework->config['page_slug'] . '_save'); ?>" />
				<?php
				$nonce_action = $this->framework->config['page_slug'] . '_save_options';
				$nonce_field = $this->framework->config['form_field_prefix'] . '_nonce';
				wp_nonce_field($nonce_action, $nonce_field);
				?>
				<div class="rl-tab-panels">
					<?php foreach ($tabs as $slug => $tab): ?>
						<?php
						$panel_active = $slug === $current_tab ? ' is-active' : '';
						$hidden = !empty($tab['_hidden']) ? ' style="display:none;"' : '';
						?>
						<section class="rl-tab-panel<?php echo esc_attr($panel_active); ?>"
							data-rl-panel="<?php echo esc_attr($slug); ?>" <?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php
							if (!empty($tab['description'])) {
								echo '<p class="rl-tab-description">' . wp_kses_post($tab['description']) . '</p>';
							}

							if (!empty($tab['sections']) && count($tab['sections']) > 1):
								// Multiple sections - render sidebar navigation
								$this->render_panel_with_sidebar($tab, $options);
							elseif (!empty($tab['sections'])):
								// Single section - render directly
								foreach ($tab['sections'] as $section):
									$this->render_section($section, $options, false);
								endforeach;
							endif;
							?>
						</section>
					<?php endforeach; ?>
				</div>

				<div class="rl-submit-bar">
					<button type="submit" class="button button-primary">
						<?php esc_html_e('Save changes', 'smart-variations-images-premium'); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render panel with sidebar navigation.
	 *
	 * @param array $tab     Tab configuration.
	 * @param array $options Current options.
	 */
	private function render_panel_with_sidebar(array $tab, array $options): void
	{
		if (empty($tab['sections'])) {
			return;
		}

		$current_section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : key($tab['sections']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="rl-sidebar-layout">
			<div class="rl-sidebar">
				<ul class="rl-sidebar-menu">
					<?php foreach ($tab['sections'] as $section_id => $section): ?>
						<?php $active_class = $section_id === $current_section ? ' rl-sidebar-active' : ''; ?>
						<li>
							<a href="#<?php echo esc_attr($section_id); ?>"
								class="rl-sidebar-link<?php echo esc_attr($active_class); ?>"
								data-section="<?php echo esc_attr($section_id); ?>">
								<?php echo esc_html($section['title'] ?? ucwords(str_replace('_', ' ', $section_id))); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="rl-content">
				<?php foreach ($tab['sections'] as $section_id => $section): ?>
					<?php $content_active = $section_id === $current_section ? ' is-active' : ''; ?>
					<div class="rl-section-content<?php echo esc_attr($content_active); ?>"
						data-section-content="<?php echo esc_attr($section_id); ?>">
						<?php $this->render_section($section, $options, true); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a section / accordion element.
	 *
	 * @param array<string,mixed> $section    Section definition.
	 * @param array               $options    Current option values.
	 * @param bool                $in_sidebar Whether this section is in a sidebar layout.
	 */
	private function render_section(array $section, array $options, bool $in_sidebar = false): void
	{
		$is_accordion = !empty($section['accordion']) && !$in_sidebar;
		$section_id = $section['id'];

		$section_classes = ['rl-section'];
		if ($is_accordion) {
			$section_classes[] = 'is-accordion';
		}

		$data_attrs = '';
		if (!empty($section['conditions']) && is_array($section['conditions'])) {
			$data_attrs .= sprintf(
				' data-conditions="%s" data-visibility-rules="%s"',
				esc_attr(wp_json_encode($section['conditions'])),
				esc_attr(wp_json_encode($section['conditions']))
			);
			$section_classes[] = 'has-conditions';
		}

		?>
		<div class="<?php echo esc_attr(implode(' ', $section_classes)); ?>"
			data-rl-section="<?php echo esc_attr($section_id); ?>"<?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ($is_accordion): ?>
				<button type="button" class="rl-accordion-toggle" aria-expanded="false">
					<span><?php echo esc_html($section['title']); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<div class="rl-accordion-content">
					<?php $this->render_section_inner($section, $options); ?>
				</div>
			<?php else: ?>
				<?php if (!$in_sidebar || !empty($section['title'])): ?>
					<header class="rl-section-header">
						<h2><?php echo esc_html($section['title']); ?></h2>
						<?php if (!empty($section['description'])): ?>
							<p class="description"><?php echo wp_kses_post($section['description']); ?></p>
						<?php endif; ?>
					</header>
				<?php endif; ?>
				<div class="rl-section-body">
					<?php $this->render_section_inner($section, $options); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the fields for a section.
	 *
	 * @param array $section Section definition.
	 * @param array $options Stored options.
	 */
	private function render_section_inner(array $section, array $options): void
	{
		if (empty($section['fields'])) {
			echo '<p class="description">' . esc_html__('No settings available for this section yet.', 'smart-variations-images-premium') . '</p>';
			return;
		}

		foreach ($section['fields'] as $field_key => $field) {
			// Ensure field has an ID (use key as fallback)
			if (empty($field['id'])) {
				$field['id'] = $field_key;
			}
			$this->render_field($field, $options, 0);
		}
	}

	/**
	 * Render a single field row.
	 *
	 * @param array $field   Field configuration.
	 * @param array $options Stored options.
	 */
	private function render_field(array $field, array $options, int $level = 0): void
	{
		// Ensure field has required properties
		if (empty($field['id']) || empty($field['type'])) {
			return; // Skip invalid fields
		}

		$field_id = $field['id'];
		$field_type = $field['type'];
		$field_label = $field['label'] ?? '';
		$field_label = $this->framework->normalize_field_label((string) $field_label);
		$field_desc = $field['description'] ?? $field['desc'] ?? '';
		$field_classes = ['rl-field', 'rl-field-' . $field_type];

		$field_value = $options[$field_id] ?? ($field['default'] ?? null);

		$data_attrs = '';
		$visibility_rules = $field['visibility_rules'] ?? ($field['conditions'] ?? []);
		if (!empty($visibility_rules) && is_array($visibility_rules)) {
			$data_attrs .= sprintf(
				' data-conditions="%s" data-visibility-rules="%s"',
				esc_attr(wp_json_encode($visibility_rules)),
				esc_attr(wp_json_encode($visibility_rules))
			);
			$field_classes[] = 'has-conditions';
		}

		if (!empty($field['depends_on']) && is_array($field['depends_on'])) {
			$data_attrs .= sprintf(
				' data-depends-on="%s"',
				esc_attr(wp_json_encode(array_values($field['depends_on'])))
			);
			$field_classes[] = 'has-dependencies';
		}

		if (!empty($field['options_provider']) && is_array($field['options_provider'])) {
			$data_attrs .= sprintf(
				' data-options-provider="%s"',
				esc_attr(wp_json_encode($field['options_provider']))
			);
			$field_classes[] = 'has-options-provider';
		}

		if (!empty($field['required_if']) && is_array($field['required_if'])) {
			$data_attrs .= sprintf(
				' data-required-if="%s"',
				esc_attr(wp_json_encode($field['required_if']))
			);
		}

		if (!empty($field['width'])) {
			$field_classes[] = 'rl-field-width-' . preg_replace('/[^a-z0-9_\-]/', '', $field['width']);
		}

		// Add indent classes if specified or inherited from nesting
		$indent_level = null;
		if (isset($field['indent_level'])) {
			$indent_level = max(0, (int) $field['indent_level']);
		} elseif (!empty($field['indent'])) {
			$indent_level = max(1, $level);
		} else {
			$indent_level = max(0, $level);
		}

		if ($indent_level > 0) {
			$field_classes[] = 'rl-field-indented';
			$field_classes[] = 'rl-field-indent-level-' . $indent_level;
		}

		printf(
			'<div class="%1$s" data-field-id="%2$s"%3$s>',
			esc_attr(implode(' ', $field_classes)),
			esc_attr((string) $field_id),
			$data_attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		if (!empty($field_label)) {
			printf(
				'<label class="rl-field-label" for="%1$s">%2$s',
				esc_attr($this->framework->get_input_id($field_id)),
				wp_kses_post($field_label)
			);
			if (!empty($field_desc)) {
				$tooltip_desc = $this->format_tooltip_content((string) $field_desc);

				printf(
					' <span class="rl-field-tooltip" data-tippy-content="%s"><span class="dashicons dashicons-info"></span></span>',
					esc_attr($tooltip_desc)
				);
			}
			echo '</label>';
		}

		echo '<div class="rl-field-control">';
		$this->render_field_control($field, $field_value);
		echo '</div>';
		echo '<div class="rl-field-inline-error" aria-live="polite" style="display:none;"></div>';

		echo '</div>'; // Close .rl-field

		// Render nested subfields (if provided) with automatic indentation
		// These render as siblings, not children of the parent field wrapper
		if (!empty($field['fields']) && is_array($field['fields'])) {
			// Sort children by priority if associative
			$children = $field['fields'];
			uasort(
				$children,
				static function (array $a, array $b): int {
					return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
				}
			);

			foreach ($children as $child_key => $child_field) {
				if (empty($child_field['id'])) {
					$child_field['id'] = is_string($child_key) ? $child_key : uniqid('field_', true);
				}
				$this->render_field($child_field, $options, $level + 1);
			}
		}
	}

	/**
	 * Format tooltip text with safe lightweight HTML.
	 *
	 * Rules:
	 * - If explicit HTML is provided, keep it (sanitized).
	 * - If plain text is provided, auto-wrap into paragraphs.
	 * - Label-like fragments (e.g. "Grid:") are bolded for readability.
	 */
	private function format_tooltip_content(string $raw): string
	{
		$allowed_html = [
			'p' => [],
			'br' => [],
			'b' => [],
			'strong' => [],
			'em' => [],
			'code' => [],
			'ul' => [],
			'ol' => [],
			'li' => [],
			'a' => [
				'href' => true,
				'target' => true,
				'rel' => true,
			],
		];

		$raw = trim($raw);
		if ('' === $raw) {
			return '';
		}

		$sanitized = wp_kses($raw, $allowed_html);

		// If the source is already intentionally structured, preserve it as-is.
		if ((bool) preg_match('/<(p|ul|ol|li|br)\b/i', $sanitized)) {
			return $sanitized;
		}

		// Normalize any lightweight HTML into plain text for global auto-formatting.
		// This lets existing strings like "<strong>Grid:</strong> ..." become proper list rows.
		$text_source = preg_replace('/<br\s*\/?>/i', "\n", $sanitized);
		$text_source = wp_strip_all_tags((string) $text_source, true);

		// Normalize plain text for formatter heuristics.
		$normalized = preg_replace('/\r\n?|\n/', ' ', (string) $text_source);
		$normalized = preg_replace('/\s+/', ' ', (string) $normalized);
		$normalized = trim((string) $normalized);

		if ('' === $normalized) {
			return '';
		}

		$label_pattern = '/([A-Z][A-Za-z0-9\/\-\s]{1,40}):\s*/';
		$label_matches = [];
		preg_match_all($label_pattern, $normalized, $label_matches, PREG_OFFSET_CAPTURE);

		$html_parts = [];

		// If there are label segments, render intro + list structure.
		if (!empty($label_matches[0]) && count($label_matches[0]) >= 1) {
			$first_offset = (int) $label_matches[0][0][1];
			$intro = trim(substr($normalized, 0, $first_offset));

			if ('' !== $intro) {
				$intro = rtrim($intro, '. ');
				$html_parts[] = sprintf('<p>%s.</p>', esc_html($intro));
			}

			$list_items = [];
			$total = count($label_matches[0]);
			for ($index = 0; $index < $total; $index++) {
				$full_match = $label_matches[0][$index][0];
				$current_offset = (int) $label_matches[0][$index][1];
				$next_offset = ($index + 1 < $total)
					? (int) $label_matches[0][$index + 1][1]
					: strlen($normalized);

				$label = trim(rtrim($full_match, ':'));
				$body_start = $current_offset + strlen($full_match);
				$body = trim(substr($normalized, $body_start, $next_offset - $body_start));
				$body = rtrim($body, '. ');

				if ('' === $label && '' === $body) {
					continue;
				}

				if ('' !== $body) {
					$list_items[] = sprintf(
						'<li><strong>%s:</strong> %s.</li>',
						esc_html($label),
						esc_html($body)
					);
				} else {
					$list_items[] = sprintf('<li><strong>%s:</strong></li>', esc_html($label));
				}
			}

			if (!empty($list_items)) {
				$html_parts[] = '<ul>' . implode('', $list_items) . '</ul>';
			}
		} else {
			$chunks = preg_split('/\s(?=[A-Z][A-Za-z0-9\/\-\s]{2,40}:\s)/', $normalized) ?: [$normalized];
			foreach ($chunks as $chunk) {
				$chunk = trim((string) $chunk);
				if ('' === $chunk) {
					continue;
				}

				if (preg_match('/^([A-Z][A-Za-z0-9\/\-\s]{2,40}:)\s*(.*)$/', $chunk, $matches)) {
					$label = esc_html(trim($matches[1]));
					$text = esc_html(trim($matches[2]));
					$html_parts[] = sprintf('<p><strong>%s</strong> %s</p>', $label, $text);
					continue;
				}

				$html_parts[] = sprintf('<p>%s</p>', esc_html($chunk));
			}
		}

		if (empty($html_parts)) {
			return wp_kses(sprintf('<p>%s</p>', esc_html($normalized)), $allowed_html);
		}

		return wp_kses(implode('', $html_parts), $allowed_html);
	}

	/**
	 * Render field control input based on field type.
	 *
	 * @param array       $field Field definition.
	 * @param string|bool $value Current value.
	 */
	private function render_field_control(array $field, $value): void
	{
		$field_id = $field['id'];
		$field_name = $this->framework->get_input_name($field_id);
		$input_id = $this->framework->get_input_id($field_id);
		$field_type = (string) ($field['type'] ?? 'text');

		if ($this->framework->field_registry()) {
			$renderer = $this->framework->field_registry()->get($field_type);
			if ($renderer) {
				$renderer->render(
					$field,
					$value,
					[
						'input_id' => $input_id,
						'field_name' => $field_name,
						'text_domain' => (string) $this->framework->config['text_domain'],
						'options_state' => $this->framework->get_options(),
						'geo_options_callback' => function (array $geo_field, string $geo_type, array $state = []): array {
							return $this->framework->get_geo_field_options($geo_field, $geo_type, $state);
						},
					]
				);
				return;
			}
		}

		$display_value = is_scalar($value) ? (string) $value : wp_json_encode($value);

		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr($display_value)
		);
	}
}
