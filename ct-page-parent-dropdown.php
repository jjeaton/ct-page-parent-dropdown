<?php
/*
 * Plugin Name: CSS-Tricks Page Dropdown
 * Plugin URI: http://www.josheaton.org/wordpress-plugins/ct-page-dropdown
 * Description: Replaces the page attributes dropdown with a better UI using Select2.
 * Version: 0.1.0
 * Author: Josh Eaton
 * Author URI: http://www.josheaton.org/
 * Contributors: jjeaton
 * Textdomain: ct-page-dropdown
 */

class CT_Page_Dropdown
{
	private static $instance;

	private function __construct() {}

	public static function instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new CT_Page_Dropdown;
			self::$instance->includes();
			self::$instance->init();
		}

		return self::$instance;
	}

	private function init() {

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_head',            array( $this, 'admin_styles' ) );
		add_action( 'admin_menu',            array( $this, 'remove_parent_div_metabox' ) );
		add_action( 'add_meta_boxes',        array( $this, 'add_parent_div_metabox' ), 10, 2 );

		add_action( 'wp_ajax_ct_page_query', array( $this, 'handle_ajax_request' ) );
	}

	private function includes() {
		include_once 'includes/class-walker.php';
	}

	public function admin_enqueue_scripts() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style( 'select2-style', plugins_url( 'js/lib//select2/select2.css', __FILE__ ), array(), '3.4.5' );
		wp_enqueue_script( 'select2', plugins_url( 'js/lib/select2/select2.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'ct-page-dropdown', plugins_url( 'js/page-dropdown' . $suffix . '.js', __FILE__ ), array( 'jquery', 'select2' ) );
	}

	public function admin_styles() {
		?>
<style type="text/css">
span.page-dropdown-url { font-size: 10px; color: #888; }
.select2-results { max-height: 320px; }
</style>
<?php
	}

	public function remove_parent_div_metabox() {

		remove_meta_box( 'pageparentdiv', 'page', 'side' );
	}

	public function add_parent_div_metabox( $post_type, $post ) {
		add_meta_box( 'pageparentdiv', 'page' == $post_type ? __( 'Page Attributes' ) : __( 'Attributes' ), array( $this, 'page_attributes_meta_box' ), 'page', 'side', 'default' );
	}

	/**
	 * Display page attributes form fields.
	 *
	 * @since 2.7.0
	 *
	 * @param object $post
	 */
	function page_attributes_meta_box( $post ) {
	?>
	<p><strong><?php _e('Parent') ?></strong></p>
	<label class="screen-reader-text" for="parent_id"><?php _e('Parent') ?></label>
	<?php if ( 0 != $post->post_parent ) { ?>
		<input type="hidden" class="parent-dropdown" style="width: 100%;" name="parent_id" id="parent_id" value="<?php echo esc_attr( $post->post_parent ); ?>" data-title="<?php echo esc_attr( get_the_title( $post->post_parent ) ); ?>">
	<?php } else { ?>
		<input type="hidden" class="parent-dropdown" style="width: 100%;" name="parent_id" id="parent_id">
	<?php }
	wp_nonce_field( 'ct-page-dropdown-search', '_ajax_ct_page_dropdown_search', false );

		if ( 'page' == $post->post_type && 0 != count( get_page_templates() ) ) {
			$template = !empty($post->page_template) ? $post->page_template : false;
			?>
	<p><strong><?php _e('Template') ?></strong></p>
	<label class="screen-reader-text" for="page_template"><?php _e('Page Template') ?></label><select name="page_template" id="page_template">
	<option value='default'><?php _e('Default Template'); ?></option>
	<?php page_template_dropdown($template); ?>
	</select>
	<?php
		} ?>
	<p><strong><?php _e('Order') ?></strong></p>
	<p><label class="screen-reader-text" for="menu_order"><?php _e('Order') ?></label><input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr($post->menu_order) ?>" /></p>
	<p><?php if ( 'page' == $post->post_type ) _e( 'Need help? Use the Help tab in the upper right of your screen.' ); ?></p>
	<?php
	}

	protected static function walk_page_tree( $pages, $depth ) {
		$walker = new CT_Walker_PageDropdown;
		$args = array( $pages, $depth );
		return call_user_func_array( array( $walker, 'walk' ), $args );
	}

	/**
	 * Performs page search query
	 *
	 * @param array $args Optional. Accepts any WP_Query arguments
	 * @return array Results.
	 */
	public static function page_query( $args = array() ) {

		$defaults = array(
			'post_type'              => 'page',
			'suppress_filters'       => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'post_status'            => 'publish',
			'posts_per_page'         => 10,
			'orderby'                => 'parent',
			'order'                  => 'ASC'
		);

		$query = wp_parse_args( $args, $defaults );

		$args['pagenum'] = isset( $args['pagenum'] ) ? absint( $args['pagenum'] ) : 1;

		if ( isset( $args['s'] ) )
			$query['s'] = $args['s'];

		$query['offset'] = $args['pagenum'] > 1 ? $query['posts_per_page'] * ( $args['pagenum'] - 1 ) : 0;

		// Do main query.
		$get_posts = new WP_Query;
		$posts = $get_posts->query( $query );

		// Check if any posts were found.
		if ( ! $get_posts->post_count )
			return false;

		// Get page hierarchies
		self::walk_page_tree( $posts, 0 );

		// Build results.
		$results = array();
		$results['pages'] = CT_Walker_PageDropdown::get_walked_pages();
		$results['total'] = $get_posts->found_posts;

		return $results;
	}

	public function handle_ajax_request() {

		check_ajax_referer( 'ct-page-dropdown-search', '_ajax_ct_page_dropdown_search' );

		$post_id        = intval( $_GET['ct_post_id'] );
		$posts_per_page = intval( $_GET['ct_posts_per_page'] );
		$s              = wp_unslash( $_GET['ct_s'] );
		$page           = $_GET['ct_page'];

		$args = array(
			'posts_per_page' => $posts_per_page,
			's'              => $s,
			'pagenum'        => $page,
			'post__not_in'   => array( $post_id ),
		);

		$data = self::page_query( $args );

		if ( false === $data ) {
			return wp_send_json_success( array( 'total' => 0, 'pages' => array() ) );
		} else {
			return wp_send_json_success( $data );
		}
	}
}

add_action( 'plugins_loaded', array( 'CT_Page_Dropdown', 'instance' ) );
