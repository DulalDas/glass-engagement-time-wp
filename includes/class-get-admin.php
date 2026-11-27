<?php
/**
 * Admin screens.
 *
 * @package GlassEngagementTime
 */

defined( 'ABSPATH' ) || exit;

class GET_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_get_save', array( __CLASS__, 'save' ) );
		add_filter( 'manage_posts_columns', array( __CLASS__, 'col' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'col_val' ), 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_query' ) );
	}

	public static function menu() {
		add_management_page( __( 'Engagement Time', 'glass-engagement-time' ), __( 'Engagement Time', 'glass-engagement-time' ), 'edit_others_posts', 'glass-engagement-time', array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'tools_page_glass-engagement-time' === $hook ) {
			wp_enqueue_style( 'get-admin', GET_URL . 'assets/admin.css', array(), GET_VERSION );
		}
	}

	public static function col( $cols ) {
		$cols['get_eng'] = __( 'Reading time', 'glass-engagement-time' );
		return $cols;
	}

	public static function col_val( $name, $id ) {
		if ( 'get_eng' !== $name ) {
			return;
		}
		$s = GET_Store::stats( $id );
		if ( ! $s['views'] ) {
			echo '&mdash;';
			return;
		}
		echo esc_html( GET_Store::fmt( $s['avg_secs'] ) . ' · ' . $s['avg_scroll'] . '% · ' . number_format_i18n( $s['views'] ) );
	}

	public static function sortable( $c ) {
		$c['get_eng'] = 'get_seconds_avg';
		return $c;
	}

	public static function sort_query( $q ) {
		if ( is_admin() && $q->is_main_query() && 'get_seconds_avg' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', 'get_seconds' );
			$q->set( 'orderby', 'meta_value_num' );
		}
	}

	public static function page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'glass-engagement-time' ) );
		}
		$set   = GET_Store::settings();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		$q = new WP_Query( array(
			'post_type'      => (array) $set['post_types'],
			'post_status'    => 'publish',
			'posts_per_page' => 25,
			'meta_key'       => 'get_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );
		$tv = 0; $tsecs = 0; $treads = 0;
		$rows = array();
		foreach ( $q->posts as $p ) {
			$s    = GET_Store::stats( $p->ID );
			$est  = GET_Store::estimate_seconds( $p->ID );
			$rows[] = array( $p, $s, $est );
			$tv    += $s['views'];
			$tsecs += $s['avg_secs'] * $s['views'];
			$treads += $s['reads'];
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved = isset( $_GET['get_saved'] );
		echo '<div class="wrap"><div class="gl-wrap"><h1>' . esc_html__( 'Engagement Time', 'glass-engagement-time' ) . '</h1>';
		echo '<p class="gl-sub">' . esc_html__( 'How long people actually read your posts. No cookies, no IP addresses stored, nothing leaves your site.', 'glass-engagement-time' ) . '</p>';
		if ( $saved ) {
			echo '<div class="gl-note ok">' . esc_html__( 'Settings saved.', 'glass-engagement-time' ) . '</div>';
		}
		echo '<div class="gl-grid" style="margin-bottom:16px">';
		printf( '<div class="gl-stat"><b>%s</b><span>%s</span></div>', esc_html( number_format_i18n( $tv ) ), esc_html__( 'Measured page views', 'glass-engagement-time' ) );
		printf( '<div class="gl-stat"><b>%s</b><span>%s</span></div>', esc_html( GET_Store::fmt( $tv ? (int) round( $tsecs / $tv ) : 0 ) ), esc_html__( 'Average active time', 'glass-engagement-time' ) );
		printf( '<div class="gl-stat"><b>%s</b><span>%s</span></div>', esc_html( $tv ? round( $treads / $tv * 100 ) . '%' : '0%' ), esc_html__( 'Engaged readers', 'glass-engagement-time' ) );
		echo '</div><div class="gl-card"><h2>' . esc_html__( 'Top posts', 'glass-engagement-time' ) . '</h2>';
		if ( ! $rows ) {
			echo '<p class="gl-sub">' . esc_html__( 'No data yet. Numbers appear after visitors read a post (logged-in editors are skipped by default).', 'glass-engagement-time' ) . '</p>';
		} else {
			echo '<table><thead><tr><th>' . esc_html__( 'Post', 'glass-engagement-time' ) . '</th><th>' . esc_html__( 'Views', 'glass-engagement-time' ) . '</th><th>' . esc_html__( 'Avg time', 'glass-engagement-time' ) . '</th><th>' . esc_html__( 'Estimated', 'glass-engagement-time' ) . '</th><th>' . esc_html__( 'Avg scroll', 'glass-engagement-time' ) . '</th><th>' . esc_html__( 'Reached end', 'glass-engagement-time' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				list( $p, $s, $est ) = $r;
				$ratio = $est ? round( $s['avg_secs'] / $est * 100 ) : 0;
				echo '<tr><td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></td>';
				echo '<td>' . esc_html( number_format_i18n( $s['views'] ) ) . '</td><td>' . esc_html( GET_Store::fmt( $s['avg_secs'] ) ) . ' <span class="gl-pill">' . esc_html( $ratio ) . '%</span></td><td>' . esc_html( GET_Store::fmt( $est ) ) . '</td><td>' . esc_html( $s['avg_scroll'] ) . '%</td>';
				echo '<td>' . esc_html( $s['views'] ? round( $s['d100'] / $s['views'] * 100 ) . '%' : '0%' ) . '</td></tr>';
			}
			echo '</tbody></table><p class="gl-sub" style="margin-top:12px">' . esc_html__( 'The grey percentage is real time as a share of the estimated reading time (about 238 words per minute). Under 40% often means people skim or leave early.', 'glass-engagement-time' ) . '</p>';
		}
		echo '</div>';
		echo '<form method="post" class="gl-card" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'get_save' );
		echo '<input type="hidden" name="action" value="get_save"><h2>' . esc_html__( 'Settings', 'glass-engagement-time' ) . '</h2><p style="margin:0 0 6px;color:#b4bce0;font-size:13px">' . esc_html__( 'Measure these content types', 'glass-engagement-time' ) . '</p>';
		foreach ( $types as $t ) {
			echo '<label style="display:inline-block;margin-right:18px;color:#fff"><input type="checkbox" name="types[]" value="' . esc_attr( $t->name ) . '"' . checked( in_array( $t->name, (array) $set['post_types'], true ), true, false ) . '> ' . esc_html( $t->labels->name ) . '</label>';
		}
		echo '<label style="color:#fff;margin-top:14px"><input type="checkbox" name="skip" value="1"' . checked( ! empty( $set['skip_editors'] ), true, false ) . '> ' . esc_html__( 'Do not measure logged-in users who can edit posts', 'glass-engagement-time' ) . '</label>';
		echo '<div class="gl-grid"><div><label for="get-sec">' . esc_html__( 'Engaged reader: at least this many seconds', 'glass-engagement-time' ) . '</label><input type="number" id="get-sec" name="sec" min="5" max="600" value="' . esc_attr( (string) $set['engaged_sec'] ) . '"></div><div><label for="get-pct">' . esc_html__( '...and scrolled at least this percent', 'glass-engagement-time' ) . '</label><input type="number" id="get-pct" name="pct" min="10" max="100" value="' . esc_attr( (string) $set['engaged_pct'] ) . '"></div></div>';
		echo '<p style="margin-top:14px"><button class="gl-btn" type="submit">' . esc_html__( 'Save settings', 'glass-engagement-time' ) . '</button></p></form>';
		echo '<p class="gl-credit">Created by <a href="https://www.facebook.com/share/1CJkZsQtna/" target="_blank" rel="noopener noreferrer">Dulal Das</a></p></div></div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'glass-engagement-time' ), 403 );
		}
		check_admin_referer( 'get_save' );
		$valid = array_keys( get_post_types( array( 'public' => true ) ) );
		$types = isset( $_POST['types'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['types'] ) ), $valid ) ) : array();
		update_option( 'get_settings', array(
			'post_types'   => $types ? $types : array( 'post' ),
			'skip_editors' => empty( $_POST['skip'] ) ? 0 : 1,
			'engaged_sec'  => max( 5, min( 600, isset( $_POST['sec'] ) ? absint( $_POST['sec'] ) : 30 ) ),
			'engaged_pct'  => max( 10, min( 100, isset( $_POST['pct'] ) ? absint( $_POST['pct'] ) : 50 ) ),
		), false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'glass-engagement-time', 'get_saved' => 1 ), admin_url( 'tools.php' ) ) );
		exit;
	}
}
