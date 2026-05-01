<?php
/**
 * Responses data helper.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Responses {

	/**
	 * Get paginated responses.
	 */
	public static function get_responses( $args = [] ) {
		global $wpdb;
		$table = $wpdb->prefix . 'exitsurvey_responses';

		$defaults = [
			'per_page'     => 10,
			'page'         => 1,
			'trigger_type' => '',
			'search'       => '',
			'orderby'      => 'created_at',
			'order'        => 'DESC',
		];
		$args = wp_parse_args( $args, $defaults );

		$where  = [ '1=1' ];
		$params = [];

		if ( $args['trigger_type'] ) {
			$where[]  = 'trigger_type = %s';
			$params[] = $args['trigger_type'];
		}
		if ( $args['search'] ) {
			$where[]  = '(answer LIKE %s OR question_text LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $args['page'] - 1 ) * $args['per_page'];
		$order     = in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ] ) ? $args['order'] : 'DESC';
		$orderby   = sanitize_sql_orderby( $args['orderby'] . ' ' . $order ) ?: 'created_at DESC';

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
		$params[] = $args['per_page'];
		$params[] = $offset;

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, array_slice( $params, 0, count($params) - 2 ) ) )
			: $wpdb->get_var( $count_sql );

		return [
			'rows'  => $rows ?: [],
			'total' => (int) $total,
			'pages' => $args['per_page'] > 0 ? ceil( $total / $args['per_page'] ) : 1,
		];
	}

	/**
	 * Aggregate stats for the dashboard.
	 */
	public static function get_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'exitsurvey_responses';

		return [
			'total'           => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
			'today'           => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()" ),
			'by_trigger'      => $wpdb->get_results( "SELECT trigger_type, COUNT(*) as count FROM {$table} GROUP BY trigger_type ORDER BY count DESC", ARRAY_A ),
			'avg_cart_value'  => (float) $wpdb->get_var( "SELECT AVG(cart_value) FROM {$table} WHERE cart_value > 0" ),
			'top_answers'     => $wpdb->get_results( "SELECT answer, COUNT(*) as count FROM {$table} GROUP BY answer ORDER BY count DESC LIMIT 10", ARRAY_A ),
		];
	}

	/**
	 * Delete a response by ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( $wpdb->prefix . 'exitsurvey_responses', [ 'id' => (int) $id ] );
	}
}
