<?php
/**
 * @package   Plugin Reviews
 * @author    ThemeAvenue <web@themeavenue.net>
 * @license   GPL-2.0+
 * @link      http://themeavenue.net
 * @copyright 2014 ThemeAvenue
 *
 * @wordpress-plugin
 * Plugin Name:       Plugin Reviews
 * Plugin URI:        https://github.com/ThemeAvenue/WordPress.org-Reviews
 * Description:       Fetch the reviews from your plugin page on WordPress.org and display them on your site.
 * Version:           0.2.0
 * Author:            ThemeAvenue
 * Author URI:        http://themeavenue.net
 * Text Domain:       wpascr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants
 */
define( 'WR_VERSION', '0.2.0' );
define( 'WR_URL',     trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'WR_PATH',    trailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Instanciate the plugin
 */
add_action( 'wp', array( 'WR_Reviews', 'get_instance' ) );

class WR_Reviews {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Shortcode attributes.
	 *
	 * @since  0.1.0
	 * @var array
	 */
	protected $atts;

	/**
	 * List of all the reviews.
	 *
	 * @since  0.1.0
	 * @var array
	 */
	protected $reviews = array();

	public function __construct() {

		require_once( WR_PATH . 'class-wr-wordpress-plugin.php' );
		require_once( WR_PATH . 'class-wr-review.php' );

		add_action( 'wp_print_styles',  array( $this, 'load_style' ) );
		add_action( 'wp_print_scripts', array( $this, 'load_script' ) );
		add_shortcode( 'wr_reviews', array( $this, 'shortcode' ) );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Enqueue plugin style.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function load_style() {
		wp_enqueue_style( 'wr-slick', WR_URL . 'vendor/slick/slick.css', null, '1.4.1', 'all' );
		wp_enqueue_style( 'wr-slick-theme', WR_URL . 'vendor/slick/slick-theme.css', null, '1.4.1', 'all' );
		wp_enqueue_style( 'wr-style', WR_URL . 'plugin-reviews.css', null, WR_VERSION, 'all' );
	}

	/**
	 * Load plugin script.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function load_script() {
		wp_enqueue_script( 'wr-imagesloaded', WR_URL . 'vendor/imagesloaded/imagesloaded.pkgd.min.js', array( 'jquery' ), '3.1.8', true );
		wp_enqueue_script( 'wr-slick', WR_URL . 'vendor/slick/slick.min.js', array( 'jquery' ), '1.4.1', true );
		wp_enqueue_script( 'wr-script', WR_URL . 'plugin-reviews.js', array( 'jquery', 'wr-imagesloaded', 'wr-slick' ), WR_VERSION, true );
	}

	/**
	 * Default attributes.
	 *
	 * @since  0.1.0
	 * @return array Allowed attributes with their default values
	 */
	public static function default_attributes() {

		$defaults = array(
			'plugin_slug'     => 'wordpress-reviews',
			'rating'          => 'all',
			'limit'           => 10,
			'sortby'          => 'date',
			'sort'            => 'DESC',
			'truncate'        => 300,
			'gravatar_size'   => 96,
			'container'       => 'div',
			'container_id'    => '',
			'container_class' => '',
			'link_all'        => 'no',
			'link_add'        => 'no',
			'layout'          => 'grid',
		);

		return $defaults;

	}

	/**
	 * Parse the shortcode attributes.
	 *
	 * Parse the attributes and check for forbidden values.
	 * If some values are not allowed we reset them to default.
	 *
	 * @since  0.1.0
	 * @param  array $atts Custom attributes
	 * @return array       Parsed attributes
	 */
	protected function parse_attributes( $atts ) {

		$defaults       = self::default_attributes();
		$parsed         = shortcode_atts( $defaults, $atts );
		$parsed['sort'] = strtoupper( $parsed['sort'] );

		if ( ! in_array( $parsed['sortby'], array( 'rating', 'date' ) ) ) {
			$parsed['sort'] = 'rating';
		}

		if ( ! in_array( $parsed['sort'], array( 'ASC', 'DESC' ) ) ) {
			$parsed['sortby'] = 'DESC';
		}

		if ( ! in_array( $parsed['layout'], array( 'grid', 'carousel' ) ) ) {
			$parsed['layout'] = 'grid';
		}

		$parsed['container_class'] = (array) $parsed['container_class'];

		if ( 'grid' === $parsed['layout'] ) {
			array_push( $parsed['container_class'], 'wr-grid' );
		} elseif ( 'carousel' === $parsed['layout'] ) {
			array_push( $parsed['container_class'], 'wr-carousel' );
		}

		$parsed['container_class'] = implode( ' ', $parsed['container_class'] );

		$this->atts = $parsed;

		return $parsed;

	}

	/**
	 * WR Reviews Shortcode.
	 *
	 * This shortcode will return a formatted list of reviews
	 * fetched from the requested plugin on WordPress.org.
	 *
	 * @since  0.1.0
	 * @param  array  $atts Shortcode attributes
	 * @return string       Formatted list of reviews
	 */
	public function shortcode( $atts ) {

		extract( $this->parse_attributes( $atts ) );

		$reviews  = array();
		$response = new WR_WordPress_Plugin( $plugin_slug );
		$list     = $response->get_reviews();

		if ( is_wp_error( $list ) ) {
			return sprintf( __( 'An error occured. You can <a href="%s">check out all the reviews on WordPress.org</a>', 'wordpress-reviews' ), esc_url( "https://wordpress.org/support/view/plugin-reviews/$plugin_slug" ) );
		}

		foreach ( $list as $review ) {

			$this_review = new WR_Review( $review, $this->atts['gravatar_size'], $this->atts['truncate'] );
			$this_output = $this_review->get_review();

			$this->add_review( $this_output );

		}

		return $this->merge();

	}

	/**
	 * Add a review in the list.
	 *
	 * @since  0.1.0
	 * @param  array $review Review to add
	 * @return void
	 */
	protected function add_review( $review ) {
		array_push( $this->reviews, $review );
	}

	/**
	 * Filter reviews.
	 *
	 * Filter reviews by rating. Get rid of reviews with a rating
	 * too low.
	 *
	 * @since  0.1.0
	 * @return array Filtered reviews
	 */
	protected function filter() {

		if ( 'all' === $this->atts['rating'] ) {
			return $this->reviews;
		}

		$stars = intval( $this->atts['rating'] );

		if ( $stars >= 1 && $stars <= 5 ) {

			$new = array();

			foreach ( $this->reviews as $key => $review ) {
				if ( intval( $review['rating'] ) >= $stars ) {
					$new[] = $review;
				}
			}

			$this->reviews = $new;

			return $new;

		} else {
			return $this->reviews;
		}

	}

	/**
	 * Sort the reviews.
	 *
	 * @since  0.1.0
	 * @return array Sorted reviews
	 */
	protected function sort_reviews() {

		$index   = array();
		$ordered = array();

		foreach ( $this->reviews as $key => $review ) {
			$value       = 'rating' === $this->atts['sortby'] ? $review['rating'] : $review['timestamp'];
			$index[$key] = $value;
		}

		switch ( $this->atts['sort'] ) {

			case 'DESC':
				arsort( $index );
				break;

			case 'ASC':
				asort( $index );
				break;

		}

		foreach ( $index as $key => $value ) {
			$ordered[] = $this->reviews[$key];
		}

		$this->reviews = $ordered;

		return $ordered;

	}

	/**
	 * Limit the number of reviews.
	 *
	 * @since  0.1.0
	 * @return array Reviews
	 */
	protected function limit() {

		if ( empty( $this->atts['limit'] ) || 'none' === $this->atts['limit'] ) {
			return $this->reviews;
		}

		$slice = array_slice( $this->reviews, 0, intval( $this->atts['limit'] ) );

		$this->reviews = $slice;

		return $slice;

	}

	/**
	 * Get all the reviews with final markup.
	 *
	 * This function returns an array of all the reviews with the final
	 * markup (including conveted template tags).
	 *
	 * @since  0.1.0
	 * @return array List of all the reveiws
	 */
	public function get_reviews() {

		$this->reviews_backup = $this->reviews;

		$this->filter();
		$this->sort_reviews();
		$this->limit();

		return $this->reviews;

	}

	/**
	 * Merge the reviews array into a echoable string.
	 *
	 * @since  0.1.0
	 * @return string Shortcode output
	 */
	protected function merge() {

		$output            = '';
		$links             = array();
		$label_all_reviews = apply_filters( 'wr_label_all_reviews', __( 'See all reviews', 'wordpress-reviews') );
		$label_add_review  = apply_filters( 'wr_label_add_review', __( 'Add a review', 'wordpress-reviews') );

		foreach ( $this->get_reviews() as $review ) {
			$output .= $review['output'];
		}

		if ( !empty( $this->atts['container'] ) ) {

			$attributes = array();


			if ( !empty( $this->atts['container_class'] ) ) {
				$attributes[] = "class='{$this->atts['container_class']}'";
			}

			if ( !empty( $this->atts['container_id'] ) ) {
				$attributes[] = "id='{$this->atts['container_id']}'";
			}

			$attributes = implode( ' ', $attributes );
			$output     = "<{$this->atts['container']} $attributes>$output</{$this->atts['container']}>";

		}

		if ( 'yes' == $this->atts['link_all'] ) {
			$links[] = "<a href='https://wordpress.org/support/view/plugin-reviews/{$this->atts['plugin_slug']}' target='_blank' class='wr-reviews-link-all'>$label_all_reviews</a>";
		}

		if ( 'yes' == $this->atts['link_add'] ) {
			$links[] = "<a href='https://wordpress.org/support/view/plugin-reviews/{$this->atts['plugin_slug']}#postform' target='_blank' class='wr-reviews-link-add'>$label_add_review</a>";
		}

		if ( !empty( $links ) ) {
			$links = implode( ' | ', $links );
			$links = "<p class='wr-reviews-link'>$links</p>";
			$output .= $links;
		}

		return $output;

	}

}