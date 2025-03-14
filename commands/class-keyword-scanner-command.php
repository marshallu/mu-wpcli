<?php
/**
 * Scans all active sites in a multisite network for specified keywords in posts and pages, exporting results to a CSV.
 *
 * @package Marshall_WPCLI
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { // phpcs:ignore
	return;
}

/**
 * Scans multisite for keywords and exports results to a CSV.
 */
class Keyword_Scanner_Command {
	/**
	 * Scans multisite for keywords and exports results to a CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--terms=<terms>]
	 * : Comma-separated list of keywords to search for. Required.
	 *
	 * [--status=<status>]
	 * : (Optional) Filter by a single post status (e.g., "publish"). Default is all statuses: "publish,draft,pending,private".
	 *
	 * [--filename=<filename>]
	 * : (Optional) Custom filename for the CSV export. Must end in .csv.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mu_scan_keywords --terms="West Virginia,Ohio,Kentucky"
	 *     wp mu_scan_keywords --terms="New York,Texas" --status="publish"
	 *     wp mu_scan_keywords --terms="New York,Texas" --status="draft" --filename="draft_scan.csv"
	 *
	 * @param array $args Arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			WP_CLI::error( 'This command only works in a multisite environment.' );
			return;
		}

		// Ensure --terms is provided.
		if ( ! isset( $assoc_args['terms'] ) ) {
			WP_CLI::error( 'You must provide keywords using --terms="keyword1,keyword2".' );
		}

		// Parse keywords.
		$keywords = array_map( 'trim', explode( ',', $assoc_args['terms'] ) );

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

		// Set filename (default or custom).
		$default_filename = 'multisite_keyword_scan.csv';
		$custom_filename  = $assoc_args['filename'] ?? $default_filename;

		// Ensure custom filename ends in .csv.
		if ( $custom_filename !== $default_filename && ! preg_match( '/\.csv$/i', $custom_filename ) ) {
			WP_CLI::error( 'Custom filename must end in .csv.' );
		}

		// Determine full file path.
		$upload_dir    = wp_upload_dir();
		$csv_file_path = $upload_dir['basedir'] . '/' . $custom_filename;

		// Open CSV file for writing.
		$csv_file = fopen( $csv_file_path, 'w' ); // phpcs:ignore
		fputcsv( $csv_file, array( 'Site URL', 'Page URL', 'Keywords Found', 'Post Status' ) );

		// Get only active sites (exclude archived, deleted, spam sites).
		$sites = get_sites(
			array(
				'number'   => 0,
				'deleted'  => 0,
				'archived' => 0,
				'spam'     => 0,
			)
		);

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			WP_CLI::log( 'Scanning active site: ' . get_site_url() );

			$query = sprintf(
				"SELECT ID, post_content, post_status FROM {$wpdb->posts} WHERE post_status IN ('%s') AND (post_type = 'post' OR post_type = 'page')",
				implode( "','", $post_statuses )
			);
			$posts = $wpdb->get_results( $query ); // phpcs:ignore

			foreach ( $posts as $post ) {
				$found_keywords = array_filter(
					$keywords,
					function ( $keyword ) use ( $post ) {
						return stripos( $post->post_content, $keyword ) !== false;
					}
				);

				if ( ! empty( $found_keywords ) ) {
					$page_url    = get_permalink( $post->ID );
					$post_status = ucfirst( $post->post_status ); // Capitalize status.
					fputcsv( $csv_file, array( get_site_url(), $page_url, implode( ', ', $found_keywords ), $post_status ) );
					WP_CLI::log( "Match found: $page_url ( " . implode( ', ', $found_keywords ) . ") - Status: $post_status" );
				}
			}

			restore_current_blog();
		}

		fclose( $csv_file ); // phpcs:ignore
		WP_CLI::success( "Export complete. File saved to: $csv_file_path" );
	}
}

WP_CLI::add_command( 'mu_scan_keywords', 'Keyword_Scanner_Command' );
