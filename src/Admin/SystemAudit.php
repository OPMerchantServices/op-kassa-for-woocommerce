<?php
/**
 * Description: Performs system audit for the plugin
 * Author: Ambientia Oy
 * Author URI: https://www.ambientia.fi
 */

namespace CheckoutFinland\WooCommerceKIS\Admin;

use CheckoutFinland\WooCommerceKIS\Admin\Notice;
use CheckoutFinland\WooCommerceKIS\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Performs system audit for the plugin
 *
 * @package    CheckoutFinland\WooCommerceKIS\Admin
 */
final class SystemAudit {

    /**
     * Transient data (used for audit messages) time to live value
     */
    const TRANSIENT_DATA_TTL = 5;

    /**
     * Message type constants
     */
    const MESSAGE_TYPE_ERROR = 'ERROR';
    const MESSAGE_TYPE_WARNING = 'WARNING';
    const MESSAGE_TYPE_SUCCESS = 'SUCCESS';

    /**
     * Performs a system audit based on given configurations
     * 
     * @return void
     */
    static public function perform_system_audit() : void {
        $is_audit_passed = true;
        $plugin = \CheckoutFinland\WooCommerceKIS\plugin();

        $config_source_url = KIS_WOOCOMMERCE_SYSTEM_AUDIT_CONFIG_URL;

        $system_audit_config = self::get_system_audit_configs($config_source_url);

        if ( $system_audit_config ) {

            $result = self::check_kassa_connection();
            $is_audit_passed = $is_audit_passed ? $result : false;
            
            if (isset($system_audit_config['ini_params'])) {
                $result = self::check_ini_params($system_audit_config['ini_params']);
                $is_audit_passed = $is_audit_passed ? $result : false;
            }

            if (isset($system_audit_config['wp_options'])) {
                $result = self::check_wp_options($system_audit_config['wp_options']);
                $is_audit_passed = $is_audit_passed ? $result : false;
            }

            if (isset($system_audit_config['target_url'])) {
                $result = self::check_connectivity($system_audit_config['target_url']);
                $is_audit_passed = $is_audit_passed ? $result : false;
            }

            if (isset($system_audit_config['mandatory_plugins'])) {
                $result = self::check_mandatory_plugins(
                    $system_audit_config['mandatory_plugins'],  
                    isset($system_audit_config['plugin_info']) ? $system_audit_config['plugin_info'] : []
                );
                $is_audit_passed = $is_audit_passed ? $result : false;
            }

            if (isset($system_audit_config['incompatible_plugins'])) {
                $result = self::check_incompatible_plugins(
                    $system_audit_config['incompatible_plugins'],
                    isset($system_audit_config['plugin_info']) ? $system_audit_config['plugin_info'] : [],
                    isset($system_audit_config['pass_audit_on_warning']) ? $system_audit_config['pass_audit_on_warning'] : true
                );
                $is_audit_passed = $is_audit_passed ? $result : false;
            }

            $result = self::check_qTranslate_plugin_settings();
            $is_audit_passed = $is_audit_passed ? $result : false;

        } else {
            self::add_to_audit_messages(__('System audit configuration file not found!', 'woocommerce-kis'), 
                self::MESSAGE_TYPE_ERROR);
            $is_audit_passed = false;
        }

        if ( $is_audit_passed ) {
            self::add_to_audit_messages(__('System audit passed!', 'woocommerce-kis'), self::MESSAGE_TYPE_SUCCESS);
        }

        // This is a temporary solution to always sync products and stocks to same direction
        $product_sync = \get_option('kis_product_sync_direction');
        if ($product_sync) {
            \update_option('kis_stock_sync_direction', $product_sync);
        }
    }

    /**
     * Checks whether the plugin has been connected to Checkout POS or not. Produces a warning.
     * 
     * @return bool
     */
    static private function check_kassa_connection() : bool {
        $oauth = new OAuth();
        if (!$oauth->is_oauth_active()) {
            self::add_to_audit_messages(__('The Checkout POS-plugin has not yet been connected to Checkout POS.' .
                ' Connect to Checkout POS in the plugin <a href="/wp-admin/admin.php?page=wc-settings&tab=kis"><b>settings</b></a>.',
                'woocommerce-kis'), self::MESSAGE_TYPE_WARNING);

            return false;
        }

        return true;
    }

    /**
     * System parameter check wrapper function
     * 
     * @param array $ini_params collection of system parameters to check
     * @return bool
     */
    static private function check_ini_params(array $ini_params) : bool {
        $is_check_successful = true;

        foreach ($ini_params as $ini_param) {
            $ini_param_value = self::get_ini_param($ini_param['name']);
            $result = false;
            if ($ini_param_value) {
                $method = 'check_' . $ini_param['name'];
                $result = self::$method($ini_param_value, $ini_param['limit']);
            }
            $is_check_successful = $is_check_successful ? $result : false;
        }

        return $is_check_successful;
    }

    /**
     * Gets the given system parameter value
     * 
     * @param string $ini_param
     * @return mixed
     */
    static private function get_ini_param($ini_param) {
        $ini_param_value = ini_get($ini_param);

        // If given parameter is null (empty) or not set (false)
        if (!$ini_param_value) {
            self::add_to_audit_messages(__('System variable not set:', 'woocommerce-kis') . ' ' . $ini_param);
            return false;
        }

        return $ini_param_value;
    }

    /**
     * Handles validating the memory_limit system parameter
     * 
     * @param mixed $ini_param_value
     * @param int $limit the minimum memory_limit value in MB
     * @return bool
     */
    static private function check_memory_limit($ini_param_value, $limit) : bool {
        $memory_limit_bytes = self::return_bytes($ini_param_value);

        if ($memory_limit_bytes < ($limit * 1024 * 1024)) {
            self::add_to_audit_messages(__('System memory limit insufficient', 'woocommerce-kis') . 
                ' (< ' . $limit . ' MB): ' . $ini_param_value);
            return false;
        }

        return true;
    }

    /**
     * Handles validating the max_execution_time system parameter
     * 
     * @param mixed $ini_param_value
     * @param int $limit the minimum max_execution_time value in seconds
     * @return bool
     */
    static private function check_max_execution_time($ini_param_value, $limit) : bool {

        if ($ini_param_value < $limit) {
            self::add_to_audit_messages(__('Max execution time limit insufficient', 'woocommerce-kis') .
                ' (<' . $limit . 's): ' . $ini_param_value);
            return false;
        }

        return true;
    }

    /**
    * Converts shorthand memory notation value to bytes
    * From http://php.net/manual/en/function.ini-get.php
    *
    * @param string $val Memory size shorthand notation
    * @return int
    */
    static private function return_bytes($val) : int {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);

        if (!is_numeric($last)) {
            $val = (int)substr($val, 0, -1);
        }
        
        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
            default:
                // Already in bytes
        }
        return $val;
    }

    /**
     * Handles validating the incompatible plugins
     * 
     * @param array $plugins
     * @param bool $warn_only Flag for determining wether this check should fail the audit or just generate a warning
     * @param array $plugin_info
     * @return bool
     */
    static private function check_incompatible_plugins(array $plugins, array $plugin_info, $warn_only = true) : bool {
        $is_check_successful = true;
        //wp_die(json_encode(get_option('active_plugins')));
        foreach ($plugins as $plugin) {
            $plugin = self::get_plugin_info($plugin, $plugin_info);
            $result = is_plugin_active($plugin['plugin']);

            if ($result) {
                $message_type = $warn_only ? self::MESSAGE_TYPE_WARNING : self::MESSAGE_TYPE_ERROR;
                $msg = $plugin['url'] ? ' <a href="' . $plugin['url'] . '" target="_blank">' . $plugin['name'] . '</a>' : ' ' . $plugin['name'];
                $msg .= isset($plugin['desc']) ? '<span style="display: inline-block; font-size: smaller; background: #f1f1f1; padding: 10px 5px;">' . __($plugin['desc'], 'woocommerce-kis') . '</span>' : '';

                self::add_to_audit_messages(__('The following active plugin may be incompatible:', 'woocommerce-kis') . $msg, $message_type);
            }
            
            // Do not fail the audit when incompatible plugins are found if warn_only flag is enabled
            $is_check_successful = ($is_check_successful && !$warn_only) ? $result : $warn_only;
        }

        return $is_check_successful;
    }

    /**
     * Handles validating the mandatory plugins
     * 
     * @param array $plugins
     * @param array $plugin_info
     * @return bool
     */
    static private function check_mandatory_plugins(array $plugins, array $plugin_info) : bool {
        $is_check_successful = true;
        //wp_die(json_encode(get_option('active_plugins')));
        foreach ($plugins as $plugin) {
            $plugin = self::get_plugin_info($plugin, $plugin_info);
            $msg = $plugin['url'] ? ' <a href="' . $plugin['url'] . '" target="_blank">' . $plugin['name'] . '</a>' : ' ' . $plugin['name'];

            if (!is_plugin_active($plugin['plugin'])) {
                self::add_to_audit_messages(__('The following plugin needs to be installed and activated:', 'woocommerce-kis') . $msg);
                $is_check_successful = false;
            }
        }

        return $is_check_successful;
    }

    /**
     * Helper method for getting plugin information
     * 
     * @param string $plugin
     * @param array $plugin_info
     * @return array
     */
    static private function get_plugin_info($plugin, array $plugin_info) {
        foreach ($plugin_info as $info) {
            if (isset($info['plugin']) && $info['plugin'] === $plugin) {
                return $info;
            }
        }

        return [
            'plugin' => $plugin,
            'name' => $plugin,
            'url' => ''
        ];
    }

    /**
     * Handles validating the mandatory Wordpress options
     * 
     * @param array $options
     * @return bool
     */
    static private function check_wp_options($options) : bool {
        $is_check_successful = true;

        foreach ($options as $option) {
          $result = get_option($option['name']);

            if ($result !== $option['value']) {
                self::add_to_audit_messages(
                    __('The Wordpress setting', 'woocommerce-kis') . 
                    ' <a href="' . get_site_url(null, $option['path'], 'admin') . 
                    '" >' . $option['name'] . '</a> ' . __('needs to be set to:', 'woocommerce-kis') . ' ' . 
                    __($option['gui_name'], 'woocommerce-kis'),
                    (isset($option['warn_only']) && $option['warn_only']) ? self::MESSAGE_TYPE_WARNING : self::MESSAGE_TYPE_ERROR
                );

                $is_check_successful = (isset($option['warn_only']) && $option['warn_only'])?: false;
            }
        }

        return $is_check_successful;
    }

    /**
     * Get System Audit configurations from external source
     * 
     * @param string $source_url
     * @return array|null
     */
    static private function get_system_audit_configs($source_url) {
        return json_decode( wp_remote_retrieve_body( wp_remote_get( $source_url )), true);
    }

    /**
     * Custom check for qTranslate-plugins' settings using global $q_config-variable
     * 
     * @return bool
     */
    static private function check_qTranslate_plugin_settings() : bool {
        global $q_config;
        $is_check_successful = true;

        if ( !is_null( $q_config ) && !is_null( $q_config['hide_default_language'] ) ) {
            if ( $q_config['hide_default_language'] == false) {
                self::add_to_audit_messages(__('qTranslate-plugin seems to be activated! To prevent issues with the Checkout POS-plugin, please select the <i>"Hide URL language information for default language."</i>-setting (if it is available) in the <i>"Settings"</i> -> <i>"Languages"</i> -> <i>"General"</i>.', 'woocommerce-kis'),
                self::MESSAGE_TYPE_WARNING);
                $is_check_successful = false;
            }
        }

        return $is_check_successful;
    }

    /**
     * Adds messages in system audit report based on message type
     * 
     * @param string $message
     * @param string $message_type
     * @return void
     */
    static private function add_to_audit_messages($message, $message_type = self::MESSAGE_TYPE_ERROR) : void {
        $system_audit_notice = get_transient( 'system-audit-notice-' . strtolower($message_type) );
        $system_audit_notice .= '<br />* ' . $message;
        set_transient('system-audit-notice-' . strtolower($message_type), $system_audit_notice, self::TRANSIENT_DATA_TTL);
    }

    /**
     * Displays the system audit report
     * 
     * @return void
     */
    static public function display_system_audit_admin_notice() : void {

        if ( $system_audit_notice = get_transient( 'system-audit-notice-error' )) {
            ?>
            <div class="notice notice-error">
                <p>Checkout POS: <?php echo '<b>' . __('Audit Failed:', 'woocommerce-kis') . '</b><br />' . $system_audit_notice; ?></p>
            </div>
            <?php
            delete_transient( 'system-audit-notice-error' );
            deactivate_plugins( plugin_basename( 'wp-woocommerce-kis-plugin/plugin.php' ) ); // Old plugin basename
            deactivate_plugins( plugin_basename( 'op-kassa-for-woocommerce/plugin.php' ) );
            unset($_GET['activate']);
        }

        if ( $system_audit_notice = get_transient( 'system-audit-notice-warning' ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>Checkout POS: <?php echo '<b>' . __('Audit Warnings:', 'woocommerce-kis') . '</b><br />' . $system_audit_notice; ?></p>
            </div>
            <?php
            delete_transient( 'system-audit-notice-warning' );
        }

        if ( $system_audit_notice = get_transient( 'system-audit-notice-success' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>Checkout POS: <?php echo '<b>' . __('Audit Success!', 'woocommerce-kis') . '</b><br />' . $system_audit_notice; ?></p>
            </div>
            <?php
            delete_transient( 'system-audit-notice-success' );
        }
    }

    /**
     * Gets the system audit report
     * 
     * @return string
     */
    static public function get_system_audit_notice() : string {
        $system_audit_notice = "";

        if ( $error = get_transient( 'system-audit-notice-error' )) {
            $system_audit_notice .= '<div class="system-audit-notice fail"><p><b>' . 
                __('Audit Failed:', 'woocommerce-kis') . '</b><br />' . $error . '</p></div>';
            delete_transient( 'system-audit-notice-error' );
        }

        if ( $warning = get_transient( 'system-audit-notice-warning' ) ) {
            $system_audit_notice .= '<div class="system-audit-notice warning"><p><b>' . 
            __('Audit Warning:', 'woocommerce-kis') . '</b><br />' . $warning . '</p></div>';
            delete_transient( 'system-audit-notice-warning' );
        }

        if ( $success = get_transient( 'system-audit-notice-success' ) ) {
            $system_audit_notice .= '<div class="system-audit-notice success">' . $success . '</p></div>';
            delete_transient( 'system-audit-notice-success' );
        }

        return $system_audit_notice;
    }
}
