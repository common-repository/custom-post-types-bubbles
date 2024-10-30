<?php
/**
 * Plugin Name: Custom post types bubbles
 * Description: Easily add notifications bubble with counters in Post Types to display either pending or draft posts.
 * Version: 2.1
 * Author: Andreas Sofantzis
 * Author URI : https://www.83pixel.com
 * Domain Path: /languages
 * Text Domain: cpt-bubbles
 * 
 */
defined( 'ABSPATH' ) or die( 'Nop. Sorry!' );

$cpt_bubbles = new CPT_Bubbles();

class CPT_Bubbles
{

	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts_and_styles' ) );

		add_action( 'plugins_loaded', array($this, 'cpt_bubbles_textdomain') );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_filter( 'add_menu_classes', array($this, 'bubble_count_number'));

		add_action( 'wp_ajax_save_cptb_options', array($this, 'save_cptb_options'));
	}

	function cpt_bubbles_textdomain() 
	{
		load_plugin_textdomain( 'cptb', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );
	}

	// Add the 'CPT Bubbles' menu in settings
	public function admin_menu()
	{
		add_options_page(
			'Custom Post Type Bubbles',
			'CPT Bubbles',
			'manage_options',
			'cpt-bubbles',
			array(
				$this,
				'render_settings_page'
			)
		);

	}

	// Function callback to render settings page in admin
	public function render_settings_page() 
	{
		$post_statuses = array(
			'draft'			=> __('Draft'),
			'pending'		=> __('Pending Review'),
			'private'		=> __('Private'),
			'publish'		=> __('Published'),
			'future'		=> __('Future')
		);

		$args = array(
		   'public'   => true,
		);

		$output = 'names';
		$operator = 'and';

		$post_types = get_post_types( $args, $output, $operator );

		$options = get_option('cptb_options');

		if ($options)
		{
			$options = unserialize($options);
			
			$options_array = array();

			foreach ($options as $option)
			{
				$options_array[$option['cpt']][$option['status']] = $option['color'];
			}
		}

		?>
		<div id="cptb" class="wrap">
			
			<h2>Custom Post Type Bubbles</h2>

			<div class="description">

				<?= __('Please choose any Post Type you want to show the bubbles.', 'cptb'); ?>
				<?= __('You can choose any color combinations for your Post types and your Post statuses.', 'cptb'); ?>

			</div>
			
			<form id="cptb-form"> 

				<ul id="cptb-cpt-list">

					<?php foreach ( $post_types  as $post_type_slug ) : ?>
					
						<?php $post_type = get_post_type_object( $post_type_slug ); ?>

						<li class="cpt-item <?php if (is_array($options) && array_key_exists($post_type_slug, $options_array)) echo 'is-active'; ?>">

							<button class="js-toggle-cpt toggle-cpt" data-cpt="<?= $post_type_slug; ?>"><?php $this->svg_caret_arrow(); ?> <?php echo $post_type->labels->singular_name ?></button>

							<div class="post-status-list-wrapper">

								<ul class="post-status-list">
									
									<?php foreach ($post_statuses as $post_status_slug => $post_status) : ?>

										<li class="post-status-item <?php if (is_array($options) && array_key_exists($post_type_slug, $options_array) && array_key_exists($post_status_slug, $options_array[$post_type_slug])) echo 'is-active'; ?>">

											<button class="js-toggle-status toggle-status" data-cpt="<?= $post_type_slug ?>" data-post-status="<?= $post_status_slug; ?>" <?php if (is_array($options) && array_key_exists($post_type_slug, $options_array) && array_key_exists($post_status_slug, $options_array[$post_type_slug])) { echo 'style="background:'.$options_array[$post_type_slug][$post_status_slug].';border-color:'.$options_array[$post_type_slug][$post_status_slug].';"'; echo 'data-color="' . $options_array[$post_type_slug][$post_status_slug] . '"'; } ?>>
											
												<?= $post_status; ?>

											</button>

											<span class="colorpicker"></span>

										</li>

									<?php endforeach; ?>

								</ul>

							</div>

						</li>

					<?php endforeach; ?>

				</ul>

				<?php wp_nonce_field('cptb-nonce', 'cptb-nonce'); ?>

				<button class="js-cptb-save-btn cptb-save-btn"><?= __('Save', 'cptb'); ?></button>

			</form>

		</div>
		<?php
	}

	// Calculate and display count number
	public function bubble_count_number( $menu ) 
	{

		$options = get_option('cptb_options');

		if ($options)
		{
			$options = unserialize($options);
			
			foreach ($options as $option)
			{
				$cpt = $option['cpt'];
				$status = $option['status'];
				$color = $option['color'];
				$count = 0;

				$num_posts = wp_count_posts($cpt, 'readable');

				if ( !empty($num_posts->$status) )
				{
					$count += $num_posts->$status;
				}

		        if ($cpt == 'post') 
		        {
		            $menu_str = 'edit.php';
		        } 
		        else 
		        {
		            $menu_str = 'edit.php?post_type=' . $cpt;
		        }

		        // loop through $menu items, find match, add indicator
    	        foreach( $menu as $menu_key => $menu_data ) 
    	        {
					if( $menu_str == $menu_data[2] )
					{
						$menu[$menu_key][0] .= " <span class='update-plugins count-$count' style='background-color:" . $color . "'><span class='plugin-count'>" . number_format_i18n($count) . '</span></span>';
					}
    	        }
			}
		}

	    return $menu;
	}

	// Admin styles
	public function admin_scripts_and_styles() 
	{
		wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_style( 'cptb', plugins_url('assets/css/cptb.min.css', __FILE__) );
        wp_enqueue_script( 'cptb', plugins_url('assets/js/cptb.min.js', __FILE__), array( 'wp-color-picker' ), false, true );
    }

    function save_cptb_options()
    {
		$nonce = $_POST['nonce'];

		if (!isset($nonce) || !wp_verify_nonce($nonce, 'cptb-nonce' ))
	    {
	        exit();
	    }

    	$options = $_POST['cptb_options'];

    	$update = update_option('cptb_options', serialize($options));

    	wp_send_json($update);
    }

    function svg_caret_arrow()
    {
    	?>

    	<svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
    		 width="307.046px" height="307.046px" viewBox="0 0 307.046 307.046" style="enable-background:new 0 0 307.046 307.046;"
    		 xml:space="preserve">
    		<g id="_x34_84._Forward">
    			<g>
    				<path d="M239.087,142.427L101.259,4.597c-6.133-6.129-16.073-6.129-22.203,0L67.955,15.698c-6.129,6.133-6.129,16.076,0,22.201
    					l115.621,115.626L67.955,269.135c-6.129,6.136-6.129,16.086,0,22.209l11.101,11.101c6.13,6.136,16.07,6.136,22.203,0
    					l137.828-137.831C245.222,158.487,245.222,148.556,239.087,142.427z"/>
    			</g>
    		</g>
		</svg>
    	<?php
    }
}
?>