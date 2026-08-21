<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
	return;
}

class RL_Field_Image implements RL_Field_Interface
{
	public function type(): string
	{
		return 'image';
	}

	public function render(array $field, $value, array $context = []): void
	{
		$input_id = (string) ($context['input_id'] ?? '');
		$field_name = (string) ($context['field_name'] ?? '');
		$text_domain = (string) ($context['text_domain'] ?? 'default');
		$button_text = __('Choose Image', 'smart-variations-images-premium');
		$remove_text = __('Remove', 'smart-variations-images-premium');

		printf(
			'<div class="rl-image-field"><input type="hidden" id="%1$s" name="%2$s" value="%3$s" class="rl-image-input" /><button type="button" class="button rl-upload-image-button" data-input-id="%1$s">%4$s</button><button type="button" class="button rl-remove-image-button" data-input-id="%1$s" style="%5$s">%6$s</button><div class="rl-image-preview" style="margin-top:10px;">%7$s</div></div>',
			esc_attr($input_id),
			esc_attr($field_name),
			esc_attr((string) $value),
			esc_html($button_text),
			empty($value) ? 'display:none;' : '',
			esc_html($remove_text),
			!empty($value) ? sprintf('<img src="%s" style="max-width:200px;height:auto;display:block;" />', esc_url((string) $value)) : ''
		);
	}
}
