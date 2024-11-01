<?php
/*
Plugin Name: Live Sales Notifier for WooCommerce
Plugin URI: http://www.mrwebsolution.in/
Description: It's a very simple plugin for publish resent order notification on your site.
Author: WP Experts Team
Author URI: https://www.wp-experts.in
Version: 1.4
WC tested up to: 6.9.4
*/
/**
License GPL2
Copyright 2018-2022   WP-EXPERTS.IN (email  raghunath.0087@gmail.com)

This program is free software; you can redistribute it andor modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if(!class_exists('WpSalesNotifierAdmin'))
{
    class WpSalesNotifierAdmin    {
        /**
         * Construct the plugin object
         */
        public function __construct()   {
			// allow shortcode for text widget 
			add_filter('widget_text','do_shortcode');
            // register actions
			add_action('admin_init', array(&$this, 'wsn_admin_init'));
			add_action('admin_menu', array(&$this, 'wsn_add_menu'));
			add_action('init', array(&$this, 'init_wp_sales_notifier'));
			add_shortcode('wpsalesnotifier',array(&$this,'wpsn_shortcode_func'));
			
			add_action('wp_head',array(&$this,'wsn_footer_func'));

			/** register_activation_hook */
			register_activation_hook( __FILE__, array(&$this, 'init_wsn_activate' ) );
			/** register_deactivation_hook */
			register_deactivation_hook( __FILE__, array(&$this, 'init_wsn_deactivate' ) );
			
			add_filter( "plugin_action_links_".plugin_basename( __FILE__ ), array(&$this,'wsn_settings_link' ));
			
			add_action( 'wp_enqueue_scripts', array(&$this,'wsn_scripts_method' ));
			add_action( 'admin_bar_menu', array(&$this,'toolbar_link_to_wsn'), 999 );
			
        } // END public function __construct
		
		/**
		* check enable or not
		*/
		function init_wp_sales_notifier()
		{
			if(!is_admin()){
				$wsn_enable = get_option('wsn_enable');
				if($wsn_enable){
				add_action( 'wp_enqueue_scripts',array(&$this, 'wsn_enqueue_styles' ));
			   }
			}
		}
		/**
		 * hook into WP's admin_init action hook
		 */
		public function wsn_admin_init()	{
			// Set up the settings for this plugin
			$this->wsn_init_settings();
			// Possibly do additional admin_init tasks
		} // END public static function activate
		/**
		 * Initialize some custom settings
		 */     
		public function wsn_init_settings()	{
			// register the settings for this plugin
			register_setting('wsn-group', 'wsn_enable');
			register_setting('wsn-group', 'wsn_display_date');
			register_setting('wsn-group', 'wsn_delay_time');
			register_setting('wsn-group', 'wsn_order_status');
		} // END public function init_custom_settings()
		/**
		 * add a menu
		 */     
		public function wsn_add_menu() {
			add_submenu_page('woocommerce','WP Sales Notifier Settings', 'Sales Notifier', 'manage_options', 'wp_sales_notifier', array(&$this, 'wsn_settings_page'));
		} // END public function add_menu()
       /**
		 * hook to add link under adminmenu bar
		 */		
		public function toolbar_link_to_wsn( $wp_admin_bar ) {
			$args = array(
				'id'    => 'wcsn_menu_bar',
				'title' => 'WC Sales Notifier Pro',
				'href'  => admin_url('admin.php?page=wp_sales_notifier'),
				'meta'  => array( 'class' => 'wcsn-toolbar-page' )
			);
			$wp_admin_bar->add_node( $args );
			//second lavel
			$wp_admin_bar->add_node( array(
				'id'    => 'wcsn-second-sub-item',
				'parent' => 'wcsn_menu_bar',
				'title' => 'Settings',
				'href'  => admin_url('admin.php?page=wp_sales_notifier'),
				'meta'  => array(
					'title' => __('Settings'),
					'target' => '_self',
					'class' => 'wcsn_menu_item_class'
				),
			));
		}
		/**
		 * Menu Callback
		 */     
		public function wsn_settings_page()	{
			if(!current_user_can('manage_options'))
			{
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}

			// Render the settings template
			include(sprintf("%s/lib/settings.php", dirname(__FILE__)));
			//include(sprintf("%s/css/admin.css", dirname(__FILE__)));
			// Style Files
			wp_register_style( 'wsn_admin_style', plugins_url( 'css/wsn-admin.css',__FILE__ ) );
			wp_enqueue_style( 'wsn_admin_style' );
			// JS files
			wp_register_script('wsn_admin_script', plugins_url('/js/wsn-admin.js',__FILE__ ), array('jquery'));
            wp_enqueue_script('wsn_admin_script');
		} // END public function plugin_settings_page()
		
	public static function wsn_footer_func( $attr ) {
		$wsn_enable = get_option('wsn_enable'); 
		if(!$wsn_enable) return 'Enable plugin';
		 
		$wsn_delay_time = get_option('wsn_delay_time') ? get_option('wsn_delay_time') : '5000';
		$wsn_display_date = get_option('wsn_display_date') ? get_option('wsn_display_date') : '';
		$wsn_order_types = get_option('wsn_order_status') ? get_option('wsn_order_status') : '';
		
		//print_r($wsn_order_types); //exit;
		
		$html= '';
		$filters = array(
		'post_status' => $wsn_order_types,
		'post_type' => 'shop_order',
		'posts_per_page' => 10,
		'paged' => 1,
		'orderby' => 'ID',
		'order' => 'DESC' 
		);

		$orderloop = new WP_Query($filters);
       // echo $orderloop->request; exit;
       if( $orderloop->have_posts() ){
		$html.= '<div id="wpsn-slideshow">';
		while ($orderloop->have_posts()) {
		$orderloop->the_post();
		$order = new WC_Order($orderloop->post->ID);
		if(count($order->get_items()) > 0) {
         foreach ($order->get_items() as $key => $lineItem) {
			$html.=' <div class="wpsn-inner">';

		   if ( has_post_thumbnail( $lineItem['product_id'] ) ){ 
                        $attachment_ids[0] = get_post_thumbnail_id( $lineItem['product_id'] );
                         $attachment = wp_get_attachment_image_src($attachment_ids[0], 'thumbnail' );  
                        $html.='<div class="wsn-image"><img src="'.$attachment[0].'" class="card-image"  /></div>';
                    } 
		   
            $html.='<div class="wsn-content">
                              <span class="wsn-title"><a href="'.get_the_permalink($lineItem['product_id']).'">'.($lineItem['name']).'</a></span>
                              <span class="wsn-buyer">
                                 <span>Bought by</span>
                                 '.get_post_meta($orderloop->post->ID,'_billing_first_name',true).' 
                                 from '.get_post_meta($orderloop->post->ID,'_billing_city',true).' 
                              </span>';
                              
                              if(get_option('wsn_display_date'))
                              {
                              $html.='<span class="wsn-time">
                                 <span style="font-size:80%;">'.human_time_diff(get_post_time('U',false,$lineItem['order_id']), current_time('timestamp')) . " " . __('ago').'</span>
                              </span>';
						      }   
                     $html.='</div><div class="clear"></div></div>';
				   }
			   }
		   
		}
        $html .= '</div>';
             
         }else{
				   $html.='<p>No order found</p>';
				   }  
				      
		_e ( wp_kses( $html, array( 
            'a' => array(
                'href' => array(),
                'title' => array(),
                'class' => array(),
            ),
            'span' => array(
                'class' => array(),
                'id' => array()
                ),
            'div' => array(
                'class' => array(),
                'style' => array('display'),
                'id' => array()
                ),
            'img' => array(
                'src' => array(),
                'class' => array(),
                'alt' => array()
                ),
            'script' => array(),
        ) ) );
        
	  }
	  /* Shortcode 
	  * @hooks wp_footer
	  */
       public static function wpsn_shortcode_func($attr)  {
		 
		$wsn_delay_time = get_option('wsn_delay_time') ? get_option('wsn_delay_time') : '5000';
		//shortcode delay time
		$wsn_delay_time = isset( $attr['delay'] ) ? $attr['delay'] : $wsn_delay_time;
		
		$wsn_display_date = get_option('wsn_display_date') ? get_option('wsn_display_date') : '';
		//shortcode show date
		$wsn_display_date = isset( $attr['date'] ) ? $attr['date'] : $wsn_display_date;
		
		
		$wsn_order_types = get_option('wsn_order_status') ? get_option('wsn_order_status') : '';
		//shortcode order status
		$wsn_order_types = isset( $attr['status'] ) ? $attr['status'] : $wsn_order_types;
		
		//print_r($wsn_order_types); //exit;
		
		$html= '';
		$filters = array(
		'post_status' => $wsn_order_types,
		'post_type' => 'shop_order',
		'posts_per_page' => 10,
		'paged' => 1,
		'orderby' => 'ID',
		'order' => 'DESC' 
		);

		$orderloop = new WP_Query($filters);
       // echo $orderloop->request; exit;
       if( $orderloop->have_posts() ){
		$html.= '<div id="wpsn-shortcode">';
		while ($orderloop->have_posts()) {
		$orderloop->the_post();
		$order = new WC_Order($orderloop->post->ID);
		if(count($order->get_items()) > 0)
		{
         foreach ($order->get_items() as $key => $lineItem) {
			$html.=' <div class="wpsn-inner">';

		   if ( has_post_thumbnail( $lineItem['product_id'] ) ){ 
                        $attachment_ids[0] = get_post_thumbnail_id( $lineItem['product_id'] );
                         $attachment = wp_get_attachment_image_src($attachment_ids[0], 'thumbnail' );  
                        $html.='<div class="wsn-image"><img src="'.$attachment[0].'" class="card-image"  /></div>';
                    } 
		   
            $html.='<div class="wsn-content">
                              <span class="wsn-title"><a href="'.get_the_permalink($lineItem['product_id']).'">'.($lineItem['name']).'</a></span>
                              <span class="wsn-buyer">
                                 <span>Bought by</span>
                                 '.get_post_meta($orderloop->post->ID,'_billing_first_name',true).' 
                                 from '.get_post_meta($orderloop->post->ID,'_billing_city',true).' 
                              </span>';
                              
                              if(get_option('wsn_display_date'))
                              {
                              $html.='<span class="wsn-time">
                                 <span style="font-size:80%;">'.human_time_diff(get_post_time('U',false,$lineItem['order_id']), current_time('timestamp')) . " " . __('ago').'</span>
                              </span>';
						      }   
                     $html.='</div><div class="clear"></div></div>';
				   }
			   }
		   
		}
        $html .= '</div>';
  
         }else{
				   $html.='<p>No order found</p>';
				   }  
		
		
		$script =' jQuery("#wpsn-shortcode > div:gt(0)").hide();
					setInterval(function() { 
					  jQuery("#wpsn-shortcode > div:first")
						.hide(1000)
						.next()
						.show(1000)
						.end()
						.appendTo("#wpsn-shortcode");
						
					},  '.$wsn_delay_time.');
                ';
               
        wp_register_script( 'wpsn-shortcode', '', array("jquery"), '', true );
        wp_enqueue_script( 'wpsn-shortcode'  );
        wp_add_inline_script( 'wpsn-shortcode', $script );
	
		wp_enqueue_style( 'wsn-shortcode',  get_stylesheet_uri()  );
        wp_add_inline_style( 'wsn-shortcode', '#wpsn-shortcode {margin: 1% auto; position: relative; width: auto; height: auto;padding: 10px; transition: all .2s ease-in-out;box-shadow: 0 0 20px rgba(0,0,0,0.15); z-index: 999; background: #fff;} #wpsn-shortcode {clear:both}#wpsn-shortcode .wpsn-inner{min-width:250px;display:none;}  #wpsn-shortcode .wpsn-inner:first-child{display:block;} #wpsn-shortcode .wpsn-inner  img{display:inline-block;margin:5px 10px;height:80px;width:auto;position:relative;overflow:hidden;padding:0px;}#wpsn-shortcode .wpsn-inner span.wsn-title { display: block;font-weight: bold;position: relative;font-size:14px;}#wpsn-shortcode .wpsn-inner span.wsn-buyer {/* display: inline-block; */font-size: 12px;}#wpsn-shortcode .wpsn-inner span.wsn-time { font-size: 14px;font-weight: bold;display: block;position: relative;}#wpsn-shortcode .wsn-content, #wpsn-shortcode .wsn-image {display: inline-block;vertical-align: top;}' );
	
    
		return $html;
		
		
	  }
	/*-------------------------------------------------
	Enqueue Style
	------------------------------------------------- */
	
	public function wsn_enqueue_styles() {
	
	wp_enqueue_style( 'wsn-stylesheet', get_stylesheet_uri() );
    wp_add_inline_style( 'wsn-stylesheet', '#wpsn-slideshow {margin: 1% auto; position: fixed;bottom:20px; right:10px;width: auto; height: auto;padding: 10px 5px; transition: all .2s ease-in-out;box-shadow: 0 0 20px rgba(0,0,0,0.15);clear:both; border-radius:10px; z-index: 999; background: #fff;} #wpsn-slideshow .wpsn-inner  {min-width:250px;display:none;} #wpsn-slideshow .wpsn-inner:first-child{display:block;}#wpsn-slideshow .wpsn-inner  img{display:inline-block;margin:5px 10px;height:80px;width:auto;position:relative;overflow:hidden;padding:0px;}#wpsn-slideshow .wpsn-inner span.wsn-title { display: block;font-weight: bold;position: relative;font-size:14px;}#wpsn-slideshow .wpsn-inner span.wsn-buyer {/* display: inline-block; */font-size: 12px;}#wpsn-slideshow .wpsn-inner span.wsn-time { font-size: 14px;font-weight: bold;display: block;position: relative;}#wpsn-slideshow .wsn-content, #wpsn-slideshow .wsn-image {display: inline-block;vertical-align: top;}  ' );
    
      $wsn_delay_time = get_option('wsn_delay_time') ? get_option('wsn_delay_time') : '5000';
      $script =' jQuery("#wpsn-slideshow > div:gt(0)").hide();
					setInterval(function() { 
					  jQuery("#wpsn-slideshow > div:first")
						.hide(1000)
						.next()
						.show(1000)
						.end()
						.appendTo("#wpsn-slideshow");
						
					},  '.$wsn_delay_time.');
                ';
               
        wp_add_inline_script( 'jquery-core', $script );

	}
	/*-------------------------------------------------
	End Social Share Buttons Style
	------------------------------------------------- */
	// Add the settings link to the plugins page
	public function wsn_settings_link($links)
	{ 
		$settings_link = '<a href="admin.php?page=wp_sales_notifier">Settings</a>'; 
		$settings_link .= ' | <a href="https://rgaddons.wordpress.com/wp-sales-notifier-pro/" target="_blank">Go Pro</a>'; 
		array_unshift($links, $settings_link); 
		return $links; 
	}
	/**
	* Enqueue jquery
	*
	* Tha callback is hooked to 'wp_enqueue_script' to ensure the script is only enqueued on the front-end.
	*/
	public function wsn_scripts_method() {
	wp_enqueue_script( 'jquery' );
	}
	
   /**
	 * Activate the plugin
	 */
	public static function init_wsn_activate()
	{
		if ( !is_plugin_active('woocommerce/woocommerce.php')){
	    // Throw an error in the wordpress admin console
        $error_message = __('This plugin requires <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'woocommerce');
        die($error_message);
		}
	} // END public static function activate

	/**
	 * Deactivate the plugin
	 */     
	public static function init_wsn_deactivate()
	{
		// Do nothing
	} // END public static function deactivate	
		
   } // END class WpSalesNotifierAdmin
} // END if(!class_exists('WpSalesNotifierAdmin'))

if(class_exists('WpSalesNotifierAdmin'))
{
    // instantiate the plugin class
    $wsn_plugin_template = new WpSalesNotifierAdmin;
}
