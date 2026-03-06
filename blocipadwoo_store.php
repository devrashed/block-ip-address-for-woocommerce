<?php
/**
 * Plugin Name: Block IP Address for WooCommerce
 * Plugin URI: https://wpcraft.net/
 * Description: Block unwanted IPs from accessing your WooCommerce shop,home and specific category redirect them to another page and control access with start and end dates. 
 * Version: 1.0.2
 * Tested up to: 6.9.1
 * Author: wpcraft
 * Requires Plugins: woocommerce
 * Author URI: https://github.com/devrashed
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: block-ip-for-woocommerce
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
final class blocipadwoo_store {

    public function __construct() {  
        add_action('admin_menu', array($this, 'blocipadwoo_admin_main_menu') );
        add_action('admin_enqueue_scripts', [$this, 'blocipadwoo_enqueue_scripts']);
        add_action('rest_api_init', [$this, 'blocipadwoo_rest_routes']);
        add_action('template_redirect', [$this, 'blocipadwoo_from_shop']); // Block access to shop
        register_activation_hook(__FILE__, [$this, 'blocipadwoo_db_activate']);         
    }   

    public function blocipadwoo_enqueue_scripts() {
        
            $screen = get_current_screen();
        
        if ($screen->id === 'toplevel_page_block-ip-address-for-woocommerce') { 
            $asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php' );
            
            wp_enqueue_style( 'blocipad-custom-css', plugins_url('assets/style.css', __FILE__), [], time());
        
            wp_enqueue_script('blocipad-build-js', plugins_url( 'build/index.js', __FILE__ ), $asset_file['dependencies'], time(), true );

            wp_localize_script( 'blocipad-build-js', 'blocipadwoo', [
                'apiUrl' => home_url( '/wp-json' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ]);
        } 
    }

    public function blocipadwoo_db_activate() {        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ipaddress varchar(100) NOT NULL,
            blocktype varchar(100) NOT NULL,
            blkcategory varchar(100) NOT NULL,
            startdate date NOT NULL,
            enddate date NOT NULL,
            redirect varchar(100) NOT NULL,
            log_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    
    /* ========== Rest API ================*/

    public function blocipadwoo_rest_routes() {
        register_rest_route( 'wooip/v1', '/new_ip', [
            'methods' => 'POST',
            'callback' => [$this, 'blocipadwoo_addnew_list'],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            }
        ]);

        register_rest_route( 'wprk/v1', '/get_blkip', [
            'methods' => 'GET',
            'callback' => [$this, 'blocipadwoo_view_list'],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            }
           
        ]);

        register_rest_route('wprk/v1', '/delete_blkip', [
            'methods'  => 'POST',
            'callback' => [$this, 'blocipadwoo_delete_list'],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            }
        ]);

        register_rest_route('wprk/v1', '/update_blkip', [
            'methods'  => 'POST',
            'callback' => [$this,'blocipadwoo_update_list'],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            }
        ]);
    
    }

 
    /* ========== Add New Rest API ================*/

    public function blocipadwoo_addnew_list($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        
        // Sanitize inputs
        $ipaddress  = sanitize_text_field($request->get_param('ipaddress'));
        $blocktype  = sanitize_text_field($request->get_param('blocktype'));
        $blkcategory = sanitize_text_field($request->get_param('blkcategory'));
        $redirect   = esc_url_raw($request->get_param('redirect'));
        $startdate  = sanitize_text_field($request->get_param('startdate'));
        $enddate    = sanitize_text_field($request->get_param('enddate'));
        
        // Validate IP address (IPv4 or IPv6)
        if (!filter_var($ipaddress, FILTER_VALIDATE_IP)) {
            return rest_ensure_response([
                'status' => 'error',
                'message' => 'Invalid IP address format.'
            ]);
        }
    
        // Check for duplicate IP for same blocktype and category
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE ipaddress = %s AND blocktype = %s AND blkcategory = %s",
                $ipaddress, $blocktype, $blkcategory
            )
        );
        if ($existing > 0) {
            return rest_ensure_response([
                'status' => 'error',
                'message' => 'This IP address is already blocked for this block type and category.'
            ]);
        }

        // Validate Redirect URL
        if (!empty($redirect) && !filter_var($redirect, FILTER_VALIDATE_URL)) {
            return rest_ensure_response([
                'status' => 'error',
                'message' => 'Invalid redirect URL.'
            ]);
        }
    
        // Validate date format (optional strict check)
        if (!strtotime($startdate) || !strtotime($enddate)) {
            return rest_ensure_response([
                'status' => 'error',
                'message' => 'Invalid start or end date.'
            ]);
        }
    
        if (strtotime($startdate) >= strtotime($enddate)) {
            return rest_ensure_response([
                'status' => 'error',
                'message' => 'Start date must be earlier than end date.'
            ]);
        }
    
        // Insert into database
        $result = $wpdb->insert(
            $table_name,
            [
                'ipaddress' => $ipaddress,
                'blocktype' => $blocktype,
                'blkcategory' => $blkcategory,  
                'redirect'  => $redirect,
                'startdate' => $startdate,
                'enddate'   => $enddate,                
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return rest_ensure_response([
                'status' => 'error',
                'message' => 'Database insertion failed: ' . $wpdb->last_error
            ]);
        }
    
        return rest_ensure_response([
            'status' => 'success',
            'message' => 'Blocked IP was successfully added.'
        ]);
    }
        
    
    /* ========== View Block IP List================*/

        public function blocipadwoo_view_list() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wooip_blockip_list';
            $employees = $wpdb->get_results("SELECT * FROM $table_name");
        
            return rest_ensure_response($employees);
        }
        

        /* ========== Delete The Block IP ================*/

    public function blocipadwoo_delete_list(WP_REST_Request $request) {
        global $wpdb;
        $id = intval($request->get_param('id'));
        
        if (!$id) {
            return new WP_REST_Response(['error' => 'Invalid ID'], 400);
        }
    
        $deleted = $wpdb->delete($wpdb->prefix . "wooip_blockip_list", ["id" => $id]);
    
        if ($deleted) {
            return new WP_REST_Response(['message' => 'Deleted successfully'], 200);
        } else {
            return new WP_REST_Response(['error' => 'Failed to delete'], 500);
        }
    }


    /* ========== Update The Block IP ================*/

    public function blocipadwoo_update_list(WP_REST_Request $request) {
        global $wpdb;
        
        $id = intval($request->get_param('id'));
        $ip_address = sanitize_text_field($request->get_param('ipaddress'));
        $blocktype = sanitize_text_field($request->get_param('blocktype'));
        $blkcategory = sanitize_text_field($request->get_param('blkcategory'));
        $redirect = sanitize_text_field($request->get_param('redirect'));
        $startdate = sanitize_text_field($request->get_param('startdate'));
        $enddate = sanitize_text_field($request->get_param('enddate'));

         // Validate IP address (IPv4 or IPv6)
         if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
            return rest_ensure_response([
                'status' => 'error',
                'message' => 'Invalid IP address format.'
            ]);
        }    
        // redirect url 
        if (!empty($redirect) && !filter_var($redirect, FILTER_VALIDATE_URL)) {
            return new WP_REST_Response(['error' => 'Invalid redirect URL'], 400);
        }

        $updated = $wpdb->update(
            $wpdb->prefix . "wooip_blockip_list",
            [ // columns to update
                "ipaddress" => $ip_address,
                "blocktype" => $blocktype,
                "blkcategory" => $blkcategory,
                "redirect" => $redirect,
                "startdate" => $startdate,
                "enddate" => $enddate
            ],
            [ "id" => $id ] // WHERE condition
        );
    
        if ($updated !== false) {
            return new WP_REST_Response(['message' => 'Updated successfully'], 200);
        } else {
            return new WP_REST_Response(['error' => 'Update failed or no changes made'], 500);
        }
    }
    

    /* ========== Menu ================*/  

    public function blocipadwoo_admin_main_menu() {
        $capability = 'manage_options';
        $slug = 'block-ip-address-for-woocommerce';

        add_menu_page(
            __( 'Block IP Woo', 'block-ip-address-for-woocommerce' ),
            __( 'Block IP Woo', 'block-ip-address-for-woocommerce' ),
            $capability,
            $slug,
            array($this, 'blocipadwoo_admin_page'), 
            'dashicons-buddicons-replies',
            53
        );                
    }


    /* ========== React help to display ================*/   
    
    public function blocipadwoo_admin_page() {
        echo '<div class="wrap">
                <div id="ip-admin"></div>
                <div id="new-ipaddress"></div>
                <div id="view-iplist"></div>
              </div>';
    }
    


    /* ======== Retrieves the user's IP address ========*/


    public function blocipadwoo_get_user_ip() {
        $ip = '';

        if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) && ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip = trim( $ip_list[0] );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        // Validate the IP address
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return esc_html( $ip ); // Escape before output (if printing in HTML)
        }
        return '0.0.0.0';
    }

    
   /* ======= Checks if the user's IP is blocked. ======= */
 
    public function blocipadwoo_ip_blocked() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        $user_ip = $this->blocipadwoo_get_user_ip();

        // Home page block
        if (is_front_page()) {
            $blocked_ip = $wpdb->get_var($wpdb->prepare(
                "SELECT ipaddress FROM $table_name WHERE ipaddress = %s AND blocktype = 'home' AND startdate <= NOW() AND enddate >= NOW()",
                $user_ip
            ));
            if (!empty($blocked_ip)) return true;
        }

        // Shop page block
        if (is_shop()) {
            $blocked_ip = $wpdb->get_var($wpdb->prepare(
                "SELECT ipaddress FROM $table_name WHERE ipaddress = %s AND blocktype = 'shop' AND startdate <= NOW() AND enddate >= NOW()",
                $user_ip
            ));
            if (!empty($blocked_ip)) return true;
        }

        // Category-wise block (using category name)
        if (is_product_category()) {
            $category = get_queried_object();
            $cat_name = $category ? $category->name : '';
            $blocked_ip = $wpdb->get_var($wpdb->prepare(
                "SELECT ipaddress FROM $table_name WHERE ipaddress = %s AND blocktype = 'category' AND blkcategory = %s AND startdate <= NOW() AND enddate >= NOW()",
                $user_ip, $cat_name
            ));
            if (!empty($blocked_ip)) return true;
        }

        return false;
    }

    /* ======== Redirects the user to the blocked IP's redirect URL. ======== */
    
    public function blocipadwoo_from_shop() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        $user_ip = $this->blocipadwoo_get_user_ip();

        // Home page block
        if (is_front_page()) {
            $redirect_url = $wpdb->get_var($wpdb->prepare(
                "SELECT redirect FROM $table_name WHERE ipaddress = %s AND blocktype = 'home' AND startdate <= NOW() AND enddate >= NOW()",
                $user_ip
            ));
            if ($redirect_url) {
                wp_redirect($redirect_url);
                exit;
            } elseif ($this->blocipadwoo_ip_blocked()) {
                wp_die('Access Denied: Your IP has been blocked from this page.', 'Access Denied', ['response' => 403]);
            }
        }

        // Shop page block
        if (is_shop()) {
            $redirect_url = $wpdb->get_var($wpdb->prepare(
                "SELECT redirect FROM $table_name WHERE ipaddress = %s AND blocktype = 'shop' AND startdate <= NOW() AND enddate >= NOW()",
                $user_ip
            ));
            if ($redirect_url) {
                wp_redirect($redirect_url);
                exit;
            } elseif ($this->blocipadwoo_ip_blocked()) {
                wp_die('Access Denied: Your IP has been blocked from this page.', 'Access Denied', ['response' => 403]);
            }
        }

        // Category-wise block (using category name)
        if (is_product_category()) {
            $category = get_queried_object();
            $cat_name = $category ? $category->name : '';
            $redirect_url = $wpdb->get_var($wpdb->prepare(
                "SELECT redirect FROM $table_name WHERE ipaddress = %s AND blocktype = 'category' AND blkcategory = %s AND startdate <= NOW() AND enddate >= NOW()",
                $user_ip, $cat_name
            ));
            if ($redirect_url) {
                wp_redirect($redirect_url);
                exit;
            } elseif ($this->blocipadwoo_ip_blocked()) {
                wp_die('Access Denied: Your IP has been blocked from this page.', 'Access Denied', ['response' => 403]);
            }
        }
    }
}

new blocipadwoo_store();