<?php
/*
Plugin Name: Smart Reporter for e-commerce
Plugin URI: http://www.storeapps.org/product/smart-reporter/
Description: <strong>Lite Version Installed.</strong> Store analysis like never before. 
Version: 2.1
Author: Store Apps
Author URI: http://www.storeapps.org/about/
Copyright (c) 2011, 2012, 2013 Store Apps All rights reserved.
*/

//Hooks
register_activation_hook ( __FILE__, 'sr_activate' );
register_deactivation_hook ( __FILE__, 'sr_deactivate' );

define ( 'IS_WOO16', version_compare ( WOOCOMMERCE_VERSION, '2.0', '<' ) ); // Flag for Handling Woo 2.0 and above

/**
 * Registers a plugin function to be run when the plugin is activated.
 */
function sr_activate() {
	global $wpdb, $blog_id;        
	
        if ( false === get_site_option( 'sr_is_auto_refresh' ) ) {
            update_site_option( 'sr_is_auto_refresh', 'no' );
            update_site_option( 'sr_what_to_refresh', 'all' );
            update_site_option( 'sr_refresh_duration', '5' );
        }
        
        if ( is_multisite() ) {
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}", 0 );
	} else {
		$blog_ids = array( $blog_id );
	}
	foreach ( $blog_ids as $blog_id ) {
		if ( ( file_exists ( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) && ( is_plugin_active ( 'woocommerce/woocommerce.php' ) ) ) {
			$wpdb_obj = clone $wpdb;
			$wpdb->blogid = $blog_id;
			$wpdb->set_prefix( $wpdb->base_prefix );
			$create_table_query = "
				CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sr_woo_order_items` (
				  `product_id` bigint(20) unsigned NOT NULL default '0',
				  `order_id` bigint(20) unsigned NOT NULL default '0',
				  `product_name` text NOT NULL,
				  `quantity` int(10) unsigned NOT NULL default '0',
				  `sales` decimal(11,2) NOT NULL default '0.00',
				  `discount` decimal(11,2) NOT NULL default '0.00',
				  KEY `product_id` (`product_id`),
				  KEY `order_id` (`order_id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
			";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	   		dbDelta( $create_table_query );
	   		
			add_action( 'load_sr_woo_order_items', 'load_sr_woo_order_items' );
	   		do_action( 'load_sr_woo_order_items', $wpdb );
	   		$wpdb = clone $wpdb_obj;
		}
	}
}

/**
 * Registers a plugin function to be run when the plugin is deactivated.
 */
function sr_deactivate() {
	global $wpdb;
	if ( is_multisite() ) {
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}", 0 );
	} else {
		$blog_ids = array( $blog_id );
	}
	foreach ( $blog_ids as $blog_id ) {
		$wpdb_obj = clone $wpdb;
		$wpdb->blogid = $blog_id;
		$wpdb->set_prefix( $wpdb->base_prefix );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}sr_woo_order_items" );
		$wpdb = clone $wpdb_obj;
	}
}

function get_latest_version($plugin_file) {
	$sr_plugin_info = get_site_transient ( 'update_plugins' );
	$latest_version = $sr_plugin_info->response [$plugin_file]->new_version;
	return $latest_version;
}

function get_user_sr_version($plugin_file) {
	$sr_plugin_info = get_plugins ();
	$user_version = $sr_plugin_info [$plugin_file] ['Version'];
	return $user_version;
}

function is_pro_updated() {
	$user_version = get_user_sr_version (SR_PLUGIN_FILE);
	$latest_version = get_latest_version (SR_PLUGIN_FILE);
	return version_compare ( $user_version, $latest_version, '>=' );
}

/**
 * Throw an error on admin page when WP e-Commerece plugin is not activated.
 */
if ( is_admin () || ( is_multisite() && is_network_admin() ) ) {
	// BOF automatic upgrades
	include ABSPATH . 'wp-includes/pluggable.php';
	
	$plugin = plugin_basename ( __FILE__ );
	define ( 'SR_PLUGIN_DIR',dirname($plugin));
	define ( 'SR_PLUGIN_DIR_ABSPATH', dirname(__FILE__) );
	define ( 'SR_PLUGIN_FILE', $plugin );
	define ( 'STORE_APPS_URL', 'http://www.storeapps.org/' );
	
	define ( 'ADMIN_URL', get_admin_url () ); //defining the admin url
	define ( 'SR_PLUGIN_DIRNAME', plugins_url ( '', __FILE__ ) );
	define ( 'SR_IMG_URL', SR_PLUGIN_DIRNAME . '/resources/themes/images/' );        

	// EOF
	
	add_action ( 'admin_notices', 'sr_admin_notices' );
	add_action ( 'admin_init', 'sr_admin_init' );
	
	if ( is_multisite() && is_network_admin() ) {
		
		function sr_add_license_key_page() {
			$page = add_submenu_page ('settings.php', 'Smart Reporter', 'Smart Reporter', 'manage_options', 'sr-settings', 'sr_settings_page' );
			add_action ( 'admin_print_styles-' . $page, 'sr_admin_styles' );
		}
		
		if (file_exists ( (dirname ( __FILE__ )) . '/pro/sr.js' ))
			add_action ('network_admin_menu', 'sr_add_license_key_page', 11);
			
	}
	
	function sr_admin_init() {
		$plugin_info 	= get_plugins ();
		$sr_plugin_info = $plugin_info [SR_PLUGIN_FILE];
		$ext_version 	= '4.0.1';
		if (is_plugin_active ( 'woocommerce/woocommerce.php' ) && is_plugin_active ( basename(WPSC_URL).'/wp-shopping-cart.php' )) {
			define('WPSC_WOO_ACTIVATED',true);
		} elseif (is_plugin_active ( basename(WPSC_URL).'/wp-shopping-cart.php' )) {
			define('WPSC_ACTIVATED',true);
		} elseif (is_plugin_active ( 'woocommerce/woocommerce.php' )) {
			define('WOO_ACTIVATED', true);
		}
		
		wp_register_script ( 'sr_ext_all', plugins_url ( 'resources/ext/ext-all.js', __FILE__ ), array (), $ext_version );
		if ($_GET['post_type'] == 'wpsc-product' || $_GET['page'] == 'smart-reporter-wpsc') {
			wp_register_script ( 'sr_main', plugins_url ( '/sr/smart-reporter.js', __FILE__ ), array ('sr_ext_all' ), $sr_plugin_info ['Version'] );
			define('WPSC_RUNNING', true);
			define('WOO_RUNNING', false);
			// checking the version for WPSC plugin
			define ( 'IS_WPSC37', version_compare ( WPSC_VERSION, '3.8', '<' ) );
			define ( 'IS_WPSC38', version_compare ( WPSC_VERSION, '3.8', '>=' ) );
			if ( IS_WPSC38 ) {		// WPEC 3.8.7 OR 3.8.8
				define('IS_WPSC387', version_compare ( WPSC_VERSION, '3.8.8', '<' ));
				define('IS_WPSC388', version_compare ( WPSC_VERSION, '3.8.8', '>=' ));
			}
		} else if ($_GET['post_type'] == 'product' || $_GET['page'] == 'smart-reporter-woo') {
			if (isset($_GET['tab']) && $_GET['tab'] == "smart_reporter_old") {
				wp_register_script ( 'sr_main', plugins_url ( '/sr/smart-reporter-woo.js', __FILE__ ), array ('sr_ext_all' ), $sr_plugin_info ['Version'] );	
			}

			define('WPSC_RUNNING', false);
			define('WOO_RUNNING', true);
			// checking the version for WooCommerce plugin
			define ( 'IS_WOO13', version_compare ( WOOCOMMERCE_VERSION, '1.4', '<' ) );                          

			//WooCommerce Currency Constants
			define ( 'SR_CURRENCY_SYMBOL', get_woocommerce_currency_symbol());
			define ( 'SR_DECIMAL_PLACES', get_option( 'woocommerce_price_num_decimals' ));
		}
		wp_register_style ( 'sr_ext_all', plugins_url ( 'resources/css/ext-all.css', __FILE__ ), array (), $ext_version );
		wp_register_style ( 'sr_main', plugins_url ( '/sr/smart-reporter.css', __FILE__ ), array ('sr_ext_all' ), $sr_plugin_info ['Version'] );
		
		if (file_exists ( (dirname ( __FILE__ )) . '/pro/sr.js' )) {
			wp_register_script ( 'sr_functions', plugins_url ( '/pro/sr.js', __FILE__ ), array ('sr_main' ), $sr_plugin_info ['Version'] );
			define ( 'SRPRO', true );
		} else {
			define ( 'SRPRO', false );
		}
		
		if (SRPRO === true) {
			include ('pro/upgrade.php');
		}



		// ================================================================================================
		//Registering scripts and stylesheets for SR Beta Version
		// ================================================================================================

		if ( !wp_script_is( 'jquery' ) ) {
            wp_enqueue_script( 'jquery' );
        }

        wp_enqueue_script ( 'sr_jqplot_js', plugins_url ( 'resources/jqplot/jquery.jqplot.min.js', __FILE__ ),array('jquery'));
        wp_register_script ( 'sr_jqplot_high', plugins_url ( 'resources/jqplot/jqplot.highlighter.min.js', __FILE__ ), array ('sr_jqplot_js' ));
        wp_register_script ( 'sr_jqplot_cur', plugins_url ( 'resources/jqplot/jqplot.cursor.min.js', __FILE__ ), array ('sr_jqplot_high' ));
        wp_register_script ( 'sr_jqplot_render', plugins_url ( 'resources/jqplot/jqplot.categoryAxisRenderer.min.js', __FILE__ ), array ('sr_jqplot_cur' ));
        wp_register_script ( 'sr_jqplot_date_render', plugins_url ( 'resources/jqplot/jqplot.dateAxisRenderer.min.js', __FILE__ ), array ('sr_jqplot_render' ));
        wp_enqueue_script ( 'sr_datepicker', plugins_url ( 'resources/jquery.datepick.package/jquery.datepick.js', __FILE__ ), array ('sr_jqplot_date_render' ));
        wp_register_script ( 'sr_jqplot_all_scripts', plugins_url ( 'resources/jqplot/jqplot.BezierCurveRenderer.min.js', __FILE__ ), array ('sr_datepicker' ), $sr_plugin_info ['Version']);

        wp_register_style ( 'font_awesome', plugins_url ( "resources/font-awesome/css/font-awesome.min.css", __FILE__ ), array ());
		wp_register_style ( 'sr_datepicker_css', plugins_url ( 'resources/jquery.datepick.package/redmond.datepick.css', __FILE__ ), array ('font_awesome'));
		wp_register_style ( 'sr_jqplot_all', plugins_url ( 'resources/jqplot/jquery.jqplot.min.css', __FILE__ ), array ('sr_datepicker_css'));
		wp_register_style ( 'sr_main_beta', plugins_url ( '/sr/smart-reporter.css', __FILE__ ), array ('sr_jqplot_all' ), $sr_plugin_info ['Version'] );
		// ================================================================================================


	}

	
	function sr_admin_notices() {
		if (! is_plugin_active ( 'woocommerce/woocommerce.php' ) && ! is_plugin_active ( basename(WPSC_URL).'/wp-shopping-cart.php' )) {
			echo '<div id="notice" class="error"><p>';
			_e ( '<b>Smart Reporter</b> add-on requires <a href="http://www.storeapps.org/wpec/">WP e-Commerce</a> plugin or <a href="http://www.storeapps.org/woocommerce/">WooCommerce</a> plugin. Please install and activate it.' );
			echo '</p></div>', "\n";
		}
	}
	
	function sr_admin_scripts() {		
		if (file_exists ( (dirname ( __FILE__ )) . '/pro/sr.js' )) {
			wp_enqueue_script ( 'sr_functions' );
		}
	}
	
	function sr_admin_styles() {
		wp_enqueue_style ( 'sr_main' );
	}
	
	function woo_add_modules_sr_admin_pages() {


		$page = add_submenu_page ('woocommerce', 'Smart Reporter', 'Smart Reporter', 'manage_woocommerce', 'smart-reporter-woo','admin_page');

		if ($_GET ['action'] != 'sr-settings') { // not be include for settings page
			add_action ( 'admin_print_scripts-' . $page, 'sr_admin_scripts' );
		}
		add_action ( 'admin_print_styles-' . $page, 'sr_admin_styles' );
	}
	add_action ('admin_menu', 'woo_add_modules_sr_admin_pages');
	
	
	function admin_page(){
        global $woocommerce;
        

        $tab = ( !empty($_GET['tab'] )  ? ( $_GET['tab'] ) : 'smart_reporter_beta' )   ;

        ?>

        <div style = "margin:0.7em 0.5em 0 0" class="wrap woocommerce">

            <h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=smart-reporter-woo') ?>" class="nav-tab <?php echo ($tab == 'smart_reporter_beta') ? 'nav-tab-active' : ''; ?>">Smart Reporter <sup style="vertical-align: super;color:red; font-size:15px">Beta</sub></a>
                <a href="<?php echo admin_url('admin.php?page=smart-reporter-woo&tab=smart_reporter_old') ?>" class="nav-tab <?php echo ($tab == 'smart_reporter_old') ? 'nav-tab-active' : ''; ?>">Smart Reporter</a>
            </h2>

            <?php
                switch ($tab) {
                    case "smart_reporter_old" :
                        sr_show_console();
                    break;
                    default :
                    	sr_beta_show_console();
                    break;
                }

            ?>

	    </div>
	    <?php

    }
    

	function wpsc_add_modules_sr_admin_pages($page_hooks, $base_page) {
		$page = add_submenu_page ( $base_page, 'Smart Reporter', 'Smart Reporter', 'manage_options', 'smart-reporter-wpsc', 'sr_show_console' );
		add_action ( 'admin_print_styles-' . $page, 'sr_admin_styles' );
		if ($_GET ['action'] != 'sr-settings') { // not be include for settings page
			add_action ( 'admin_print_scripts-' . $page, 'sr_admin_scripts' );
		}
		$page_hooks [] = $page;
		return $page_hooks;
	}
	add_filter ( 'wpsc_additional_pages', 'wpsc_add_modules_sr_admin_pages', 10, 2 );
	
	add_action( 'woocommerce_order_actions', 'sr_woo_refresh_order' );			// Action to be performed on clicking 'Save Order' button from Order panel

	// Actions on order change
	add_action( 'woocommerce_order_status_pending', 	'sr_woo_remove_order' );
	add_action( 'woocommerce_order_status_failed', 		'sr_woo_remove_order' );
	add_action( 'woocommerce_order_status_refunded', 	'sr_woo_remove_order' );
	add_action( 'woocommerce_order_status_cancelled', 	'sr_woo_remove_order' );
	add_action( 'woocommerce_order_status_on-hold', 	'sr_woo_add_order' );
	add_action( 'woocommerce_order_status_processing', 	'sr_woo_add_order' );
	add_action( 'woocommerce_order_status_complete', 	'sr_woo_add_order' );

	function sr_woo_refresh_order( $order_id ) {
		sr_woo_remove_order( $order_id );
		$order_status = wp_get_object_terms( $order_id, 'shop_order_status', array('fields' => 'slugs') );
		if ( $order_status[0] == 'on-hold' || $order_status[0] == 'processing' || $order_status[0] == 'completed' )
			sr_woo_add_order( $order_id );
	}
        
        function sr_get_attributes_name_to_slug() {
            global $wpdb;
            
            $attributes_name_to_slug = array();
            
            $query = "SELECT DISTINCT meta_value AS product_attributes,
                             post_id AS product_id
                      FROM {$wpdb->prefix}postmeta
                      WHERE meta_key LIKE '_product_attributes'
                    ";
            $results = $wpdb->get_results( $query, 'ARRAY_A' );
            
            foreach ( $results as $result ) {
                $attributes = maybe_unserialize( $result['product_attributes'] );
                if ( count( $attributes ) > 0 ) {
                    foreach ( $attributes as $slug => $attribute ) {
                        $attributes_name_to_slug[ $result['product_id'] ][ $attribute['name'] ] = $slug;
                    }
                }
            }
            
            return $attributes_name_to_slug;
        }
        
        function sr_get_term_name_to_slug( $taxonomy_prefix = '' ) {
            global $wpdb;
            
            if ( !empty( $taxonomy_prefix ) ) {
                $where = "WHERE term_taxonomy.taxonomy LIKE '$taxonomy_prefix%'";
            } else {
                $where = '';
            }
            
            $query = "SELECT terms.slug, terms.name, term_taxonomy.taxonomy
                      FROM {$wpdb->prefix}terms AS terms
                          LEFT JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy USING ( term_id )
                      $where
                    ";
            $results = $wpdb->get_results( $query, 'ARRAY_A' );
            $term_name_to_slug = array();
            foreach ( $results as $result ) {
                if ( count( $result ) <= 0 ) continue;
                if ( !isset( $term_name_to_slug[ $result['taxonomy'] ] ) ) {
                    $term_name_to_slug[ $result['taxonomy'] ] = array();
                }
                $term_name_to_slug[ $result['taxonomy'] ][ $result['name'] ] = $result['slug'];
            }
            
            return $term_name_to_slug;
        }
	
        function sr_get_variation_attribute( $order_id ) {
            
                global  $wpdb;
                $query_variation_ids = "SELECT order_itemmeta.meta_value
                                        FROM {$wpdb->prefix}woocommerce_order_items AS order_items
                                        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta
                                        ON (order_items.order_item_id = order_itemmeta.order_item_id)
                                        WHERE order_itemmeta.meta_key LIKE '_variation_id'
                                        AND order_itemmeta.meta_value > 0
                                        AND order_items.order_id IN ($order_id)";
                                        
                $result_variation_ids  = $wpdb->get_col ( $query_variation_ids );

                $query_variation_att = "SELECT postmeta.post_id AS post_id,
                                        GROUP_CONCAT(postmeta.meta_value
                                        ORDER BY postmeta.meta_id
                                        SEPARATOR ', ' ) AS meta_value
                                        FROM {$wpdb->prefix}postmeta AS postmeta
                                        WHERE postmeta.meta_key LIKE 'attribute_%'
                                        AND postmeta.post_id IN (". implode(",",$result_variation_ids) .")
                                        GROUP BY postmeta.post_id";

                $results_variation_att  = $wpdb->get_results ( $query_variation_att , 'ARRAY_A');

                $variation_att_all = array(); 

                for ( $i=0;$i<sizeof($results_variation_att);$i++ ) {
                    $variation_att_all [$results_variation_att [$i]['post_id']] = $results_variation_att [$i]['meta_value'];
                }
        }

        function sr_items_to_values( $all_order_items = array() ) {
            global $wpdb;

            if ( count( $all_order_items ) <= 0 || !defined( 'IS_WOO16' ) ) return $all_order_items;
            $values = array();
            $attributes_name_to_slug = sr_get_attributes_name_to_slug();
            $prefix = ( defined( 'IS_WOO16' ) && IS_WOO16 ) ? '' : '_';


            foreach ( $all_order_items as $order_id => $order_items ) {
                foreach ( $order_items as $item ) {
                        $order_item = array();

                        $order_item['order_id'] = $order_id;

                        if( ! function_exists( 'get_product' ) ) {
                            $product_id = ( !empty( $prefix ) && isset( $item[$prefix.'id'] ) ) ? $item[$prefix.'id'] : $item['id'];
                        } else {
                            $product_id = ( !empty( $prefix ) && isset( $item[$prefix.'product_id'] ) ) ? $item[$prefix.'product_id'] : $item['product_id'];
                        }// end if

                        $order_item['product_name'] = get_the_title( $product_id );
                        $variation_id = ( !empty( $prefix ) && isset( $item[$prefix.'variation_id'] ) ) ? $item[$prefix.'variation_id'] : $item['variation_id'];
                        $order_item['product_id'] = ( $variation_id > 0 ) ? $variation_id : $product_id;

                        if ( $variation_id > 0 ) {
                                $variation_name = array();
                                if( ! function_exists( 'get_product' ) && count( $item['item_meta'] ) > 0 ) {
                                    foreach ( $item['item_meta'] as $items ) {
                                        $variation_name[ 'attribute_' . $items['meta_name'] ] = $items['meta_value'];
                                    }
                                } else {
                                    foreach ( $item as $item_meta_key => $item_meta_value ) {
                                        if ( array_key_exists( $item_meta_key, $attributes_name_to_slug[$product_id] ) ) {
                                            $variation_name[ 'attribute_' . $item_meta_key ] = ( is_array( $item_meta_value ) && isset( $item_meta_value[0] ) ) ? $item_meta_value[0] : $item_meta_value;
                                        } elseif ( in_array( $item_meta_key, $attributes_name_to_slug[$product_id] ) ) {
                                            $variation_name[ 'attribute_' . $item_meta_key ] = ( is_array( $item_meta_value ) && isset( $item_meta_value[0] ) ) ? $item_meta_value[0] : $item_meta_value;
                                        }
                                    }
                                }
                                
                                $order_item['product_name'] .= ' (' . woocommerce_get_formatted_variation( $variation_name, true ) . ')'; 
                        }

                        $order_item['quantity'] = ( !empty( $prefix ) && isset( $item[$prefix.'qty'] ) ) ? $item[$prefix.'qty'] : $item['qty'];
                        $line_total = ( !empty( $prefix ) && isset( $item[$prefix.'line_total'] ) ) ? $item[$prefix.'line_total'] : $item['line_total'];
                        $order_item['sales'] = $line_total;
                        $line_subtotal = ( !empty( $prefix ) && isset( $item[$prefix.'line_subtotal'] ) ) ? $item[$prefix.'line_subtotal'] : $item['line_subtotal'];
                        $order_item['discount'] = $line_subtotal - $line_total;

                        if ( empty( $order_item['product_id'] ) || empty( $order_item['order_id'] ) || empty( $order_item['quantity'] ) ) 
                            continue;
                        $values[] = "( {$order_item['product_id']}, {$order_item['order_id']}, '{$order_item['product_name']}', {$order_item['quantity']}, " . (empty($order_item['sales']) ? 0 : $order_item['sales'] ) . ", " . (empty($order_item['discount']) ? 0 : $order_item['discount'] ) . " )";
                }
            }
            return $values;
        }
        
        function sr_woo_add_order( $order_id ) {
            	global $wpdb;
		$order = new WC_Order( $order_id );

		$order_items = array( $order_id => $order->get_items() );
		

		$insert_query = "INSERT INTO {$wpdb->prefix}sr_woo_order_items 
							( `product_id`, `order_id`, `product_name`, `quantity`, `sales`, `discount` ) VALUES ";
                
                $values = sr_items_to_values( $order_items );
                
                if ( count( $values ) > 0 ) {
                    $insert_query .= implode( ',', $values );                                   
                    $wpdb->query( $insert_query );
                }
        }
	
	function sr_woo_remove_order( $order_id ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}sr_woo_order_items WHERE order_id = {$order_id}" );
	}
	
	// Function to load table sr_woo_order_items
	function load_sr_woo_order_items( $wpdb ) {

                $insert_query = "REPLACE INTO {$wpdb->prefix}sr_woo_order_items 
                                                                ( `product_id`, `order_id`, `product_name`, `quantity`, `sales`, `discount` ) VALUES ";

                $all_order_items = array();
                
		// WC's code to get all order items
                if( IS_WOO16 ) {
                    $results = $wpdb->get_results ("
                            SELECT meta.post_id AS order_id, meta.meta_value AS items FROM {$wpdb->posts} AS posts

                            LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
                            LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
                            LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
                            LEFT JOIN {$wpdb->terms} AS term USING( term_id )

                            WHERE 	meta.meta_key 		= '_order_items'
                            AND 	posts.post_type 	= 'shop_order'
                            AND 	posts.post_status 	= 'publish'
                            AND 	tax.taxonomy		= 'shop_order_status'
                            AND		term.slug			IN ('completed', 'processing', 'on-hold')
                    ", 'ARRAY_A');

                    foreach ( $results as $result ) {
                            $all_order_items[ $result['order_id'] ] = maybe_unserialize( $result['items'] ); 
                    }
                    
                } else {
                        $results = $wpdb->get_col ("
                                SELECT posts.ID AS order_id FROM {$wpdb->posts} AS posts

                                LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
                                LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
                                LEFT JOIN {$wpdb->terms} AS term USING( term_id )

                                WHERE 	posts.post_type 	= 'shop_order'
                                AND 	posts.post_status 	= 'publish'
                                AND 	tax.taxonomy		= 'shop_order_status'
                                AND	term.slug	IN ('completed', 'processing', 'on-hold')
                                ");
                      
                        $order_id = implode( ", ", $results);
                        
                        $query_order_items = "SELECT order_items.order_item_id,
                                                    order_items.order_id    ,
                                                    order_items.order_item_name AS order_prod,
                                            GROUP_CONCAT(order_itemmeta.meta_key
                                            ORDER BY order_itemmeta.meta_id
                                            SEPARATOR '###' ) AS meta_key,
                                            GROUP_CONCAT(order_itemmeta.meta_value
                                            ORDER BY order_itemmeta.meta_id
                                            SEPARATOR '###' ) AS meta_value
                                            FROM {$wpdb->prefix}woocommerce_order_items AS order_items
                                            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta
                                            ON (order_items.order_item_id = order_itemmeta.order_item_id)
                                            WHERE order_items.order_id IN ($order_id)
                                            GROUP BY order_items.order_item_id
                                            ORDER BY FIND_IN_SET(order_items.order_id,'$order_id')";
                                        
                        $results  = $wpdb->get_results ( $query_order_items , 'ARRAY_A');          
                        
                        foreach ( $results as $result ) {
                            $order_item_meta_values = explode('###', $result ['meta_value'] );
                            $order_item_meta_key = explode('###', $result ['meta_key'] );
                            if ( count( $order_item_meta_values ) != count( $order_item_meta_key ) )
                                continue; 
                            $order_item_meta_key_values = array_combine($order_item_meta_key, $order_item_meta_values);
                            if ( !isset( $all_order_items[ $result['order_id'] ] ) ) {
                                $all_order_items[ $result['order_id'] ] = array();
                            }
                            $all_order_items[ $result['order_id'] ][] = $order_item_meta_key_values;
                        }
                } //end if
              
                $values = sr_items_to_values( $all_order_items );
                
                if ( count( $values ) > 0 ) {
                    $insert_query .= implode( ',', $values );
                    $wpdb->query( $insert_query );
                }
	}
	

	function sr_console_common() {

		?>
		<div class="wrap">
		<!-- <div id="icon-smart-reporter" class="icon32"><br /> -->
		</div>
		<style>
		    div#TB_window {
		        background: lightgrey;
		    }
		</style>    
		<?php if ( SRPRO === true && function_exists( 'sr_support_ticket_content' ) ) sr_support_ticket_content(); 

		if (WPSC_RUNNING === true) {
			$json_filename = 'json';
		} else if (WOO_RUNNING === true) {
			$json_filename = 'json-woo';
		}
		define ( 'SR_JSON_URL', SR_PLUGIN_DIRNAME . "/sr/$json_filename.php" );
		
		//set the number of days data to show in lite version.
		define ( 'SR_AVAIL_DAYS', 30);
		
		$latest_version = get_latest_version (SR_PLUGIN_FILE );
		$is_pro_updated = is_pro_updated ();
		
		if ($_GET ['action'] == 'sr-settings') {
			sr_settings_page (SR_PLUGIN_FILE);
		} else {
			$base_path = WP_PLUGIN_DIR . '/' . str_replace ( basename ( __FILE__ ), "", plugin_basename ( __FILE__ ) ) . 'sr/';
		?>
		<div class="wrap">
		<div id="icon-smart-reporter" class="icon32"><img alt="Smart Reporter"
			src="<?php echo SR_IMG_URL.'/logo.png'; ?>"></div>
		<h2><?php
		echo _e ( 'Smart Reporter' );
		echo ' ';
			if (SRPRO === true) {
				echo _e ( 'Pro' );
			} else {
				echo _e ( 'Lite' );
			}
		?>
   	<p class="wrap" style="font-size: 12px">
	   	<span style="float: right"> <?php
			if ( SRPRO === true && ! is_multisite() ) {
				
				if (WPSC_RUNNING == true) {
					$plug_page = 'wpsc';
				} elseif (WOO_RUNNING == true) {
					$plug_page = 'woo';
				}
			} else {
				$before_plug_page = '';
				$after_plug_page = '';
				$plug_page = '';
			}

			if ( SRPRO === true ) {
	            if ( !wp_script_is( 'thickbox' ) ) {
	                if ( !function_exists( 'add_thickbox' ) ) {
	                    require_once ABSPATH . 'wp-includes/general-template.php';
	                }
	                add_thickbox();
	            }
	            $before_plug_page = '<a href="admin.php#TB_inline?max-height=420px&inlineId=sr_post_query_form" title="Send your query" class="thickbox" id="support_link">Feedback / Help?</a>';
	            
	            if (SR_BETA != "true") {
	            	$before_plug_page .= ' | <a href="admin.php?page=smart-reporter-';
	            	$after_plug_page = '&action=sr-settings">Settings</a>';
	            }
	            else {
	            	$after_plug_page = "";
	            	$plug_page = "";
	            }

	        }

			printf ( __ ( '%1s%2s%3s'), $before_plug_page, $plug_page, $after_plug_page);		
		?>
		</span>
		<?php
			echo __ ( 'Store analysis like never before.' );
		?>
	</p>
	<h6 align="right"><?php
			if (isset($is_pro_updated) && ! $is_pro_updated) {
				$admin_url = ADMIN_URL . "plugins.php";
				$update_link = "An upgrade for Smart Reporter Pro  $latest_version is available. <a align='right' href=$admin_url> Click to upgrade. </a>";
				sr_display_notice ( $update_link );
			}
			?>
   </h6>
   <h6 align="right">
</h2>
</div>

<?php
if (SRPRO === false) {
				?>
<div id="message" class="updated fade">
<p><?php
printf ( __ ( "<b>Important:</b> To get the sales and sales KPI's for more than 30 days upgrade to Pro . Take a <a href='%2s' target=_livedemo> Live Demo here </a>." ), 'http://demo.storeapps.org/' );
				?></p>
</div>
<?php
}
			?>
<?php
			$error_message = '';
			if ((file_exists( WP_PLUGIN_DIR . '/wp-e-commerce/wp-shopping-cart.php' )) && (file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ))) {
			if (is_plugin_active( 'wp-e-commerce/wp-shopping-cart.php' )) {
                            require_once (WPSC_FILE_PATH . '/wp-shopping-cart.php');
                            if (IS_WPSC37 || IS_WPSC38) {
                                if (file_exists( $base_path . 'reporter-console.php' )) {
                                        include_once ($base_path . 'reporter-console.php');
                                        return;
                                } else {
                                        $error_message = __( "A required Smart Reporter file is missing. Can't continue.", 'smart-reporter' );
                                }
                            } else {
                                $error_message = __( 'Smart Reporter currently works only with WP e-Commerce 3.7 or above.', 'smart-reporter' );
                            }
			} else if (is_plugin_active( 'woocommerce/woocommerce.php' )) {
                            if (IS_WOO13) {
                                    $error_message = __( 'Smart Reporter currently works only with WooCommerce 1.4 or above.', 'smart-reporter' );
                            } else {
                                if (file_exists( $base_path . 'reporter-console.php' )) {
                                        include_once ($base_path . 'reporter-console.php');
                                        return;
                                } else {
                                        $error_message = __( "A required Smart Reporter file is missing. Can't continue.", 'smart-reporter' );
                                }
                            }
			}
                        else {
                            $error_message = "<b>" . __( 'Smart Reporter', 'smart-reporter' ) . "</b> " . __( 'add-on requires', 'smart-reporter' ) . " " .'<a href="http://www.storeapps.org/wpec/">' . __( 'WP e-Commerce', 'smart-reporter' ) . "</a>" . " " . __( 'plugin or', 'smart-reporter' ) . " " . '<a href="http://www.storeapps.org/woocommerce/">' . __( 'WooCommerce', 'smart-reporter' ) . "</a>" . " " . __( 'plugin. Please install and activate it.', 'smart-reporter' );
                        }
                    } else if (file_exists( WP_PLUGIN_DIR . '/wp-e-commerce/wp-shopping-cart.php' )) {
                        if (is_plugin_active( 'wp-e-commerce/wp-shopping-cart.php' )) {
                            require_once (WPSC_FILE_PATH . '/wp-shopping-cart.php');
                            if (IS_WPSC37 || IS_WPSC38) {
                                if (file_exists( $base_path . 'reporter-console.php' )) {
                                        include_once ($base_path . 'reporter-console.php');
                                        return;
                                } else {
                                        $error_message = __( "A required Smart Reporter file is missing. Can't continue.", 'smart-reporter' );
                                }
                            } else {
                                $error_message = __( 'Smart Reporter currently works only with WP e-Commerce 3.7 or above.', 'smart-reporter' );
                            }
                        } else {
                                $error_message = __( 'WP e-Commerce plugin is not activated.', 'smart-reporter' ) . "<br/><b>" . _e( 'Smart Reporter', 'smart-reporter' ) . "</b> " . _e( 'add-on requires WP e-Commerce plugin, please activate it.', 'smart-reporter' );
                        }
                    } else if (file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' )) {
                        if (is_plugin_active( 'woocommerce/woocommerce.php' )) {
                            if (IS_WOO13) {
                                    $error_message = __( 'Smart Reporter currently works only with WooCommerce 1.4 or above.', 'smart-reporter' );
                            } else {
                                if (file_exists( $base_path . 'reporter-console.php' )) {
                                    include_once ($base_path . 'reporter-console.php');
                                    return;
                                } else {
                                    $error_message = __( "A required Smart Reporter file is missing. Can't continue.", 'smart-reporter' );
                                }
                            }
                        } else {
                            $error_message = __( 'WooCommerce plugin is not activated.', 'smart-reporter' ) . "<br/><b>" . __( 'Smart Reporter', 'smart-reporter' ) . "</b> " . __( 'add-on requires WooCommerce plugin, please activate it.', 'smart-reporter' );
                        }
                    }
                    else {
                        $error_message = "<b>" . __( 'Smart Reporter', 'smart-reporter' ) . "</b> " . __( 'add-on requires', 'smart-reporter' ) . " " .'<a href="http://www.storeapps.org/wpec/">' . __( 'WP e-Commerce', 'smart-reporter' ) . "</a>" . " " . __( 'plugin or', 'smart-reporter' ) . " " . '<a href="http://www.storeapps.org/woocommerce/">' . __( 'WooCommerce', 'smart-reporter' ) . "</a>" . " " . __( 'plugin. Please install and activate it.', 'smart-reporter' );
                    }

			if ($error_message != '') {
				sr_display_err ( $error_message );
				?>
<?php
			}
		}
	};


	function sr_beta_show_console() {
		

		//Constants for the arrow indicators
	    define ('SR_IMG_UP_GREEN', 'icon-arrow-up icon_cumm_indicator_green');
	    define ('SR_IMG_UP_RED', 'icon-arrow-up icon_cumm_indicator_red');
	    define ('SR_IMG_DOWN_RED', 'icon-arrow-down icon_cumm_indicator_red');
	    
	    //Constant for DatePicker Icon    
	    define ('SR_IMG_DATE_PICKER', SR_IMG_URL . 'calendar-blue.gif');

	    define("SR_BETA","true");

	    //Enqueing the Scripts and StyleSheets

        wp_enqueue_script ( 'sr_jqplot_all_scripts' );
		wp_enqueue_style ( 'sr_main_beta' );

		sr_console_common();

		// Code for overriding the wooCommerce orders module search functionality code

		add_action('wp_ajax_get_monthly_sales','get_monthly_sales');

		function woocommerce_shop_order_search_custom_fields1( $wp ) {
	        global $pagenow, $wpdb;

	        if(!(isset($_GET['source']) && $_GET['source'] == 'sr'))
	        	return;
	        
	        remove_filter( 'parse_query', 'woocommerce_shop_order_search_custom_fields' );

	        $post_ids = (isset($_COOKIE['post_ids'])) ? explode(",",$_COOKIE['post_ids']) : 0;

	        // Remove s - we don't want to search order name
	        unset( $wp->query_vars['s'] );

	        // Remove the post_ids from $_COOKIE
	        unset($_COOKIE['post_ids']);

	        // so we know we're doing this
	        $wp->query_vars['shop_order_search'] = true;

	        // Search by found posts
	        $wp->query_vars['post__in'] = $post_ids;

	        add_filter( 'parse_query', 'woocommerce_shop_order_search_custom_fields' );

	    }
		add_filter( 'parse_query', 'woocommerce_shop_order_search_custom_fields1',5 );


	};

	function sr_show_console() {

		//Enqueing the Scripts and StyleSheets
		wp_enqueue_script ( 'sr_main' );
		wp_enqueue_style ( 'sr_main' );

		sr_console_common();
	}
	
	function sr_update_notice() {
		if ( !function_exists( 'sr_get_download_url_from_db' ) ) return;
                $download_details = sr_get_download_url_from_db();
//                $plugins = get_site_transient ( 'update_plugins' );
		$link = $download_details['results'][0]->option_value;                                //$plugins->response [SR_PLUGIN_FILE]->package;
		
                if ( !empty( $link ) ) {
                    $current  = get_site_transient ( 'update_plugins' );
                    $r1       = sr_plugin_reset_upgrade_link ( $current, $link );
                    set_site_transient ( 'update_plugins', $r1 );
                    echo $man_download_link = " Or <a href='$link'>click here to download the latest version.</a>";
                }
	}
		
	if (! function_exists ( 'sr_display_err' )) {
		function sr_display_err($error_message) {
			echo "<div id='notice' class='error'>";
			echo _e ( '<b>Error: </b>' . $error_message );
			echo "</div>";
		}
	}
	
	if (! function_exists ('sr_display_notice')) {
		function sr_display_notice($notice) {
			echo "<div id='message' class='updated fade'>
             <p>";
			echo _e ( $notice );
			echo "</p></div>";
		}
	}
// EOF auto upgrade code
}
?>
