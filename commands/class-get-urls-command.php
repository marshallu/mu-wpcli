<?php
/**
 * Scans all active sites in a multisite network for specified keywords in posts and pages, exporting results to a CSV.
 *
 * @package Marshall_WPCLI
 */

namespace MuWPCLI\Commands;

use WP_CLI;
use WP_CLI_Command;

/**
 * Scans all active sites in a multisite network for specified keywords in posts and pages, exporting results to a CSV.
 */
class Get_Urls_Command extends WP_CLI_Command {
	/**
	 * Retrieves all URLs from the specified site and saves them to a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * [--site=<id>]
	 * : The site ID to fetch URLs from.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mu_get_urls --site=48
	 *
	 * @param array $args Arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! is_multisite() ) {
			WP_CLI::error( 'This command is only available in multisite environments.' );
			return;
		}

		$site_id = isset( $assoc_args['site'] ) ? (int) $assoc_args['site'] : null;

		if ( ! $site_id || ! get_blog_details( $site_id ) ) {
			WP_CLI::error( 'Invalid or missing site ID.' );
			return;
		}

		switch_to_blog( $site_id );

		// Set default post statuses.
		$default_statuses = array( 'publish', 'draft', 'pending', 'private' );

		// Check if --status argument is provided, otherwise use default statuses.
		if ( isset( $assoc_args['status'] ) ) {
			$status = trim( strtolower( $assoc_args['status'] ) );
			if ( ! in_array( $status, $default_statuses, true ) ) {
				WP_CLI::error( "Invalid status '$status'. Allowed statuses: publish, draft, pending, private." );
			}
			$post_statuses = array( $status ); // Use only the specified status.
		} else {
			$post_statuses = $default_statuses; // Use all default statuses.
		}

		$upload_dir   = wp_upload_dir();
		$csv_filename = "site-{$site_id}-urls.csv";
		$csv_filepath = $upload_dir['basedir'] . '/' . $csv_filename;
		$csv_url      = $upload_dir['baseurl'] . '/' . $csv_filename;

		$urls = array();

		$query = new WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( $post_statuses ),
				'posts_per_page' => -1,
			)
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$urls[] = array(
					'URL'           => get_permalink(),
					'Status'        => get_post_status(),
					'Last Modified' => get_the_modified_date( 'Y-m-d H:i:s' ),
				);
			}
		}

		restore_current_blog();

		if ( empty( $urls ) ) {
			WP_CLI::success( "No URLs found for site {$site_id}." );
			return;
		}

		// Write to CSV.
		$file = fopen( $csv_filepath, 'w' ); // phpcs:ignore
		fputcsv( $file, array( 'URL', 'Status', 'Last Modified' ) );
		foreach ( $urls as $url ) {
			fputcsv( $file, $url );
		}
		fclose( $file ); // phpcs:ignore

		WP_CLI::success( "CSV file created: {$csv_filepath}" );
		WP_CLI::line( "Download: {$csv_url}" );
	}
}

WP_CLI::add_command( 'mu_get_urls', 'Get_Urls_Command' );
