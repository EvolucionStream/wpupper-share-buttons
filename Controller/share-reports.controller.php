<?php
/**
 *
 * @package WPUpper Share Buttons
 * @author  Victor Freitas
 * @subpackage Controller Sharing Report
 * @version 2.3.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	 // Exit if accessed directly.
	exit(0);
}

//View
WPUSB_App::uses( 'share-report', 'View' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if ( ! class_exists( 'WP_Screen' ) ) {
	WPUSB_Utils::include_file_screen();
}

if ( ! class_exists( 'Walker_Category_Checklist' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/template.php' );
}

class WPUSB_Share_Reports_Controller extends WP_List_Table {

	/**
	 * Number for posts per page
	 *
	 * @since 1.1
	 * @var Integer
	 */
	const POSTS_PER_PAGE = 15;

	/**
	 * Number for cache time
	 *
	 * @since 1.2
	 * @var Integer
	 */
	private $cache_time;

	/**
	 * Search in list table
	 *
	 * @since 1.2
	 * @var string
	 */
	private $search;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );

		$this->cache_time = WPUSB_Utils::option( 'report_cache_time', 10, 'intval' );
		$this->search     = WPUSB_Utils::get( 's', false, 'esc_sql' );

		parent::__construct(array(
			'singular' => 'social-share-report',
			'plural'   => 'social-sharing-reports',
			'screen'   => WPUSB_Utils::add_prefix( '_sharing_report' ),
			'ajax'     => false,
		));
	}

	/**
	 * Create submenu page
	 *
	 * @since 1.0
	 * @param null
	 * @return Void
	 */
	public function menu() {
	  	add_submenu_page(
	  		WPUSB_App::SLUG,
	  		__( 'Sharing Report | WPUpper Share Buttons', WPUSB_App::TEXTDOMAIN ),
	  		__( 'Sharing Report', WPUSB_App::TEXTDOMAIN ),
	  		'manage_options',
	  		WPUSB_Setting::SHARING_REPORT,
	  		array( $this, 'report' )
	  	);
	}

	/**
	 * Search in database results relative share posts
	 *
	 * @since 1.4
	 * @global $wpdb
	 * @param Int $page
	 * @param String $orderby
	 * @param String $order
	 * @return Object
	 */
	private function _get_sharing_report( $posts_per_page, $current_page, $orderby, $order ) {
		global $wpdb;

		$offset = ( ( $current_page - 1 ) * self::POSTS_PER_PAGE );
		$cache  = get_transient( WPUSB_Setting::TRANSIENT_SHARING_REPORT );
		$table  = WPUSB_Utils::get_table_name();
		$where  = $this->_where();

		if ( ! $this->_table_exists( $wpdb, $table ) ) {
			return;
		}

		if ( ! $this->search && false !== $cache && isset( $cache[ $current_page ][ $orderby ][ $order ] ) ) {
			return $cache[ $current_page ][ $orderby ][ $order ];
		}

		$query = $wpdb->prepare(
			"SELECT * FROM `{$table}`
			 {$where}
			 ORDER BY `{$orderby}` {$order}
			 LIMIT %d OFFSET %d
			",
			$posts_per_page,
			$offset
		);

		$cache[ $current_page ][ $orderby ][ $order ] = $wpdb->get_results( $query );

		if ( ! $this->search ) {
			set_transient( WPUSB_Setting::TRANSIENT_SHARING_REPORT, $cache, $this->cache_time * MINUTE_IN_SECONDS );
		}

		return $cache[ $current_page ][ $orderby ][ $order ];
	}

	/**
	 * Get total results in wp list table for records
	 *
	 * @since 1.0
	 * @global $wpdb
	 * @param Null
	 * @return Integer
	 */
	private function _total_items() {
		global $wpdb;

		$cache = get_transient( WPUSB_Setting::TRANSIENT_SHARING_REPORT_COUNT );
		$table = WPUSB_Utils::get_table_name();

		if ( ! $this->_table_exists( $wpdb, $table ) ) {
			return 0;
		}

		if ( ! $this->search && false !== $cache ) {
			return $cache;
		}

		$where       = $this->_where( ' ' );
		$query       = "SELECT COUNT(*) FROM {$table}{$where}";
		$row_count   = $wpdb->get_var( $query );
		$total_items = intval( $row_count );

		$this->_set_cache_caunter( $total_items );

		return $total_items;
	}

	/**
	 * Create sql where post title for search
	 *
	 * @since 1.0
	 * @param String $space
	 * @return Mixed Null|String
	 */
	private function _where( $space = '' ) {
		global $wpdb;

		$search = $wpdb->esc_like( $this->search );

		return ( $search ) ? "{$space}WHERE `post_title` LIKE '%%{$search}%%'" : '';
	}

	/**
	 * Verify table exists in database
	 *
	 * @since 1.0
	 * @param Object $wpdb
	 * @param String $table
	 * @return Boolean
	 */
	private function _table_exists( $wpdb, $table ) {
		return $wpdb->query( "SHOW TABLES LIKE '{$table}'" );
	}

	/**
	 * Set transient cache items counter
	 *
	 * @since 1.0
	 * @param Integer $total_items
	 * @return Void
	 */
	private function _set_cache_caunter( $total_items ) {
		if ( ! $this->search ) {
			set_transient(
				WPUSB_Setting::TRANSIENT_SHARING_REPORT_COUNT,
				$total_items,
				$this->cache_time * MINUTE_IN_SECONDS
			);
		}
	}

	/**
	 * Insert results in column wp list table
	 *
	 * @since 1.0
	 * @param Object $item
	 * @param String $column
	 * @return Mixed String|Int
	 */
	public function column_default( $item, $column ) {
		$column = strtolower( $column );

		switch ( $column ) {
			case 'title' :
				return WPUSB_Sharing_Report_View::get_permalink_title( $item->post_id );

			case 'facebook'  :
			case 'twitter'   :
			case 'google'    :
			case 'linkedin'  :
			case 'pinterest' :
			case 'tumblr'    :
			case 'total'     :
				return WPUSB_Utils::number_format( $item->{$column} );

			case 'date' :
				return esc_attr( date_i18n( __( 'Y/m/d g:i:s a' ), strtotime( $item->post_date ) ) );
		}
	}

	/**
	 * Set column wp list table
	 *
	 * @since 1.0
	 * @param Null
	 * @return Array
	 */
	public function get_columns() {
		$columns = array(
			'Title'     => __( 'Title', WPUSB_App::TEXTDOMAIN ),
			'Facebook'  => __( 'Facebook', WPUSB_App::TEXTDOMAIN ),
			'Google'    => __( 'Google+', WPUSB_App::TEXTDOMAIN ),
			'Twitter'   => __( 'Twitter', WPUSB_App::TEXTDOMAIN ),
			'Linkedin'  => __( 'Linkedin', WPUSB_App::TEXTDOMAIN ),
			'Pinterest' => __( 'Pinterest', WPUSB_App::TEXTDOMAIN ),
			'Tumblr'    => __( 'Tumblr', WPUSB_App::TEXTDOMAIN ),
			'Total'     => __( 'Total', WPUSB_App::TEXTDOMAIN ),
			'Date'      => __( 'Post date', WPUSB_App::TEXTDOMAIN ),
		);

		return $columns;
	}

	/**
	 * Set orderby in column wp list table
	 *
	 * @since 1.0
	 * @param Null
	 * @return Array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'Title'     => array( 'post_title', true ),
			'Facebook'  => array( 'facebook', true ),
			'Google'    => array( 'google', true ),
			'Twitter'   => array( 'twitter', true ),
			'Linkedin'  => array( 'linkedin', true ),
			'Pinterest' => array( 'pinterest', true ),
			'Tumblr'    => array( 'tumblr', true ),
			'Total'     => array( 'total', true ),
			'Date'      => array( 'post_date', true ),
		);

		return $sortable_columns;
	}

	/**
	 * Prepare item for record add in wp list table
	 *
	 * @since 1.1
	 * @param Null
	 * @return Array
	 */
	public function prepare_items() {
		$orderby               = $this->_get_sql_orderby( WPUSB_Utils::get( 'orderby' ), 'total' );
		$order_type            = $this->_get_sql_order( WPUSB_Utils::get( 'order' ), 'desc' );
		$per_page              = $this->get_items_per_page( WPUSB_Utils::add_prefix( '_posts_per_page' ), self::POSTS_PER_PAGE );
		$current_page          = $this->get_pagenum();
		$total_items           = self::_total_items();
		$this->_column_headers = $this->get_column_info();

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => ceil( $total_items / $per_page ),
			'per_page'    => $per_page,
		) );

		$this->items = self::_get_sharing_report( $per_page, $current_page, $orderby, $order_type );
	}

	/**
	 * Return message in wp list table case empty records
	 *
	 * @since 1.0
	 * @param Null
	 * @return String
	 */
	public function no_items() {
		_e( 'There is no record available at the moment!', WPUSB_App::TEXTDOMAIN );
	}

	/**
	 * Set report page view
	 *
	 * @since 1.3
	 * @param null
	 * @return Void
	 */
	public function report() {
		WPUSB_Sharing_Report_View::render_sharing_report( $this );
	}

	/**
	 * Verify sql orderby param
	 *
	 * @since 1.2
	 * @param String $orderby
	 * @param String $default
	 * @return String
	 */
	private function _get_sql_orderby( $orderby, $default = '' ) {
		$permissions = array(
			'post_title' => '',
			'post_date'  => '',
			'facebook'   => '',
			'twitter'    => '',
			'google'     => '',
			'linkedin'   => '',
			'pinterest'  => '',
			'tumblr'     => '',
			'total'      => '',
		);

		return isset( $permissions[ $orderby ] ) ? $orderby : $default;
	}

	/**
	 * Verify sql order param
	 *
	 * @since 1.0
	 * @param String $order
	 * @param String $default
	 * @return String
	 */
	private function _get_sql_order( $order, $default = '' ) {
		$permissions = array( 'desc' => '', 'asc'  => '' );
		$order       = isset( $permissions[ $order ] ) ? $order : $default;

		return strtoupper( $order );
	}
}