<?php
/**
 * Plugin Name: Block IP Address for WooCommerce
 * Plugin URI: https://wpcraft.net/
 * Description: Block unwanted IPs from accessing your WooCommerce shop,home and specific category redirect them to another page and control access with start and end dates. 
 * Version: 1.0.3
 * Tested up to: 6.9.4
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
        add_action( 'admin_menu', array( $this, 'blocipadwoo_admin_main_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'blocipadwoo_enqueue_scripts' ) );
        add_action( 'rest_api_init', array( $this, 'blocipadwoo_rest_routes' ) );
        add_action( 'template_redirect', array( $this, 'blocipadwoo_from_shop' ) );
        register_activation_hook( __FILE__, array( $this, 'blocipadwoo_db_activate' ) );
    }

    /* ========== Enqueue Scripts ================ */

    public function blocipadwoo_enqueue_scripts() {
        $screen = get_current_screen();
        
        if ( $screen && $screen->id === 'toplevel_page_block-ip-address-for-woocommerce' ) { 
            $asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php' );
            
            wp_enqueue_style( 'blocipad-custom-css', plugins_url( 'assets/style.css', __FILE__ ), array(), time() );
            wp_enqueue_script( 'blocipad-build-js', plugins_url( 'build/index.js', __FILE__ ), $asset_file['dependencies'], time(), true );

            wp_localize_script( 'blocipad-build-js', 'blocipadwoo', array(
                'apiUrl' => home_url( '/wp-json' ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
            ) );
        } 
    }

    /* ========== Database Activation ================ */

    public function blocipadwoo_db_activate() {        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ipaddress varchar(100) NOT NULL,
            blocktype varchar(100) NOT NULL DEFAULT '',
            blkcategory varchar(100) NOT NULL DEFAULT '',
            startdate date NOT NULL,
            enddate date NOT NULL,
            redirect varchar(255) NOT NULL DEFAULT '',
            log_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Add missing columns for existing installations
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table_name" );
        if ( ! in_array( 'blocktype', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN blocktype varchar(100) NOT NULL DEFAULT '' AFTER ipaddress" );
        }
        if ( ! in_array( 'blkcategory', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN blkcategory varchar(100) NOT NULL DEFAULT '' AFTER blocktype" );
        }
    }

    /* ========== REST API Routes ================ */

    public function blocipadwoo_rest_routes() {
        register_rest_route( 'wooip/v1', '/new_ip', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'blocipadwoo_addnew_list' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( 'wprk/v1', '/get_blkip', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'blocipadwoo_view_list' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( 'wprk/v1', '/delete_blkip', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'blocipadwoo_delete_list' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( 'wprk/v1', '/update_blkip', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'blocipadwoo_update_list' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( 'wooip/v1', '/product_categories', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'blocipadwoo_get_product_categories' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );

        // Debug endpoint to check detected IP
        register_rest_route( 'wooip/v1', '/debug_ip', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'blocipadwoo_debug_ip' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    /* ========== Debug IP Detection ================ */

    public function blocipadwoo_debug_ip() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        $detected_ip = $this->blocipadwoo_get_user_ip();
        
        // Get all blocked IPs for comparison
        $all_blocked_ips = $wpdb->get_results( "SELECT DISTINCT ipaddress FROM $table_name ORDER BY ipaddress" );
        $blocked_list = array_map( function( $row ) { return $row->ipaddress; }, $all_blocked_ips );
        
        // Check if current IP matches any blocked IP
        $is_blocked = in_array( $detected_ip, $blocked_list, true );
        
        return rest_ensure_response( array(
            'detected_ip'          => $detected_ip,
            'is_blocked'           => $is_blocked,
            'blocked_ips'          => $blocked_list,
            'current_page_context' => array(
                'is_front_page' => is_front_page(),
                'is_shop'       => is_shop(),
                'is_product_category' => is_product_category(),
                'is_singular_product' => is_singular( 'product' ),
            ),
            'server_vars'          => array(
                'REMOTE_ADDR'          => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'Not set',
                'HTTP_CLIENT_IP'       => isset( $_SERVER['HTTP_CLIENT_IP'] ) ? $_SERVER['HTTP_CLIENT_IP'] : 'Not set',
                'HTTP_X_FORWARDED_FOR' => isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : 'Not set',
                'HTTP_CF_CONNECTING_IP' => isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : 'Not set',
            ),
        ) );
    }

    /* ========== Get WooCommerce Product Categories ================ */

    public function blocipadwoo_get_product_categories() {
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $categories ) ) {
            return rest_ensure_response( array() );
        }

        $result = array();
        foreach ( $categories as $cat ) {
            $result[] = array(
                'id'   => $cat->term_id,
                'name' => html_entity_decode( $cat->name ),
                'slug' => $cat->slug,
            );
        }

        return rest_ensure_response( $result );
    }

    /* ========== Add New IP ================ */

    public function blocipadwoo_addnew_list( $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        
        $ipaddress   = sanitize_text_field( $request->get_param( 'ipaddress' ) );
        $blocktype   = sanitize_text_field( $request->get_param( 'blocktype' ) );
        $blkcategory = sanitize_text_field( $request->get_param( 'blkcategory' ) );
        $redirect    = esc_url_raw( $request->get_param( 'redirect' ) );
        $startdate   = sanitize_text_field( $request->get_param( 'startdate' ) );
        $enddate     = sanitize_text_field( $request->get_param( 'enddate' ) );
        
        if ( ! filter_var( $ipaddress, FILTER_VALIDATE_IP ) ) {
            return rest_ensure_response( array( 'status' => 'error', 'message' => 'Invalid IP address format.' ) );
        }

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE ipaddress = %s AND blocktype = %s AND blkcategory = %s",
            $ipaddress, $blocktype, $blkcategory
        ) );

        if ( $existing > 0 ) {
            return rest_ensure_response( array( 'status' => 'error', 'message' => 'This IP address is already blocked for this block type and category.' ) );
        }

        if ( ! empty( $redirect ) && ! filter_var( $redirect, FILTER_VALIDATE_URL ) ) {
            return rest_ensure_response( array( 'status' => 'error', 'message' => 'Invalid redirect URL.' ) );
        }

        if ( ! strtotime( $startdate ) || ! strtotime( $enddate ) ) {
            return rest_ensure_response( array( 'status' => 'error', 'message' => 'Invalid start or end date.' ) );
        }

        if ( strtotime( $startdate ) >= strtotime( $enddate ) ) {
            return rest_ensure_response( array( 'status' => 'error', 'message' => 'Start date must be earlier than end date.' ) );
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'ipaddress'   => $ipaddress,
                'blocktype'   => $blocktype,
                'blkcategory' => $blkcategory,
                'redirect'    => $redirect,
                'startdate'   => $startdate,
                'enddate'     => $enddate,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result === false ) {
            return rest_ensure_response( array( 'status' => 'error', 'message' => 'Database insertion failed: ' . $wpdb->last_error ) );
        }

        return rest_ensure_response( array( 'status' => 'success', 'message' => 'Blocked IP was successfully added.' ) );
    }

    /* ========== View Block IP List ================ */

    public function blocipadwoo_view_list() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        $results    = $wpdb->get_results( "SELECT * FROM $table_name" );

        $response = rest_ensure_response( $results );
        $response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'Expires', '0' );
        return $response;
    }

    /* ========== Delete Block IP ================ */

    public function blocipadwoo_delete_list( WP_REST_Request $request ) {
        global $wpdb;
        $id = intval( $request->get_param( 'id' ) );
        
        if ( ! $id ) {
            return new WP_REST_Response( array( 'error' => 'Invalid ID' ), 400 );
        }

        $deleted = $wpdb->delete( $wpdb->prefix . 'wooip_blockip_list', array( 'id' => $id ) );

        if ( $deleted ) {
            return new WP_REST_Response( array( 'message' => 'Deleted successfully' ), 200 );
        } else {
            return new WP_REST_Response( array( 'error' => 'Failed to delete' ), 500 );
        }
    }

    /* ========== Update Block IP ================ */

    public function blocipadwoo_update_list( WP_REST_Request $request ) {
        global $wpdb;
        
        $id          = intval( $request->get_param( 'id' ) );
        $ip_address  = sanitize_text_field( $request->get_param( 'ipaddress' ) );
        $blocktype   = sanitize_text_field( $request->get_param( 'blocktype' ) );
        $blkcategory = sanitize_text_field( $request->get_param( 'blkcategory' ) );
        $redirect    = sanitize_text_field( $request->get_param( 'redirect' ) );
        $startdate   = sanitize_text_field( $request->get_param( 'startdate' ) );
        $enddate     = sanitize_text_field( $request->get_param( 'enddate' ) );

        if ( ! filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
            return rest_ensure_response( array( 'status' => 'error', 'message' => 'Invalid IP address format.' ) );
        }

        if ( ! empty( $redirect ) && ! filter_var( $redirect, FILTER_VALIDATE_URL ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid redirect URL' ), 400 );
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'wooip_blockip_list',
            array(
                'ipaddress'   => $ip_address,
                'blocktype'   => $blocktype,
                'blkcategory' => $blkcategory,
                'redirect'    => $redirect,
                'startdate'   => $startdate,
                'enddate'     => $enddate,
            ),
            array( 'id' => $id )
        );

        if ( $updated !== false ) {
            return new WP_REST_Response( array( 'message' => 'Updated successfully' ), 200 );
        } else {
            return new WP_REST_Response( array( 'error' => 'Update failed or no changes made' ), 500 );
        }
    }

    /* ========== Admin Menu ================ */

    public function blocipadwoo_admin_main_menu() {
        add_menu_page(
            __( 'Block IP Woo', 'block-ip-for-woocommerce' ),
            __( 'Block IP Woo', 'block-ip-for-woocommerce' ),
            'manage_options',
            'block-ip-address-for-woocommerce',
            array( $this, 'blocipadwoo_admin_page' ),
            'dashicons-buddicons-replies',
            53
        );
    }

    /* ========== Admin Page ================ */

    public function blocipadwoo_admin_page() {
        echo '<div class="wrap">
                <div id="ip-admin"></div>
                <div id="new-ipaddress"></div>
                <div id="view-iplist"></div>
              </div>';
    }

    /* ========== Get User IP ================ */

    public function blocipadwoo_get_user_ip() {

        /* $visitor_ip = $_SERVER['REMOTE_ADDR'];
        echo "Visitor IP: " . $visitor_ip;
        die(); */

        $ip = '';

        // 1. Cloudflare real IP
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
        }
        // 2. Standard proxy/load-balancer forwarded IP
        elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_list = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip = trim( $ip_list[0] );
        }
        // 3. Client IP header
        elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        }
        // 4. Direct connection fallback
        elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Validate IP - return valid IP or fallback
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return '0.0.0.0';
    }

    /* ========== Fetch Block Record for Current Page ================ */
    
    private function blocipadwoo_get_block_record() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wooip_blockip_list';
        $user_ip    = $this->blocipadwoo_get_user_ip();
        
        /* print_r($user_ip);
        exit; */

        // Home page
        if ( is_front_page() && ! is_shop() ) {
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE ipaddress = %s
                   AND blocktype = 'home'
                   AND startdate <= CURDATE()
                   AND enddate   >= CURDATE()
                 LIMIT 1",
                $user_ip
            ) );
        }

        // Shop page
        if ( is_shop() ) {
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE ipaddress = %s
                   AND blocktype = 'shop'
                   AND startdate <= CURDATE()
                   AND enddate   >= CURDATE()
                 LIMIT 1",
                $user_ip
            ) );
        }

        // Product category archive
        if ( is_product_category() ) {
            $category = get_queried_object();
            $cat_name = $category ? html_entity_decode( $category->name ) : '';
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE ipaddress = %s
                   AND blocktype = 'category'
                   AND LOWER(blkcategory) = LOWER(%s)
                   AND startdate <= CURDATE()
                   AND enddate   >= CURDATE()
                 LIMIT 1",
                $user_ip, $cat_name
            ) );
        }

        // Single product page — check all categories the product belongs to
        if ( is_singular( 'product' ) ) {
            $product_id   = get_the_ID();
            $product_cats = wp_get_post_terms( $product_id, 'product_cat' );
            if ( ! is_wp_error( $product_cats ) && ! empty( $product_cats ) ) {
                foreach ( $product_cats as $pcat ) {
                    $pcat_name = html_entity_decode( $pcat->name );
                    $row = $wpdb->get_row( $wpdb->prepare(
                        "SELECT * FROM $table_name
                         WHERE ipaddress = %s
                           AND blocktype = 'category'
                           AND LOWER(blkcategory) = LOWER(%s)
                           AND startdate <= CURDATE()
                           AND enddate   >= CURDATE()
                         LIMIT 1",
                        $user_ip, $pcat_name
                    ) );
                    if ( $row ) {
                        return $row;
                    }
                }
            }
        }

        return null;
    }

    /* ========== Block / Redirect Handler ================ */

    public function blocipadwoo_from_shop() {
        // Not a page type we block — bail early
        if ( ! is_front_page() && ! is_shop() && ! is_product_category() && ! is_singular( 'product' ) ) {
            return;
        }

        $record = $this->blocipadwoo_get_block_record();

        if ( ! $record ) {
            return; // IP is not blocked — do nothing
        }

        // Log the block event (optional - for debugging)
        $user_ip = $this->blocipadwoo_get_user_ip();
        error_log( 'IP BLOCKED: ' . $user_ip . ' on page: ' . $_SERVER['REQUEST_URI'] );

        $redirect_url = ! empty( $record->redirect ) ? $record->redirect : '';

        // Guard: never redirect to the current URL (would cause a loop)
        if ( ! empty( $_SERVER['HTTP_HOST'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $current_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            if ( ! empty( $redirect_url ) && rtrim( $redirect_url, '/' ) !== rtrim( $current_url, '/' ) ) {
                wp_redirect( $redirect_url, 302 );
                exit;
            }
        }

        // No valid redirect — block with a 403
        wp_die(
            esc_html__( 'Access Denied: Your IP address has been blocked from this page.', 'block-ip-for-woocommerce' ),
            esc_html__( 'Access Denied', 'block-ip-for-woocommerce' ),
            array( 'response' => 403 )
        );
    }
}

new blocipadwoo_store();