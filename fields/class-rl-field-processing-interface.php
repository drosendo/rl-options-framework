<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if (!defined('ABSPATH')) {
        return;
}

interface RL_Field_Processing_Interface
{
        /**
         * Sanitize field value.
         *
         * @param array $field Field definition.
         * @param mixed $value Raw value.
         * @param array $context Runtime processing context.
         * @return mixed
         */
        public function sanitize(array $field, $value, array $context = []);

        /**
         * Validate field value.
         *
         * @param array  $field Field definition.
         * @param mixed  $value Value to validate.
         * @param string $error Validation message output.
         * @param array  $context Runtime processing context.
         */
        public function validate(array $field, $value, string &$error, array $context = []): bool;

        /**
         * Prepare raw value for validation.
         *
         * @param array $field Field definition.
         * @param mixed $value Raw submitted value.
         * @param array $context Runtime processing context.
         * @return mixed
         */
        public function prepare_for_validation(array $field, $value, array $context = []);
}
