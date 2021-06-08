<?php
/**
* Plugin Name: Checkout POS for WooCommerce
* Plugin URI: https://github.com/CheckoutFinland/checkout-pos-for-woocommerce 
* Description: Connect your Checkout POS (formerly OP Kassa) and WooCommerce to synchronize products, orders and stock levels between the systems.
* Version: 3.0.0
* Requires at least: 4.9
* Tested up to: 5.7
* Requires PHP: 7.1
* WC requires at least: 3.0
* WC tested up to: 5.3
* Author: Checkout Finland
* Author URI: https://www.checkout.fi/pos 
* Text Domain: op-kassa-for-woocommerce
* Domain Path: /languages
* License: MIT
* License URI: https://opensource.org/licenses/MIT
* Copyright: OP Merchant Services
*/

namespace CheckoutFinland\WooCommerceKIS;

use CheckoutFinland\WooCommerceKIS\Activation;
use CheckoutFinland\WooCommerceKIS\Admin\Notice;
use CheckoutFinland\WooCommerceKIS\Admin\ProductEAN;
use CheckoutFinland\WooCommerceKIS\Admin\PackageSlip;
use CheckoutFinland\WooCommerceKIS\Admin\SettingsPage;
use CheckoutFinland\WooCommerceKIS\Admin\OrderInfoMetaBox;
use CheckoutFinland\WooCommerceKIS\Admin\DeletedProductTracker;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once( __DIR__ . '/vendor/autoload.php' );
}

/**
 * Initializes plugin functionalities.
 *
 * @since      1.0.0
 * @package    CheckoutFinland\WooCommerceKIS
 */
final class Plugin {

    /**
     * Holds the plugin singleton.
     *
     * @since    0.0.0
     * @access   private
     * @var Plugin
     */
    private static $instance;

    /**
     * The notice instance handles displaying all admin notices.
     *
     * @since    0.0.0
     * @access   private
     * @var      Notice    $notice    Handles all admin notices.
     */
    protected $notice;

    /**
     * The WooCommerce REST API modifications class.
     *
     * @var Api
     */
    protected $api;

    /**
     * Order info box renderer for orders
     *
     * @since 0.4.0
     * @var OrderInfoMetaBox
     */
    protected $order_box;

    /**
     * Handler for product EAN related stuff
     *
     * @since 0.5.0
     * @var ProductEAN
     */
    protected $product_ean;

    /**
     * Package slip metabox render class
     *
     * @since 0.5.0
     * @var PackageSlip
     */
    protected $package_slip;

    /**
     * Tracker for deleted and trashed posts
     *
     * @since 0.4.0
     * @var DeletedProductTracker
     */
    protected $deleted_product_tracker;

    /**
     * Domain used to connect to OP Kassa
     *
     * @since 1.0.5
     * @var string
     */
    protected $kassa_connected_site_domain;

    /**
     * Define all WooCommerce post types here
     * since WC does not bother to define them.
     */
    const WC_POST_TYPES = [
        'product',
        'product_variation',
        'product_visibility',
        'shop_order',
        'shop_coupon',
        'shop_webhook',
    ];

    /**
     * Private constructor for the plugin singleton.
     */
    public function init() {
        $this->set_constants();

        $this->notice                  = new Notice();
        $this->api                     = new Api();
        $this->order_box               = new OrderInfoMetaBox();
        $this->deleted_product_tracker = new DeletedProductTracker();
        $this->product_ean             = new ProductEAN();
        $this->package_slip            = new PackageSlip();

        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_global_hooks();
    }

    /**
     * Force disconnect from Kassa if site domain has changed
     * 
     * @since    1.0.5
     * @access   private
     */
    function check_siteurl_changes() {
        $site_url = parse_url( get_option( 'siteurl' ) );
        $kassa_connected_site_url = get_option( 'kassa_connected_site_url' );

        if ( !$kassa_connected_site_url ) {
            return;
        }

        $kassa_connected_site_url = parse_url( $kassa_connected_site_url );
        if( $kassa_connected_site_url['host'] && $kassa_connected_site_url['host'] !== $site_url['host'] ) {
            $this->kassa_connected_site_domain = $kassa_connected_site_url['host'];

            // Disconnect from Kassa
            $this->disconnect_kassa();
            delete_option( 'kassa_connected_site_url', null );

            // Add notification about the disconnect and about the possibility of double products/orders in Kassa after reconnect
            add_action( 'admin_notices', function () {
                $site_url = parse_url(get_option('siteurl'));
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <div><?php echo esc_html( 'The site domain has changed: OP Kassa has been disconnected.', 'woocommerce-kis' ); ?></div>
                        <div><?php echo esc_html( 'Old domain: ', 'woocommerce-kis' ) . $this->kassa_connected_site_domain; ?></div>
                        <div><?php echo esc_html( 'New domain: ', 'woocommerce-kis' ) . $site_url['host']; ?></div>
                        <div><?php echo esc_html( 'Please make sure the new domain is correct before re-connecting to OP Kassa.', 'woocommerce-kis' ); ?></div>
                        <div><?php echo esc_html( 'NOTE! If You connect to OP Kassa with same credentials than earlier, the products may be duplicated during the product synchronization. You may want to delete the products from OP Kassa prior new connection.', 'woocommerce-kis' ); ?></div>
                    </p>
                </div>
                <?php
            });
        }
    }

    /**
     * Define plugin constants.
     */
    private function set_constants() {
        $plugin_headers = [
            'Version' => 'Version',
        ];

        if ( function_exists( 'get_file_data' ) ) {
            $plugin_data = \get_file_data( __FILE__, $plugin_headers, 'plugin' );

            $kis_version = $plugin_data['Version'];
        }
        else {
            $kis_version = 'unknown';
        }

        define( 'WOOCOMMERCE_KIS_VERSION', $kis_version );

        if ( ! defined('KIS_HAS_CUSTOM_ENVIRONMENT')) {
            define( 'KIS_HAS_CUSTOM_ENVIRONMENT', $this->has_custom_environment_urls());
        }

        $kis_test_environment_enabled = get_option('kis_test_environment_enabled', null);
        // If kis_test_environment_enabled-option has not been set yet, use 'no' as default value
        if (is_null($kis_test_environment_enabled)) {
            $kis_test_environment_enabled = 'no';
        }

        if ( ! defined( 'KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED' ) ) {
            define( 'KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED', $kis_test_environment_enabled );
        }

        if ( ! defined( 'KIS_WOOCOMMERCE_OAUTH_URL' ) ) {
            $target_url = KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED === 'no' ? 
                'https://woocommerce.prod.op-kassa.fi/prod/woo-auth-initiate' : 
                'https://woocommerce.qa.op-kassa.fi/qa/woo-auth-initiate';
            define( 'KIS_WOOCOMMERCE_OAUTH_URL', $target_url );
        }

        if ( ! defined( 'KIS_KASSA_OAUTH_URL' ) ) {
            $target_url = KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED === 'no' ?
                'https://woocommerce.prod.op-kassa.fi/prod/kassa-oauth-initiate' :
                'https://woocommerce.qa.op-kassa.fi/qa/kassa-oauth-initiate';
            define( 'KIS_KASSA_OAUTH_URL', $target_url );
        }

        if ( ! defined( 'KIS_KASSA_DELETE_OAUTH_URL' ) ) {
            $target_url = KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED === 'no' ?
                'https://woocommerce.prod.op-kassa.fi/prod/kassa-oauth-delete' :
                'https://woocommerce.qa.op-kassa.fi/qa/kassa-oauth-delete';
            define( 'KIS_KASSA_DELETE_OAUTH_URL', $target_url );
        }

        if ( ! defined( 'KIS_WOOCOMMERCE_OAUTH_CALLBACK_URL' ) ) {
            $target_url = KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED === 'no' ?
                'https://woocommerce.prod.op-kassa.fi/prod/woo-auth-callback' :
                'https://woocommerce.qa.op-kassa.fi/qa/woo-auth-callback';
            define( 'KIS_WOOCOMMERCE_OAUTH_CALLBACK_URL', $target_url );
        }

        if ( ! defined( 'KIS_WOOCOMMERCE_WEBHOOK_URL' ) ) {
            $target_url = KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED === 'no' ?
                'https://woocommerce.prod.op-kassa.fi/prod/woo-webhook' :
                'https://woocommerce.qa.op-kassa.fi/qa/woo-webhook';
            define( 'KIS_WOOCOMMERCE_WEBHOOK_URL', $target_url );
        }

        if ( ! defined( 'KIS_WOOCOMMERCE_SYSTEM_AUDIT_CONFIG_URL' ) ) {
            $target_url = KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED === 'no' ?
                'https://woocommerce.prod.op-kassa.fi/prod/wooClientSystemAuditConfig.json' :
                'https://woocommerce.qa.op-kassa.fi/qa/wooClientSystemAuditConfig.json';
            define( 'KIS_WOOCOMMERCE_SYSTEM_AUDIT_CONFIG_URL', $target_url );
        }
    }

    /**
     * Returns true if some of the environmental urls are set
     * 
     * @return bool
     * @since    0.8.0
     * @access   private
     */
    private function has_custom_environment_urls() : bool {
        return (defined( 'KIS_WOOCOMMERCE_OAUTH_URL' ) ||
            defined( 'KIS_KASSA_OAUTH_URL' ) ||
            defined( 'KIS_WOOCOMMERCE_OAUTH_CALLBACK_URL' ) || 
            defined( 'KIS_WOOCOMMERCE_WEBHOOK_URL' ) ) ;
    }

    /**
     * Disconnects the plugin from Kassa: 
     * - if plugin is connected to Kassa but the target environment is not known
     * 
     * @since    0.8.0
     * @access   private
     */
    private function disconnect_from_kassa_if_no_environment() {
        $kis_test_environment_enabled = get_option('kis_test_environment_enabled', null);

        if (is_null($kis_test_environment_enabled)) {
            $this->disconnect_kassa();
            // Add the kis_test_environment_enabled-option with a default value of 'no'
            add_option('kis_test_environment_enabled', 'no');
        }
    }

    /**
     * If given new option value differs from the previous option value:
     * - Disconnect from Kassa
     * - Reload plugin to redefine new environment related constants
     * 
     * @since    0.8.0
     * @access   public
     */
    public function reload_plugin( $new_option_value, $old_option_value ) {
        if ( $new_option_value !== $old_option_value && ! empty( $new_option_value ) ) {
            $this->disconnect_kassa();

            // Reload page
            header("Refresh:0");
        }
    }

    /**
     * Force disconnect from Kassa if connection is active
     * 
     * @return bool disconnect was made
     * 
     * @since    0.8.0
     * @access   private
     */
    private function disconnect_kassa() : bool {
        $oauth = new OAuth( $this->notice );

        if ( $oauth->is_oauth_active() ) {
            $this->oauth_delete();
            $oauth->handle_oauth_delete(true);

            return true;
        }

        return false;
    }

    /**
     * Send 'Disconnect from Kassa'-request to the integration
     * 
     * @return bool disconnect was made
     * 
     * @since    0.8.1
     * @access   private
     */
    private function oauth_delete() : bool {
        $url = Utility::add_query_parameter( KIS_KASSA_DELETE_OAUTH_URL, 'domain', Utility::get_server_name() );

        $http_code = wp_remote_retrieve_response_code( wp_remote_get( $url ) );

        if ( $http_code >= 400) {
            error_log('Kassa Disconnect failed! Check OP Kassa integration logs for further information.');
        }

        return $http_code < 400;
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Plugin_Name_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    0.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new I18n();

        add_action( 'plugins_loaded', [ $plugin_i18n, 'load_plugin_textdomain' ] );
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    0.0.0
     * @access   private
     */
    private function define_admin_hooks() {

        // Hooks to handle plugin environment change
        add_filter( 'update_option_kis_test_environment_enabled', [$this, 'reload_plugin'], 10, 2 );

        // Hooks to handle plugin auth method change
        add_filter( 'update_option_kis_woo_auth_params_enabled', [$this, 'reload_plugin'], 10, 2 );


        // Register the settings page for WooCommerce.
        add_filter(
            'woocommerce_get_settings_pages', [ SettingsPage::class, 'include_settings_page' ], 1, 1
        );

        // Hook for displaying OAUTH errors.
        add_filter(
            'admin_notices', [ $this->notice, 'oauth_error' ]
        );

        // Create a new metabox for orders
        add_action('add_meta_boxes', [$this->order_box, 'orderbox_metabox']);

        // Hook to add order metadata to order
        add_action('post_updated', [$this->order_box, 'save_metabox_data'], 10, 3);

        // Create a new metabox for package slip
        add_action('add_meta_boxes', [$this->package_slip, 'render_metabox']);

        // To track if posts are deleted, trashed or untrashed
        add_action('delete_post', [$this->deleted_product_tracker, 'post_deleted_or_trashed'], 10, 1);
        add_action('wp_trash_post', [$this->deleted_product_tracker, 'post_deleted_or_trashed'], 10, 1);
        add_action('untrash_post', [$this->deleted_product_tracker, 'post_untrashed'], 10, 1);

        // WooCommerce refund hook to update the order
        add_action('woocommerce_order_refunded', [$this, 'refresh_order_on_refund'], 10, 2 );

        // WooCommerce products EAN hooks
        add_action('woocommerce_product_options_sku', [$this->product_ean, 'add_ean_input_to_product']);
        add_action('woocommerce_process_product_meta', [$this->product_ean, 'save_product_ean']);

        // WooCommerce product variation EAN hooks
        add_action('woocommerce_variation_options', [$this->product_ean, 'add_ean_input_to_variation'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this->product_ean, 'save_variation_ean'], 10, 2);
    }

    /**
     * Register all hooks run globally.
     *
     * @since    0.0.0
     * @access   private
     */
    private function define_global_hooks() {
        $notice = $this->notice;

        // Hook to the OAuth server plugin's token data hook.
        $oauth = new OAuth( $notice );
        add_filter(
            'json_oauth1_access_token_data', [ $oauth, 'handle_json_access_token_data' ], 1, 1
        );

        add_action( 'init', [ $this, 'check_siteurl_changes' ] );
        add_action( 'admin_init', [ $oauth, 'handle_kassa_oauth_response' ] );

        add_action( 'wp_loaded', function() {
            $this->disconnect_from_kassa_if_no_environment();
        });

        // Filter WooCommerce REST API query for all WC post types.
        $post_types = static::WC_POST_TYPES;
        array_walk(
            $post_types, function( $post_type ) {
                add_filter(
                    "woocommerce_rest_{$post_type}_object_query",
                    [ $this->api, 'filter_wc_rest_query' ],
                    PHP_INT_MAX,
                    2
                );
            }
        );

        // Add a custom route for the deleted products
        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'wc/v3',
                    'deleted_products',
                    [
                        'methods'  => 'GET',
                        'callback' => [$this->deleted_product_tracker, 'get_deleted_products_callback'],
                        'permission_callback' => function() {
                            return \current_user_can('manage_woocommerce');
                        }
                    ]
                );
                register_rest_route(
                    'wc/v3',
                    'delete_auth_token',
                    [
                        'methods'  => 'GET',
                        'callback' => [$this->api, 'delete_auth_token'],
                        'permission_callback' => function() {
                            return \current_user_can('manage_woocommerce');
                        }
                    ]
                );
            }
        );

        // Prevent putting orders to 'completed'-status directly from 'pending'-status if they are Kassa-orders created via API.
        // Auto completing of orders happens when no products on the order can be found from Woo.
        add_action( 'woocommerce_order_status_changed', function ( $order_id, $old_status, $new_status, $instance ) {
            $order = wc_get_order($order_id);
            if ( $order && $order->get_created_via() === 'rest-api' && $old_status === 'pending' && $new_status === 'completed' ) {
                // Can we trust this meta attribute is always on the order?
                $is_kassa_purchase = get_post_meta( $order_id, '_kassa_purchase', true );
                if ( $is_kassa_purchase ) {
                    $order->update_status( 'processing' );
                }
            }
        }, 10, 4 );

        // Prevent sending order emails for orders which are created via API
        add_action('woocommerce_new_order', function ($order_id) {         
             $order = wc_get_order($order_id);
         
             if ($order && $order->get_created_via() === 'rest-api') {
               add_filter( 'woocommerce_email_enabled_new_order', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_cancelled_order', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_failed_order', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_customer_on_hold_order', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_customer_processing_order', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_customer_completed_order', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_customer_refunded_order', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_customer_invoice', function($yesno, $object){
                 return false;
               }, 10, 2);
               add_filter( 'woocommerce_email_enabled_customer_note', function($yesno, $object){
                 return false;
               }, 10, 2);
             }
         }, 10, 1);

         /**
         * Register the plugin deactivation hook.
         */
        register_deactivation_hook( __FILE__, array ( $this, 'plugin_deactivate' ));
    }

    /**
     * Check if the 'WP REST API - OAuth 1.0a Server' plugin is enabled.
     *
     * @see https://wordpress.org/plugins/rest-api-oauth1/
     *
     * @return bool
     */
    private function oauth_enabled() {
        return class_exists( 'WP_REST_OAuth1' );
    }

    /**
     * Get the plugin assets url.
     *
     * @return string
     */
    public function get_assets_url() : string {
        return plugin_dir_url( __FILE__ ) . 'assets/';
    }

    /**
     * Get the plugin assets path.
     *
     * @return string
     */
    public function get_assets_path() : string {
        return plugin_dir_path( __FILE__ ) . 'assets';
    }

    /**
     * Initializes the plugin once and returns the instance.
     */
    public static function instance() {
        if ( empty( static::$instance ) ) {
            static::$instance = new Plugin();

            return static::$instance;
        }

        return static::$instance;
    }

    /**
     * Updates order post modified timestamp when order is refunded
     *
     * @param int $order_id
     * @param int $refund_id
     * @return void
     */
    public function refresh_order_on_refund($order_id, $refund_id) {
        $order = get_post($order_id);
        $err = null;
        wp_update_post($order, $err);
    }

    /**
     * Run when the plugin is deactivated.
     * 
     * @since    0.8.0
     * @access   public
     */
    public function plugin_deactivate() {
        // Disconnect from Kassa on plugin deactivation.
        $this->disconnect_kassa();
    }
}

/**
 * A global method for getting the plugin singleton.
 *
 * @package CheckoutFinland\WooCommerceKIS
 * @since   0.0.0
 * @return  Plugin
 */
function plugin() {
    return Plugin::instance();
}

// Begin plugin excecution by creating the singleton.
plugin()->init();

/**
 * Register an Activation hook for handling the plugin system audit
 */
include_once dirname( __FILE__ ) . '/src/Admin/SystemAudit.php';
register_activation_hook( __FILE__, array( 'CheckoutFinland\WooCommerceKIS\Admin\SystemAudit', 'perform_system_audit' ) );
add_action( 'admin_notices', array( 'CheckoutFinland\WooCommerceKIS\Admin\SystemAudit', 'display_system_audit_admin_notice' ) );
