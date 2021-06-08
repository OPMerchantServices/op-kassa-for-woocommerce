<?php
/**
 * This class controls OAuth functionalities.
 */

namespace CheckoutFinland\WooCommerceKIS;

use CheckoutFinland\WooCommerceKIS\Admin\Notice;
use CheckoutFinland\WooCommerceKIS\Utility;
use CheckoutFinland\WooCommerceKIS\Webhooks;
use WP_Post;

/**
 * Class OAuth
 *
 * This class controls OAuth functionalities.
 *
 * @package CheckoutFinland\WooCommerceKIS
 */
class OAuth {

    const CONSUMER_TITLE = 'WooCommerce KIS';

    /**
     * The option name for the WooCommerce OAUTH status option.
     */
    const WOO_USER_OPTION = 'kis_oauth_woo_user';

    /**
     * This option name is used to store the connected merchant details.
     */
    const KASSA_MERCHANT_DETAILS_OPTION = 'kis_merchant_details';

    /**
     * This command string is used as a GET parameter.
     */
    const WOO_OAUTH_CANCEL_CMD = 'woo_oauth_cancel';

    /**
     * This command string is used as a GET parameter.
     */
    const WOO_OAUTH_SUCCESS_CMD = 'woo_auth_success';

    /**
     * This command is used in the Kassa OAuth callback return URL
     * to mark a successful connection and to pass the merchant data to WooCommerce.
     */
    const KASSA_OAUTH_MERCHANT_DETAILS_CMD = 'merchant_details';

    /**
     * This command string is used as a GET parameter.
     */
    const OAUTH_CANCEL_CMD = 'kis_oauth_cancel';

    /**
     * This is the GET parameter used to identify the request for Oauth user creation.
     */
    const WOO_OAUTH_CLIENT_CREATION_CMD = 'woo_oauth_client_creation';

    /**
     * This command string is used to display error notices.
     */
    const KIS_OAUTH_ERROR_CMD = 'kis_auth_error';

    /**
     * This is used as a meta value to identify WooCommerce KIS json_consumer posts.
     */
    const WOO_OAUTH_CLIENT_TYPE = '_woocommerce_kis';

    /**
     * An instance of Notice class to display admin notices.
     *
     * @var Notice
     */
    private $notice;

    /**
     * Get the current OAUTH Client with its meta.
     *
     * @return WP_Post|null
     */
    public static function get_oauth_client() : ?WP_Post {
        // Get the posts.
        $posts = static::get_kis_oauth_clients();

        // If the OAUTH client is not present, bail early.
        if ( empty( $posts ) ) {
            return null;
        }

        // Return the first item, there shouldn't be more.
        return $posts[0];
    }

    /**
     * Get all OAuth clients created by KIS.
     *
     * @return WP_Post[]|null
     */
    private static function get_kis_oauth_clients() : ?array {
        $posts = get_posts( [
            'numberposts'            => -1, // phpcs:ignore
            'post_type'              => 'json_consumer',
            'post_status'            => 'any',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'  => [ // phpcs:ignore
                'key'     => static::WOO_OAUTH_CLIENT_TYPE,
                'compare' => 'EXISTS',
            ],
        ] );

        return $posts;
    }

    /**
     * OAuth constructor.
     *
     * @param Notice $notice An instance of notice class.
     */
    public function __construct( ?Notice $notice = null ) {
        $this->notice = $notice;

        add_action( 'admin_init', [ $this, 'handle_woo_success_request' ] );
        add_action( 'admin_init', [ $this, 'handle_oauth_delete' ] );
    }

    /**
     * This method is hooked to the WP Rest API OAUTH plugin's
     * 'json_oauth1_access_token_data' hook.
     * If the oauth access is created for the consumer application,
     * we store the user id.
     * This creates a flag into the WordPress option table
     * determining if the authorization is active or not.
     *
     * @param array $data JSON OAuth1 access token data.
     *
     * @return array
     */
    public function handle_json_access_token_data( array $data = [] ) : array {
        if ( $this->is_kis_consumer( $data['consumer'] ?? 0 ) ) {
            $user_id = $data['user'] ?? 0;
            if ( $user_id ) {
                $this->set_oauth_user( $user_id );
            }
        }

        return $data;
    }

    /**
     * Stores the id of the user holding the
     * WordPress REST API OAuth application access.
     *
     * @param integer $user        The OAUTH user id.
     *
     * @return bool False if value was not updated and true if value was updated.
     */
    protected function set_oauth_user( int $user ) : bool {
        $updated = \update_option( static::WOO_USER_OPTION, $user );

        return $updated;
    }

    /**
     * Get the current WordPress OAuth JSON consumer id.
     *
     * @return int
     */
    public function get_oauth_user() : int {
        $oauth_user_id = \get_option( static::WOO_USER_OPTION );
        return (int) $oauth_user_id ?: 0;
    }

    /**
     * Stores the merchant details object.
     *
     * @param Merchant $merchant The merchant details object.
     *
     * @return bool False if value was not updated and true if value was updated.
     */
    protected function set_merchant_details( Merchant $merchant ) : bool {
        $updated = \update_option( static::KASSA_MERCHANT_DETAILS_OPTION, $merchant );

        return $updated;
    }

    /**
     * Get the current WordPress OAuth JSON consumer id.
     *
     * @return Merchant
     */
    public function get_merchant_details() : ?Merchant {
        $merchant = \get_option( static::KASSA_MERCHANT_DETAILS_OPTION );
        return $merchant ?: null;
    }

    /**
     * Evaluate if a JSON consumer is the KIS consumer.
     *
     * TODO: evaluate the match by a post meta value, not the title.
     *
     * @param int $consumer_id The post id of a JSON consumer.
     *
     * @return bool
     */
    protected function is_kis_consumer( int $consumer_id ) : bool {
        $consumer = \get_post( $consumer_id );

        if (
            ! empty( $consumer ) &&
            \get_post_type( $consumer ) === 'json_consumer' &&
            $consumer->post_title === static::CONSUMER_TITLE
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if the OAUTH is active between KIS, Kassa and WooCommerce.
     *
     * @return bool True if the auth is active and the application user still exists.
     *              False if the auth is not active.
     */
    public function is_oauth_active() {
        $user_id = $this->get_oauth_user();
        if ( $user_id && get_userdata( $user_id ) && $this->get_merchant_details() ) {
            return true;
        }

        return false;
    }

    /**
     * Cancel the OAuth connection to KIS.
     * 
     * @param bool $force Forces oath delete handling without the url param
     * 
     * @return void
     */
    public function handle_oauth_delete($force = false) {
        $delete = filter_input( INPUT_GET, static::OAUTH_CANCEL_CMD, FILTER_SANITIZE_STRING );

        if ( ! $delete && ! $force) {
            return;
        }

        // Allow Kassa OAuth delete,
        // if Woo OAuth is no longer active or the user has can manage OAuths.
        if ( $this->current_user_can_manage_oauth() ) {
            $this->delete_oauth();
            $message = __( 'The KIS connection was successfully cancelled!', 'woocommerce-kis' );
            $this->notice->success( $message );
            return;
        }

        $this->notice->oauth_cancel_deny_notice();
    }

    /**
     * Delete all authentication related data from WooCommerce.
     */
    private function delete_oauth() {
        \delete_option( static::KASSA_MERCHANT_DETAILS_OPTION );

        \delete_option( static::WOO_USER_OPTION );

        // Delete the webhooks created by the plugin
        Webhooks::delete_all();
    }

    /**
     * Handle the success request from the WooCommerce auth.
     *
     * @return void
     */
    public function handle_woo_success_request() {
        $success = filter_input( INPUT_GET, static::WOO_OAUTH_SUCCESS_CMD, FILTER_SANITIZE_STRING );

        if ( $success ) {
            $this->create_kis_webhooks();

            $message = __( 'Connected successfully!', 'woocommerce-kis' );
            $this->notice->success( $message );
        }
    }

    /**
     * Handles a Kassa OAuth response.
     */
    public function handle_kassa_oauth_response() {
        $base64_data = filter_input( INPUT_GET, static::KASSA_OAUTH_MERCHANT_DETAILS_CMD, FILTER_SANITIZE_STRING );

        if ( $base64_data ) {
            if ( ! $this->current_user_can_manage_oauth() ) {
                $error_msg = __( 'You are not allowed to create the connection!', 'woocommerce-kis' );
                $this->notice->error( $error_msg );
                return;
            }

            $merchant_details = base64_decode( $base64_data );
            $merchant         = new Merchant( (object) json_decode( $merchant_details ) );

            if ( ! $merchant->is_valid() ) {
                $error_msg = __( 'Connecting to Checkout POS failed!', 'woocommerce-kis' );
                $this->delete_oauth();
                $this->notice->error( $error_msg );
                return;
            }

            // Store details.
            $this->set_merchant_details( $merchant );
            $this->set_oauth_user( get_current_user_id() );
        }
    }

    /**
     * Creates the OAUTH Client to the database before redirecting the user to KIS.
     */
    private function handle_woo_oauth_client_creation() {
        // Delete all possible previously created OAUTH Clients.
        $this->delete_oauth_clients();

        $callback_url = KIS_WOOCOMMERCE_OAUTH_CALLBACK_URL . '?domain=' . Utility::get_server_name();

        // Create the OAUTH Client.
        $client = \WP_REST_OAuth1_Client::create([
            'name'        => 'WooCommerce KIS',
            'description' => 'KIS API',
            'meta'        => [
                // Use the type name as a key for quicker indexed queries for when the info is needed.
                static::WOO_OAUTH_CLIENT_TYPE => 'true',
                'callback'                    => $callback_url,
            ],
        ]);

        if ( is_wp_error( $client ) ) {
            $wp_error = $client->get_error_message();
            // translators: "%s" is a placeholder for the error message.
            $error_msg = sprintf( _x(
                'Connecting to WooCommerce failed! Error: %s',
                '"%s" is a placeholder for the error message.',
                'woocommerce-kis'
            ), $wp_error );

            $this->delete_oauth();

            $this->notice->error( $error_msg );
            return;
        }

        $consumer_secret = get_post_meta( $client->post->ID, 'secret', true );
        $consumer_key    = get_post_meta( $client->post->ID, 'key', true );

        if ( empty( $consumer_secret ) || empty( $consumer_secret ) ) {
            $error_msg = __(
                'Connecting to WooCommerce failed! An error occurred when creating the WordPress OAuth application.',
                'woocommerce-kis'
            );

            $this->delete_oauth();

            $this->notice->error( $error_msg );
            return;
        }

        if ( !get_option( 'kassa_connected_site_url' ) ) {
            add_option( 'kassa_connected_site_url', get_option('siteurl') );
        } else {
            update_option( 'kassa_connected_site_url', get_option('siteurl') );
        }

        header( 'Location: ' . $this->get_woo_oauth_url( $consumer_key, $consumer_secret ) );
    }

    /**
     * Delete all possible previously created OAUTH Clients.
     *
     * @return integer The amount of posts deleted.
     */
    private function delete_oauth_clients() : int {
        $clients = static::get_kis_oauth_clients();

        // Delete all previously created OAUTH Client instances.
        array_walk( $clients, function( WP_Post $post ) {
            \wp_delete_post( $post->ID, true );
        });

        return count( $clients );
    }

    /**
     * Returns whether current user can manage OAuth or not.
     *
     * Currently we check if the user can manage WooCommerce
     * and allow OAuth manipulation based on the capability.
     *
     * @return boolean
     */
    private function current_user_can_manage_oauth() : bool {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Create the webhooks for KIS integration.
     *
     * @return void
     */
    private function create_kis_webhooks() {
        $resources = [
            'order',
            'product',
        ];

        $events = [
            'created',
            'updated',
            'deleted',
            'restored',
        ];

        $oauth_client = static::get_oauth_client();

        if ( ! $oauth_client ) {
            return;
        }

        // Get the merchant details.
        $merchant = $this->get_merchant_details();

        $query = http_build_query(
            [
                'domain'      => Utility::get_server_name(),
                'merchant_id' => $merchant->get_merchant_id(),
            ]
        );

        // Use the OAuth token secret as the webhook secert.
        $webhook_secret = get_post_meta( $oauth_client->ID, 'secret', true );

        // All resource changes are posted to the same endpoint.
        $delivery_url = KIS_WOOCOMMERCE_WEBHOOK_URL . '/?' . $query;

        foreach ( $resources as $resource ) {
            foreach ( $events as $event ) {
                Webhooks::create([
                    'name'         => sprintf(
                        'WooCommerce KIS %s %s',
                        ucfirst( $resource ),
                        ucfirst( $event )
                    ),
                    'topic'        => $resource . '.' . $event,
                    'delivery_url' => $delivery_url,
                    'secret'       => $webhook_secret ?? null,
                ]);
            }
        }
    }

    /**
     * Get the WooCommerce OAuth url.
     *
     * @param string $consumer_key    The consumer key to use.
     * @param string $consumer_secret The consumer secret to use.
     * @return string
     */
    private function get_woo_oauth_url( string $consumer_key, string $consumer_secret ) : string {
        $success_url = static::get_woo_auth_success_url();

        $query = http_build_query(
            [
                'consumerKey'    => $consumer_key,
                'consumerSecret' => $consumer_secret,
                'domain'         => Utility::get_server_name(),
                'restUrl'        => \get_rest_url(),
                'successUrl'     => $success_url,
            ]
        );

        // You probably don't want to change this, but here's a filter for you my friend.
        return apply_filters( 'woocommerce_kis_woo_oauth_url', KIS_WOOCOMMERCE_OAUTH_URL . '?' . $query );
    }

    /**
     * Creates a return URL for the OAUTH authentication.
     *
     * @return string
     */
    private function get_woo_auth_success_url() : string {
        return Utility::get_current_admin_url();
    }
}
