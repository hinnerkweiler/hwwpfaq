<?php
/**
 * Database operations for the HW WP FAQ plugin.
 *
 * Table schema:
 *   id          – auto-increment primary key
 *   category    – short label used for grouping/filtering
 *   question    – the FAQ question (plain text)
 *   answer      – the FAQ answer (HTML, stored via wp_kses_post)
 *   pub_date    – scheduled publication date/time
 *   author_id   – FK to wp_users.ID
 *   is_active   – 0 = inactive (hidden), 1 = active
 *   created_at  – row creation timestamp
 *   updated_at  – last-modified timestamp
 */

defined( 'ABSPATH' ) || exit;

class HWWPFAQ_DB {

	/**
	 * Creates (or upgrades) the plugin table on activation.
	 * Uses dbDelta so re-activation is safe.
	 */
	public static function create_table() {
		global $wpdb;

		$table           = $wpdb->prefix . HWWPFAQ_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			category   VARCHAR(100)        NOT NULL DEFAULT '',
			question   TEXT                NOT NULL,
			answer     LONGTEXT            NOT NULL,
			pub_date   DATETIME            NOT NULL DEFAULT '2000-01-01 00:00:00',
			author_id  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			is_active  TINYINT(1)          NOT NULL DEFAULT 1,
			created_at DATETIME            NOT NULL DEFAULT '2000-01-01 00:00:00',
			updated_at DATETIME            NOT NULL DEFAULT '2000-01-01 00:00:00',
			PRIMARY KEY (id),
			KEY idx_category (category),
			KEY idx_pub_date (pub_date),
			KEY idx_is_active (is_active)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Fetches FAQ rows with optional filtering.
	 *
	 * @param array $args {
	 *   @type string $category    Filter by exact category. Empty = all.
	 *   @type bool   $active_only Return only is_active=1 rows.
	 *   @type bool   $published   Return only rows where pub_date <= now.
	 *   @type string $orderby     Column name (id|category|pub_date|created_at).
	 *   @type string $order       ASC or DESC.
	 *   @type int    $limit       Max rows; -1 = unlimited.
	 *   @type int    $offset      Row offset for pagination.
	 * }
	 * @return array Array of stdClass objects.
	 */
	public static function get_items( array $args = array() ) {
		global $wpdb;

		$table    = $wpdb->prefix . HWWPFAQ_TABLE;
		$defaults = array(
			'category'    => '',
			'active_only' => false,
			'published'   => false,
			'orderby'     => 'pub_date',
			'order'       => 'ASC',
			'limit'       => -1,
			'offset'      => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$params        = array();

		if ( '' !== $args['category'] ) {
			$where_clauses[] = 'category = %s';
			$params[]        = $args['category'];
		}
		if ( $args['active_only'] ) {
			$where_clauses[] = 'is_active = 1';
		}
		if ( $args['published'] ) {
			$where_clauses[] = 'pub_date <= %s';
			$params[]        = current_time( 'mysql' );
		}

		$where   = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$orderby = self::safe_orderby( $args['orderby'] );
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM `{$table}` {$where} ORDER BY `{$orderby}` {$order}";

		if ( $args['limit'] > 0 ) {
			$sql      .= ' LIMIT %d OFFSET %d';
			$params[]  = absint( $args['limit'] );
			$params[]  = absint( $args['offset'] );
		}

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql );

		return $results ?: array();
	}

	/**
	 * Returns a single FAQ row by ID or null.
	 *
	 * @param int $id Row ID.
	 * @return stdClass|null
	 */
	public static function get_item( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . HWWPFAQ_TABLE;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", absint( $id ) )
		);
	}

	/**
	 * Inserts a new FAQ row.
	 *
	 * @param array $data Associative array of column => value pairs.
	 * @return int|null Inserted row ID, or null on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$table  = $wpdb->prefix . HWWPFAQ_TABLE;
		$result = $wpdb->insert( $table, $data, self::build_formats( $data ) );

		return $result ? $wpdb->insert_id : null;
	}

	/**
	 * Updates an existing FAQ row.
	 *
	 * @param array $data  Columns to update.
	 * @param array $where WHERE conditions (e.g. ['id' => 5]).
	 * @return bool True on success.
	 */
	public static function update( array $data, array $where ) {
		global $wpdb;
		$table = $wpdb->prefix . HWWPFAQ_TABLE;

		return false !== $wpdb->update(
			$table,
			$data,
			$where,
			self::build_formats( $data ),
			self::build_formats( $where )
		);
	}

	/**
	 * Deletes a FAQ row by ID.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . HWWPFAQ_TABLE;

		return false !== $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Returns total number of FAQ rows.
	 *
	 * @return int
	 */
	public static function count_items() {
		global $wpdb;
		$table = $wpdb->prefix . HWWPFAQ_TABLE;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns distinct category values, alphabetically sorted.
	 *
	 * @return string[]
	 */
	public static function get_categories() {
		global $wpdb;
		$table = $wpdb->prefix . HWWPFAQ_TABLE;

		return $wpdb->get_col( "SELECT DISTINCT category FROM `{$table}` WHERE category != '' ORDER BY category ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Maps column values to wpdb format strings.
	 * Integer-type columns use %d; everything else uses %s.
	 *
	 * @param array $data Data array.
	 * @return string[]
	 */
	private static function build_formats( array $data ) {
		$int_columns = array( 'id', 'author_id', 'is_active' );
		$formats     = array();

		foreach ( array_keys( $data ) as $col ) {
			$formats[] = in_array( $col, $int_columns, true ) ? '%d' : '%s';
		}

		return $formats;
	}

	/**
	 * Returns a safe ORDER BY column name.
	 *
	 * @param string $col Requested column.
	 * @return string Validated column name.
	 */
	private static function safe_orderby( $col ) {
		$allowed = array( 'id', 'category', 'pub_date', 'created_at', 'is_active' );

		return in_array( $col, $allowed, true ) ? $col : 'pub_date';
	}
}
