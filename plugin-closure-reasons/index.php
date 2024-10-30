<?php
namespace WordPressdotorg\Experiments\PluginClosureReasons;
/**
 * This experiment adds a "closed" notice to wp-admin/plugins.php for that have been closed on WordPress.org.
 *
 * See https://core.trac.wordpress.org/ticket/30465 for discussion.
 */

/**
 * Add the closed plugin notice to each closed plugin.
 */
function wp_plugin_update_rows() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$plugins = get_site_transient( 'update_plugins' );
	if ( isset( $plugins->closed ) && is_array( $plugins->closed ) ) {
		$plugin_files = array_keys( $plugins->closed );

		foreach ( $plugin_files as $plugin_file ) {
			add_action( "after_plugin_row_{$plugin_file}", __NAMESPACE__ . '\wp_plugin_closed_row', 10, 2 );
		}
	}
}
add_action( 'load-plugins.php', __NAMESPACE__ . '\wp_plugin_update_rows' );
add_action( 'wp_ajax_search-plugins', __NAMESPACE__ . '\wp_plugin_update_rows', 1 ); // wp_plugin_update_rows() is called from that AJAX handler.

/**
 * Displays closed plugin notice.
 *
 * @since 6.8.0
 *
 * @param string $file        Plugin basename.
 * @param array  $plugin_data Plugin information.
 * @return void|false
 */
function wp_plugin_closed_row( $file, $plugin_data ) {
	$current = get_site_transient( 'update_plugins' );

	if ( ! isset( $current->closed[ $file ] ) ) {
		return false;
	}

	$response    = $current->closed[ $file ];
	$plugin_slug = isset( $response->slug ) ? $response->slug : $response->id;

	/** @var WP_Plugins_List_Table $wp_list_table */
	$wp_list_table = _get_list_table(
		'WP_Plugins_List_Table',
		array(
			'screen' => get_current_screen(),
		)
	);

	if ( is_multisite() && ! is_network_admin() ) {
		return;
	}

	if ( is_network_admin() ) {
		$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
	} else {
		$active_class = is_plugin_active( $file ) ? ' active' : '';
	}

	printf(
		// MERGE TODO: Remove plugin-update-tr class.
		'<tr class="plugin-update-tr plugin-closed-tr%s" id="%s" data-slug="%s" data-plugin="%s">' .
		'<td colspan="%s" class="plugin-closed colspanchange">' .
		'<div class="closed-message notice inline notice-warning notice-alt"><p>',
		$active_class,
		esc_attr( $plugin_slug . '-closed' ),
		esc_attr( $plugin_slug ),
		esc_attr( $file ),
		esc_attr( $wp_list_table->get_column_count() )
	);

	$closure_reasons = array(
		'security-issue'                => _x( 'A security issue is known to exist within the plugin.', 'Plugin closure reason' ),
		'author-request'                => _x( 'The author of this plugin no longer supports it, and requested the plugin be closed.', 'Plugin closure reason' ),
		'guideline-violation'           => _x( 'The plugin has been suspended due to a Guideline Violation.', 'Plugin closure reason' ),
		'licensing-trademark-violation' => _x( 'The plugin has been suspended due to a Licensing/Trademark Violation', 'Plugin closure reason' ),
		'merged-into-core'              => _x( 'This plugin is no longer required, as the functionality is now provided by WordPress.', 'Plugin closure reason' ),
		'unused'                        => _x( 'This plugin is unused.', 'Plugin closure reason' ),
		'unknown'                       => _x( 'The reason is unknown.', 'Plugin closure reason' ),
	);

	/* translators: %s: is an error code returned by WordPress.org, this is a fallback for when no reason is known. */
	$unknown_closure_reason                    = _x( 'The plugin has been closed due to: %s.', 'Plugin closure reason' );
	$closure_reasons['unknown-closure-reason'] = $unknown_closure_reason;

	/**
	 * Filters the list of plugin closure reasons.
	 *
	 * @since 6.8.0
	 *
	 * @param array $closure_reasons An array of plugin closure reasons.
	 * @return array An array of plugin closure reasons.
	 */
	$closure_reasons = apply_filters( 'plugin_closure_reasons', $closure_reasons );

	if ( isset( $closure_reasons[ $response->closed_reason ] ) ) {
		$closure_reason_text = $closure_reasons[ $response->closed_reason ];
	} else {
		if ( isset( $closure_reasons['unknown-closure-reason'] ) ) {
			$unknown_closure_reason = $closure_reasons['unknown-closure-reason'];
		}

		$closure_reason_text = sprintf(
			$unknown_closure_reason,
			'<code>' . esc_html( $response->closed_reason ) . '</code>'
		);
	}

	printf(
		/* translators: 1: Date the plugin was closed, 2: Reason the plugin was closed. */
		__( 'This plugin has been closed as of %1$s and is no longer available for new installs. %2$s.' ),
		date_i18n( get_option( 'date_format' ), strtotime( $response->closed_on ) ),
		$closure_reason_text
	);

	/**
	 * Fires at the end of the closed message container in each
	 * row of the plugins list table.
	 *
	 * The dynamic portion of the hook name, `$file`, refers to the path
	 * of the plugin's primary file relative to the plugins directory.
	 *
	 * @since 6.8.0
	 *
	 * @param array  $plugin_data An array of plugin metadata. See get_plugin_data()
	 *                            and the {@see 'plugin_row_meta'} filter for the list
	 *                            of possible values.
	 * @param object $response {
	 *     An object of metadata about the closed plugin.
	 *
	 *     @type string $id            Plugin ID, e.g. `w.org/plugins/[plugin-name]`.
	 *     @type string $slug          Plugin slug.
	 *     @type string $plugin        Plugin basename.
	 *     @type string $url           Plugin URL.
	 *     @type string $closed_on     The date of which the plugin was closed.
	 *     @type string $closed_reason A code which represents the reason the plugin was closed.
	 * }
	 */
	do_action( "in_plugin_closed_message-{$file}", $plugin_data, $response ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

	echo '</p></div></td></tr>';
}

function add_script_styles() {
	/*
	 * Add the needed CSS to style the closed message.
	 */
	wp_add_inline_style(
		'common',
		<<<CSS
		.closed-message p:before {
			display: inline-block;
			font: normal 20px/1 'dashicons';
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			vertical-align: top;
		}
		/* Closed icon */
		.closed-message p:before {
			color: #d63638;
			content: "\\f534";
		}
		CSS
	);

	/*
	 * Add the needed JS to add the 'update' class to the plugin row.
	 *
	 * This is needed as the plugins list table doesn't have a way to filter the table tr classes.
	 *
	 * MERGE TODO: Update all the instances of tr.update to also taget tr.closed.
	 */
	wp_add_inline_script(
		'updates',
		<<<JS
			jQuery('.plugin-closed-tr').each(
				( index, el ) => {
					jQuery(el).prev().addClass('update')
				}
			);
		JS,
		'after'
	);
}
add_action( 'load-plugins.php', __NAMESPACE__ . '\add_script_styles', 20 );
