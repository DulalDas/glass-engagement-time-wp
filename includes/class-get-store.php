<?php
/**
 * Stores engagement totals in post meta. No personal data is saved.
 *
 * @package GlassEngagementTime
 */

defined( 'ABSPATH' ) || exit;

class GET_Store {

	const KEYS = array( 'get_views', 'get_seconds', 'get_scroll_sum', 'get_reads', 'get_d25', 'get_d50', 'get_d75', 'get_d100' );

	public static function settings() {
		$d = array(
			'post_types'   => array( 'post' ),
			'skip_editors' => 1,
			'engaged_sec'  => 30,
			'engaged_pct'  => 50,
		);
		$s = get_option( 'get_settings', array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), $d );
	}

	/** Words per minute used for the reading time estimate. */
	const WPM = 238;

	public static function estimate_seconds( $post_id ) {
		$words = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ) );
		return (int) max( 10, round( $words / self::WPM * 60 ) );
	}

	/**
	 * Add one measurement chunk to a post.
	 *
	 * @param int  $post_id Post ID.
	 * @param int  $secs    Active seconds in this chunk.
	 * @param int  $pct     Max scroll percent reached in this chunk.
	 * @param bool $first   First chunk of a page view.
	 */
	public static function add( $post_id, $secs, $pct, $first ) {
		$secs = max( 0, min( 3600, (int) $secs ) );
		$pct  = max( 0, min( 100, (int) $pct ) );
		$set  = self::settings();
		if ( $first ) {
			self::inc( $post_id, 'get_views', 1 );
			self::inc( $post_id, 'get_scroll_sum', $pct );
			foreach ( array( 25, 50, 75, 100 ) as $d ) {
				if ( $pct >= $d ) {
					self::inc( $post_id, 'get_d' . $d, 1 );
				}
			}
		}
		if ( $secs ) {
			self::inc( $post_id, 'get_seconds', $secs );
		}
		if ( $first && $secs >= (int) $set['engaged_sec'] && $pct >= (int) $set['engaged_pct'] ) {
			self::inc( $post_id, 'get_reads', 1 );
		}
	}

	private static function inc( $post_id, $key, $by ) {
		$cur = (int) get_post_meta( $post_id, $key, true );
		update_post_meta( $post_id, $key, $cur + (int) $by );
	}

	public static function stats( $post_id ) {
		$v = (int) get_post_meta( $post_id, 'get_views', true );
		$s = (int) get_post_meta( $post_id, 'get_seconds', true );
		return array(
			'views'      => $v,
			'avg_secs'   => $v ? (int) round( $s / $v ) : 0,
			'avg_scroll' => $v ? (int) round( (int) get_post_meta( $post_id, 'get_scroll_sum', true ) / $v ) : 0,
			'reads'      => (int) get_post_meta( $post_id, 'get_reads', true ),
			'd25'        => (int) get_post_meta( $post_id, 'get_d25', true ),
			'd50'        => (int) get_post_meta( $post_id, 'get_d50', true ),
			'd75'        => (int) get_post_meta( $post_id, 'get_d75', true ),
			'd100'       => (int) get_post_meta( $post_id, 'get_d100', true ),
		);
	}

	public static function fmt( $secs ) {
		$secs = (int) $secs;
		if ( $secs < 60 ) {
			return $secs . 's';
		}
		return floor( $secs / 60 ) . 'm ' . str_pad( (string) ( $secs % 60 ), 2, '0', STR_PAD_LEFT ) . 's';
	}
}
