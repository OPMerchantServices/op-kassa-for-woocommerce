<?php
/**
 * * This class creates the custom settings page into WooCommerce settings.
 */

namespace CheckoutFinland\WooCommerceKIS\Admin;

use CheckoutFinland\WooCommerceKIS\OAuth;
use CheckoutFinland\WooCommerceKIS\Plugin;
use CheckoutFinland\WooCommerceKIS\Utility;
use CheckoutFinland\WooCommerceKIS\Admin\SystemAudit;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * This class creates the custom settings page into WooCommerce settings.
 *
 * @since      0.0.0
 * @package    CheckoutFinland\WooCommerceKIS\Admin
 */
class SettingsPage extends \WC_Settings_Page {

    /**
     * The option name for the WooCommerce AUTH method option.
     */
    const WOO_AUTH_PARAMS = 'kis_woo_auth_params_enabled';

    /**
     * Holds the plugin instance.
     *
     * @var Plugin
     */
    private $plugin;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id     = 'kis';
        $this->label  = __( 'Checkout POS', 'woocommerce-kis' );
        $this->plugin = \CheckoutFinland\WooCommerceKIS\plugin();

        // Add actions here to enqueue scripts only on the correct page.
        \add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );

        add_action( 'woocommerce_admin_field_kis_system_audit', [ $this, 'system_audit' ] );

        add_action( 'woocommerce_admin_field_kis_oauth', [ $this, 'oauth_output' ] );

        parent::__construct();

    }

    /**
     * Enqueue scripts and stylesheets.
     *
     * @since    0.0.0
     */
    public function enqueues() {
        $assets_path = $this->plugin->get_assets_path();

        // Get file modification times to enable more dynamic versioning.
        $css_path    = $assets_path . '/css/wc-settings-kis.css';
        $css_version = file_exists( $css_path ) ?
            filemtime( $css_path ) : WOOCOMMERCE_KIS_VERSION;
        $js_path     = $assets_path . '/js/wc-settings-kis.js';
        $js_version  = file_exists( $js_path ) ?
            filemtime( $js_path ) : WOOCOMMERCE_KIS_VERSION;

        wp_enqueue_style(
            strtolower( __CLASS__ ),
            $this->plugin->get_assets_url() . 'css/wc-settings-kis.css',
            [],
            $css_version,
            'all'
        );

        wp_enqueue_script(
            strtolower( __CLASS__ ),
            $this->plugin->get_assets_url() . 'js/wc-settings-kis.js',
            [ 'jquery' ],
            $js_version,
            false
        );

    }

    /**
     * Get sections.
     *
     * @return array
     */
    public function get_sections() {
        $sections = array(
            '' => __( 'Integration options', 'woocommerce-kis' ),
        );
        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }

    /**
     * Get settings array.
     *
     * @return array
     */
    public function get_settings() : array {
        $description = sprintf(
            // Translators: The placeholder is the KIS version number.
            __(
                'version: %s',
                'woocommerce-kis'
            ),
            WOOCOMMERCE_KIS_VERSION
        );

        $settings_array = [
            [
                'title' => __( 'Checkout POS', 'woocommerce-kis' ),
                'desc'  => $description,
                'type'  => 'title',
                'id'    => 'kis_settings',
            ],

            [
                'type' => 'sectionend',
                'id'   => 'kis_environment_settings',
            ],

            [ 'type' => 'kis_oauth' ],

            [
                'type' => 'sectionend',
                'id'   => 'kis_oauth_settings',
            ],

            [
                'title' => __( 'Sync settings', 'woocommerce-kis' ),
                'type'  => 'title',
                'id'    => 'kis_sync_settings',
            ],

            'kis_product_sync_direction' => [
                'type'    => 'select',
                'title'   => __( 'Product export', 'woocommerce-kis' ),
                'default' => 'off',
                'id'      => 'kis_product_sync_direction',
                'options' => [
                    'woo_to_kassa' => __( 'Woo to Kassa', 'woocommerce-kis' ),
                    'kassa_to_woo' => __( 'Kassa to Woo', 'woocommerce-kis' ),
                    'off'          => __( 'Off', 'woocommerce-kis' ),
                ],
            ],

            'kis_order_sync_direction'   => [
                'type'    => 'select',
                'title'   => __( 'Order export', 'woocommerce-kis' ),
                'default' => 'off',
                'id'      => 'kis_order_sync_direction',
                'options' => [
                    'woo_to_kassa' => __( 'Woo to Kassa', 'woocommerce-kis' ),
                    'kassa_to_woo' => __( 'Kassa to Woo', 'woocommerce-kis' ),
                    'both'         => __( 'Both', 'woocommerce-kis' ),
                    'off'          => __( 'Off', 'woocommerce-kis' ),
                ],
            ],

            'kis_stock_sync_direction'   => [
                'type'    => 'select',
                'title'   => __( 'Stock export', 'woocommerce-kis' ),
                'default' => 'off',
                'id'      => 'kis_stock_sync_direction',
                'options' => [
                    'woo_to_kassa' => __( 'Woo to Kassa', 'woocommerce-kis' ),
                    'kassa_to_woo' => __( 'Kassa to Woo', 'woocommerce-kis' ),
                    'off'          => __( 'Off', 'woocommerce-kis' ),
                ],
            ],

            [
                'type' => 'sectionend',
                'id'   => 'kis_sync_settings',
            ],

            [
                'title' => __( 'Tax settings', 'woocommerce-kis' ),
                'type'  => 'title',
                'id'    => 'kis_tax_settings',
            ],

            'kis_woo_tax_calc_enabled' => [
                'type'    => 'checkbox',
                'title'   => __( 'Enable WooCommerce tax calculation', 'woocommerce-kis' ),
                'id'      => 'kis_woo_tax_calc_enabled',
            ],

            [
                'type' => 'sectionend',
                'id'   => 'kis_tax_settings',
            ],

            [
                'type' => 'title',
                'id' => 'kis_tax_title',
                'desc'  => __( '<div class="setting-warning">NOTE:<div>There may be rounding differences between Checkout POS and WooCommerce orders if this option is enabled.</div><div>Products and Fees on Checkout POS purchases which are not found in WooCommerce, use the default tax class VAT percentage. This may differ from Checkout POS.</div></div>', 'woocommerce-kis' ),


            ],

            [
                'type' => 'sectionend',
                'id'   => 'kis_settings',
            ],

            [
                'type' => 'sectionend',
                'id'   => 'kis_system_audit',
            ],

            'kis_test_environment_enabled' => [
                'type'    => 'select',
                'title'   => __( 'Operational environment', 'woocommerce-kis' ),
                'id'      => 'kis_test_environment_enabled',
                'options' => [
                    'no' => __( 'Checkout POS production environment', 'woocommerce-kis' ),
                    'yes' => __( 'Checkout POS test environment' ),
                ],
            ],

            [ 'type' => 'kis_system_audit' ],

            [
                'title' => __( 'Authentication settings', 'woocommerce-kis' ),
                'type'  => 'title',
                'id'    => 'kis_auth_settings',
            ],

            'kis_woo_auth_params_enabled' => [
                'type'    => 'checkbox',
                'desc'  => __( '<strong>Receive Checkout POS integration authentication data in URL params instead of request header.</strong> <div class="auth-url-class">Receive Checkout POS integration authentication data in URL params instead of request header. After changing the setting save the changes before you connect to Checkout POS.</div>', 'woocommerce-kis' ),
                'id'      => 'kis_woo_auth_params_enabled',
            ],

            [
                'type' => 'sectionend',
                'id'   => 'kis_auth_settings',
            ],
        ];

        return $settings_array;
    }

    /**
     * Output the settings.
     */
        public function output() {
        $settings = $this->get_settings();
        $this->the_merchant_details();
        $this->oauth_output();
        $this->sync_settings();
        $this->system_audit();
        
       
        }
    /**
     * Output the System audit section
     */
    public function system_audit() {
        $system_audit_notice = '';
        $sys_audit_url = $sys_audit_url = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );

        if ( strpos( filter_input( INPUT_SERVER, 'QUERY_STRING', FILTER_SANITIZE_URL ), 'sys_audit=true') !== false ) {
            $system_audit = new SystemAudit();
            $system_audit->perform_system_audit();
            $system_audit_notice = __('System audit report:', 'woocommerce-kis') . $system_audit->get_system_audit_notice();
        } else {
            $sys_audit_url = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL ) . '&sys_audit=true';
        }
        $auth_params_enabled = \WC_Admin_Settings::get_option('kis_woo_auth_params_enabled', '');
        $test_environment_enabled = \WC_Admin_Settings::get_option('kis_test_environment_enabled', '');

        ?>
            <div class="kis-audit-status-container">
                <div class="metabox-holder columns-1">
                    <div class="meta-box-sortables ui-sortable">
                        <div class="postbox">
                            <h2><span><?php esc_html_e( 'Developer settings', 'woocommerce-kis' ); // phpcs:ignore ?></span></h2>
                            <div class="system-audit-notices"><?php echo $system_audit_notice; ?></div>
                            <div class="kis_test_environment_enabled_area">
                                <tr valign="top">
                                    <th scope="row" class="titledesc">
                                        <label for="kis_test_environment_enabled"><?php echo __( 'Operational environment', 'woocommerce-kis' ) ?></label>
                                    </th>
                                    <td class="forminp">
                                        <select
                                            name="kis_test_environment_enabled"
                                            id="kis_test_environment_enabled"
                                            >
                                                <option value="no" <?php selected( $test_environment_enabled, 'no' ); ?>>
                                                <?php echo __( 'Checkout POS production environment', 'woocommerce-kis' ) ?>
                                                </option>
                                                <option value="yes" <?php selected( $test_environment_enabled, 'yes' ); ?>>
                                                <?php echo __( 'Checkout POS test environment', 'woocommerce-kis' ) ?>
                                                </option>
                                        </select>
                                    </td>
                                </tr>
                            </div>
                            <div class="inside kis_system_test_inside">
                            <label for="kis_system_test"><?php echo __( 'System test', 'woocommerce-kis' ) ?></label><p>
                                     <a
                                        href="<?php esc_html_e( $sys_audit_url ); ?>"
                                        class="button kis-system_audit-link">
                                        <?php esc_html_e( 'Run system audit', 'woocommerce-kis' ); ?>
                                    </a>
                                </p>
                            </div>
                            <div class="system-test-checkbox">
                                <h2 class="auth-settings-title"><span><?php esc_html_e( 'Authentication settings', 'woocommerce-kis' ); // phpcs:ignore ?></span></h2>
                                <label for="kis_woo_auth_params_enabled">
                                    <input
                                        name="kis_woo_auth_params_enabled"
                                        id="kis_woo_auth_params_enabled"
                                        type="checkbox"
                                        value="yes"
                                        <?php checked( $auth_params_enabled, 'yes' ); ?>
                                    /> <?php echo __('<strong>Receive Checkout POS integration authentication data in URL params instead of request header.</strong> <div class="auth-url-class">Receive Checkout POS integration authentication data in URL params instead of request header. After changing the setting save the changes before you connect to Checkout POS.</div>', 'woocommerce-kis') // WPCS: XSS ok. ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php 
    }

    /**
     * Output the Sync settings section
     */
    public function sync_settings() {

        $auth_params_enabled = \WC_Admin_Settings::get_option('kis_woo_tax_calc_enabled', '');
        $product_sync_direction =  \WC_Admin_Settings::get_option('kis_product_sync_direction', '');
        $order_sync_direction = \WC_Admin_Settings::get_option('kis_order_sync_direction', '');
        $stock_sync_direction = \WC_Admin_Settings::get_option('kis_stock_sync_direction', '');

        ?>
             <div class="kis-audit-status-container">
                <div class="metabox-holder columns-1">
                    <div class="meta-box-sortables ui-sortable">
                        <div class="postbox">
                            <h2><span><?php esc_html_e( 'Sync settings', 'woocommerce-kis' ); // phpcs:ignore ?></span></h2>
                            <div class="kis_test_environment_enabled_area">
                                <tr valign="top">
                                <div class="options-container">
                                    <th class="titledesc">
                                        <label for="kis_product_sync_direction"><?php echo __( 'Product export', 'woocommerce-kis' ) ?></label>
                                    </th>
                                    <td class="forminp">
                                        <select
                                            name="kis_product_sync_direction"
                                            id="kis_product_sync_direction"
                                            >
                                                <option value="woo_to_kassa" <?php selected( $product_sync_direction, 'woo_to_kassa' ); ?>>
                                                <?php echo __( 'Woo to Kassa', 'woocommerce-kis' ) ?>
                                                </option>
                                                <option value="kassa_to_woo" <?php selected( $product_sync_direction, 'kassa_to_woo' ); ?>>
                                                <?php echo __( 'Kassa to Woo', 'woocommerce-kis' ) ?>
                                                </option>
                                                <option value="off" <?php selected( $product_sync_direction, 'off' ); ?>>
                                                <?php echo __( 'Off', 'woocommerce-kis' ) ?>
                                                </option>
                                        </select>
                                    </td>
                                    </div>
                                    <div class="options-container">
                                        <th class="titledesc">
                                            <label for="kis_order_sync_direction"><?php echo __( 'Order export', 'woocommerce-kis' ) ?></label>
                                        </th>
                                        <td class="forminp">
                                            <select
                                                name="kis_order_sync_direction"
                                                id="kis_order_sync_direction"
                                                >
                                                    <option value="woo_to_kassa" <?php selected( $order_sync_direction, 'woo_to_kassa' ); ?>>
                                                    <?php echo __( 'Woo to Kassa', 'woocommerce-kis' ) ?>
                                                    </option>
                                                    <option value="kassa_to_woo" <?php selected( $order_sync_direction, 'kassa_to_woo' ); ?>>
                                                    <?php echo __( 'Kassa to Woo', 'woocommerce-kis' ) ?>
                                                    </option>
                                                    <option value="both" <?php selected( $order_sync_direction, 'both' ); ?>>
                                                    <?php echo __( 'Both', 'woocommerce-kis' ) ?>
                                                    </option>
                                                    <option value="off" <?php selected( $order_sync_direction, 'off' ); ?>>
                                                    <?php echo  __( 'Off', 'woocommerce-kis' ) ?>
                                                    </option>
                                            </select>
                                        </td>
                                    </div>
                                    <div class="options-container">
                                        <th class="titledesc">
                                            <label for="kis_stock_sync_direction"><?php echo __( 'Stock export', 'woocommerce-kis') ?></label>
                                        </th>
                                        <td class="forminp">
                                            <select
                                                name="kis_stock_sync_direction"
                                                id="kis_stock_sync_direction"
                                                >
                                                    <option value="woo_to_kassa" <?php selected( $stock_sync_direction, 'woo_to_kassa' ); ?>>
                                                    <?php echo __( 'Woo to Kassa', 'woocommerce-kis' ) ?>
                                                    </option>
                                                    <option value="kassa_to_woo" <?php selected( $stock_sync_direction, 'kassa_to_woo' ); ?>>
                                                    <?php echo __( 'Kassa to Woo', 'woocommerce-kis' ) ?>
                                                    </option>
                                                    <option value="off" <?php selected( $stock_sync_direction, 'off' ); ?>>
                                                    <?php echo __( 'Off', 'woocommerce-kis' ) ?>
                                                    </option>
                                            </select>
                                        </td>
                                    </div>
                                </tr>
                            </div>
                            <div class="system-test-checkbox">
                                <h2><span><?php echo __( 'Tax settings', 'woocommerce-kis' ); // phpcs:ignore ?></span></h2>
                                <label for="kis_woo_tax_calc_enabled">
                                    <input
                                        name="kis_woo_tax_calc_enabled"
                                        id="kis_woo_tax_calc_enabled"
                                        type="checkbox"
                                        value="yes"
                                        <?php checked( $auth_params_enabled, 'yes' ); ?>
                                    /> <?php echo __( '<h4>Calculate the taxes in woocommerce</h4><div class="tax-notice">There may be rounding differences between Checkout POS and WooCommerce orders if this option is enabled. Products and Fees on Checkout POS purchases which are not found in WooCommerce, use the default tax class VAT percentage. This may differ from Checkout POS.</div>', 'woocommerce-kis' ); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php
         
    }
    


    /**
     * Output the OAUTH section.
     */
    public function oauth_output() {
        $oauth           = new OAuth();
        $oauth_activated = $oauth->is_oauth_active();

        $kis_test_environment_enabled = KIS_WOOCOMMERCE_TEST_ENVIRONMENT_ENABLED === "yes" ? "test" : "production";
        $kis_test_environment_enabled = KIS_HAS_CUSTOM_ENVIRONMENT ? 
            'CUSTOM (environmental urls probably set up in wp-config.php. These settings override the environment selection.)' : 
            $kis_test_environment_enabled;

        $oauth_url        = $this->get_kassa_oauth_url();
        $cancel_oauth_url = $this->get_oauth_cancel_url();
        ?>
            <div id="poststuff" class="kis-status-container">
                <div id="post-body" class="metabox-holder columns-1">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <?php $this->the_merchant_details(); ?>
                            <div class="postbox">
                                <h2><span><?php esc_html_e( 'Connection', 'woocommerce-kis' ); // phpcs:ignore ?></span></h2>
                                <div class="inside">                           
                                    <div class="kis-oauth">
                                        <div class="kis-environment">
                                            <input type="hidden" name="kis_has_custom_environment" value="<?= KIS_HAS_CUSTOM_ENVIRONMENT; ?>" />
                                        </div>
                                        <div class="kis-oauth__section kis-oauth__section--kassa">
                                            <?php if ( $oauth_activated ) : ?>
                                                <p>
                                                    <h3 class="kis-oauth__label kis-oauth__label--checkmarked">
                                                        <?php esc_html_e( 'Connected', 'woocommerce-kis' ); ?>
                                                    </h3>
                                                </p>
                                                <p>
                                                    <span class="kis-oauth__label">
                                                        <?php esc_html_e( 'Woocommerce is connected to Checkout POS', 'woocommerce-kis' ); ?>
                                                    </span>
                                                </p>
                                                <p>
                                                    <a
                                                        href="<?php echo esc_url( $cancel_oauth_url ); ?>"
                                                        id="kis-oauth__action__disconnect-link--kassa"
                                                        class="button kis-oauth__action__disconnect-link">
                                                    <?php esc_html_e( 'Disconnect', 'woocommerce-kis' ); ?>
                                                    </a>
                                                </p>
                                            <?php else : // phpcs:ignore ?>
                                                <p>
                                                    <h3 class="kis-oauth__label kis-oauth__label--crossed">
                                                        <?php esc_html_e( 'Not connected', 'woocommerce-kis' ); ?>
                                                    </h3>
                                                </p>
                                                <p>
                                                    <span class="kis-oauth__label">
                                                        <?php esc_html_e( 'Woocommerce is not connected to Checkout POS', 'woocommerce-kis' ); ?>
                                                    </span>
                                                </p>
                                                <p>
                                                    <a
                                                        href="<?php echo esc_url( $oauth_url ); ?>"
                                                        id="kis-oauth__action__connect-link"
                                                        class="button-primary">
                                                        <?php esc_html_e( 'Connect', 'woocommerce-kis' ); ?>
                                                    </a>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br class="clear">
            </div>
        <?php
    }

    /**
     * Print out the Kassa merchant details if they exist.
     */
    private function the_merchant_details() {
        $oauth    = new OAuth();
        $merchant = $oauth->get_merchant_details();

        if ( $merchant ) {
            $address = $merchant->format_address();

            ?>
                <div class="postbox kis-merchant">
                    <h2><?php esc_html_e( 'Merchant details', 'woocommerce-kis' ); ?></h2>
                    <div class="inside">
                        <table class="kis-merchant__details">
                            <tr class="kis-merchant__details__row">
                                <td class="kis-merchant__details__row__cell kis-merchant__details__row__cell--label">
                                    <?php esc_html_e( 'Checkout POS account', 'woocommerce-kis' ); ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $merchant->get_owner_email() ); ?>
                                </td>
                            </tr>
                            <tr class="kis-merchant__details__row">
                                <td class="kis-merchant__details__row__cell kis-merchant__details__row__cell--label">
                                    <?php esc_html_e( 'Checkout POS mode', 'woocommerce-kis' ); ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $merchant->get_merchant_mode() ); ?>
                                </td>
                            </tr>
                            <tr class="kis-merchant__details__row">
                                <td class="kis-merchant__details__row__cell kis-merchant__details__row__cell--label">
                                    <?php esc_html_e( 'Phone number', 'woocommerce-kis' ); ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $merchant->get_owner_phone_number() ?? '-' ); ?>
                                </td>
                            </tr>
                            <tr class="kis-merchant__details__row">
                                <td class="kis-merchant__details__row__cell kis-merchant__details__row__cell--label">
                                    <?php esc_html_e( 'Company name', 'woocommerce-kis' ); ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $merchant->get_merchant_name() ?? '-' ); ?>
                                </td>
                            </tr>
                            <?php if ( $address ) : ?>
                                <tr class="kis-merchant__details__row">
                                    <td class="kis-merchant__details__row__cell kis-merchant__details__row__cell--label"><?php // phpcs:ignore ?>
                                        <?php esc_html_e( 'Company address', 'woocommerce-kis' ); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $merchant->get_merchant_billing_street_name() ); ?>,
                                        <?php echo esc_html( $merchant->get_merchant_billing_zip() ); ?>,
                                        <?php echo esc_html( $merchant->get_merchant_billing_city() ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            <?php
        }
    }

    /**
     * Get the WooCommerce Oauth client creation url.
     *
     * @return string
     */
    private function get_woo_oauth_client_url() : string {
        $current = \wp_parse_url( Utility::get_current_admin_url() );

        parse_str( $current['query'], $query_string );

        $query_string[ OAuth::WOO_OAUTH_CLIENT_CREATION_CMD ] = true;

        $current['query'] = http_build_query( $query_string );

        return Utility::unparse_url( $current );
    }

    /**
     * Get the cancelling url for WooCommerce Oauth.
     *
     * @return string
     */
    private function get_woo_oauth_cancel_url() : string {
        $url  = Utility::get_current_admin_url();
        $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . OAuth::WOO_OAUTH_CANCEL_CMD . '=1';

        return $url;
    }

    /**
     * Get the Kassa OAuth url.
     *
     * @return string
     */
    private function get_kassa_oauth_url() : string {
        $url = Utility::add_query_parameter( KIS_KASSA_OAUTH_URL, 'domain', Utility::get_server_name() );
        $url = Utility::add_query_parameter( $url, 'woo_return_url', Utility::get_current_admin_url() );
        $url = Utility::add_query_parameter( $url, 'kassa_oauth', '1' );
        $url = Utility::add_query_parameter( $url, 'rest_url', get_rest_url() );
        if (\get_option( static::WOO_AUTH_PARAMS ) === 'yes' ) {
            $url = Utility::add_query_parameter( $url, 'auth_params', 1);
        };
        
        // You probably don't want to change this, but here's a filter for you my friend.
        return apply_filters( 'woocommerce_kis_kassa_oauth_url', $url );
    }

    /**
     * Get the Kassa OAuth cancel url.
     *
     * @return string
     */
    private function get_oauth_cancel_url() : string {
        $callback_url = Utility::get_current_admin_url();
        $callback_url = Utility::add_query_parameter( $callback_url, Oauth::OAUTH_CANCEL_CMD, '1' );

        $url = Utility::add_query_parameter( KIS_KASSA_DELETE_OAUTH_URL, 'domain', Utility::get_server_name() );
        $url = Utility::add_query_parameter( $url, 'success_url', $callback_url );

        return $url;
    }

    /**
     * Include this settings page in the WooCommerce setting pages.
     *
     * @param \WC_Settings_Page[] $settings WooCommerce settings page instances.
     *
     * @return array
     */
    public static function include_settings_page( array $settings ) : array {
        array_push( $settings, new self() );
        return $settings;

    }
}
