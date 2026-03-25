<?php

if (!defined('ABSPATH')) {
	return;
}

require_once __DIR__ . '/class-rl-field-interface.php';
require_once __DIR__ . '/class-rl-field-processing-interface.php';
require_once __DIR__ . '/class-rl-field-registry.php';

$files = glob(__DIR__ . '/class-rl-field-*.php');
if (is_array($files)) {
	sort($files);
	foreach ($files as $file) {
		$basename = basename($file);
		if (in_array($basename, ['class-rl-field-interface.php', 'class-rl-field-processing-interface.php', 'class-rl-field-registry.php'], true)) {
			continue;
		}
		require_once $file;
	}
}
