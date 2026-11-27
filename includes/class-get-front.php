<?php
/**
 * Front end script and REST endpoint.
 *
 * @package GlassEngagementTime
 */

defined( 'ABSPATH' ) || exit;

class GET_Front {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function enqueue() {
		if ( ! is_singular() ) {
			return;
		}
		$set = GET_Store::settings();
		$id  = get_queried_object_id();
		if ( ! $id || ! in_array( get_post_type( $id ), (array) $set['post_types'], true ) || 'publish' !== get_post_status( $id ) ) {
			return;
		}
		if ( $set['skip_editors'] && current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_enqueue_script( 'glass-engagement-time', GET_URL . 'assets/track.js', array(), GET_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'glass-engagement-time', 'GlassEngagement', array(
			'url'  => esc_url_raw( rest_url( 'glass-engagement/v1/hit' ) ),
			'post' => $id,
		) );
	}

	public static function routes() {
		register_rest_route( 'glass-engagement/v1', '/hit', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'hit' ),
			// Public on purpose: visitors are anonymous. Input is validated and rate limited below.
			'permission_callback' => '__return_true',
			'args'                => array(
				'post'  => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'secs'  => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'pct'   => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'first' => array( 'required' => false, 'sanitize_callback' => 'absint' ),
			),
		) );
	}

	private static function is_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		return '' === $ua || (bool) preg_match( '/bot|crawl|spider|slurp|headless|lighthouse|preview|facebookexternalhit|curl|wget|python|php\//', $ua );
	}

	public static function hit( WP_REST_Request $req ) {
		$ok = new WP_REST_Response( array( 'ok' => true ), 202 );
		$id = (int) $req['post'];
		$set = GET_Store::settings();
		if ( ! $id || 'publish' !== get_post_status( $id ) || ! in_array( get_post_type( $id ), (array) $set['post_types'], true ) || post_password_required( $id ) ) {
			return new WP_Error( 'get_invalid', 'Invalid post.', array( 'status' => 400 ) );
		}
		if ( self::is_bot() ) {
			return $ok;
		}
		// Short lived limiter so one browser cannot flood the counters. The key is a hash and expires in seconds.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$key = 'get_rl_' . substr( hash_hmac( 'sha256', $ip . '|' . $ua . '|' . $id, wp_salt() ), 0, 24 );
		$n   = (int) get_transient( $key );
		if ( $n >= 6 ) {
			return $ok;
		}
		set_transient( $key, $n + 1, 60 );
		GET_Store::add( $id, (int) $req['secs'], (int) $req['pct'], (bool) $req['first'] );
		return $ok;
	}
}
