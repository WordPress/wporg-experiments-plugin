<?php
namespace WordPressdotorg\Experiments;
/**
 * Plugin Name: WordPress.org Experiments - Experimental core features
 * Plugin URI:  https://wordpress.org/plugins/wporg-experiments/
 * Description: A plugin to test new features and ideas for WordPress.org in Core.
 * Author:      WordPress.org contributors
 * Version:     0.1
 * License:     GPLv2 or later
 * Text Domain: wporg-experiments
 */

include __DIR__ . '/helpers.php';

// PR https://github.com/WordPress/wordpress-develop/pull/7671
include __DIR__ . '/plugin-closure-reasons/index.php';
include __DIR__ . '/plugin-closure-reasons/api-alteration.php';
