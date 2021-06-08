<?php
/**
 * This class controls modifications of the WooCommerce REST API.
 */

namespace CheckoutFinland\WooCommerceKIS;

use WP_REST_Request;

/**
 * Class Api
 *
 * This class controls modifications of the WooCommerce REST API.
 *
 * @package CheckoutFinland\WooCommerceKIS
 */
class Api {

    /**
     * This query var is used to create a modified by date query.
     */
    const MODIFIED_AFTER_QUERY_VAR = 'kis_modified_after';

    /**
     * This query var is used to create a meta query
     * to match for only objects created by KIS.
     */
    const KIS_OBJECT_TYPE_QUERY_VAR = 'kis_object_type';

    /**
     * Customize the WooCommerce REST API query.
     *
     * This method is hooked to the following hook
     * where post types are the WooCommerce post types:
     * "woocommerce_rest_{$post_type}_object_query"
     *
     * @param array           $args    Key value array of query var to query value.
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return array A prepared date query array.
     */
    public function filter_wc_rest_query( array $args, WP_REST_Request $request ) : array {
        $args = $this->add_modified_by_date_query( $args, $request );
        $args = $this->add_object_type_meta_query( $args, $request );

        return $args;
    }

    /**
     * Add a modified by date query if the query parameters match.
     *
     * This is used to force the time handling required by KIS.
     * Results are always ordered in ascending order by the modified date.
     *
     * @see https://github.com/woocommerce/wc-api-dev/issues/65
     *
     * @param array           $args    Key value array of query var to query value.
     * @param WP_REST_Request $request Full details about the request.
     * @return array A prepared date query array.
     */
    protected function add_modified_by_date_query( array $args, WP_REST_Request $request ) : array {
        $param = $request->get_param( static::MODIFIED_AFTER_QUERY_VAR );
        if ( $param !== null ) {
            $unix_timestamp = (int) filter_var( $param, FILTER_SANITIZE_NUMBER_INT );
            $unix_timestamp = $this->substractTimeDifference($unix_timestamp);

            $modified_after = date( 'c', $unix_timestamp );

            $args['date_query'] = [
                'after'  => $modified_after,
                'column' => 'post_modified_gmt',
            ];

            // Order by modified time in an ascending order.
            $args['orderby'] = 'modified';
            $args['order']   = 'ASC';
        }

        return $args;
    }

    /**
     * Given date query timestamp is converted to local time in function: Wp_Date_Query::build_mysql_datetime
     * We need to substract the time difference between the local timezone (set in Wordpress) and GMT
     * to counter the effect of using of the local time instead of GMT.
     * 
     * For example, assume we want to query items that has 'post_modified_gmt': 2020-04-09T11:25:05
     * 1) We want to query items that have 'post_modified_gmt' after timestamp 1586427402 (GMT: Thursday, 9th April 2020, 10.16)
     * 2) We get the timezone set in Wordpress (eg. 'Europe/Helsinki') and calculate the time difference to GMT (on summer time: 10800 seconds)
     * 3) We substract the time difference from the original query timestamp: 1586427402 - 10800 = 1586416602 (GMT: Thursday, 9th April 2020, 07.16)
     * 4) Wordpress then uses this new timestamp (-10800 seconds to GMT) to get the local time set in Wordpress which is +10800 seconds to GMT thus returning the correct GMT (GMT: Thursday, 9th April 2020, 10.16)
     * 5) The item with 'post_item_gmt' of 2020-04-09T11:25:05 is now returned as it is modified after the given time (GMT: Thursday, 9th April 2020, 10.16)
     * 
     * Without this helper method Wordpress will return local time (LOCAL: Thursday, 9th April 2020, 13.16) and thus the item with 'post_item_gmt' of 2020-04-09T11:25:05
     * is NOT returned as it is not modified after the given time.
     */
    private function substractTimeDifference($timestamp) {

        // wp_timezone()-function is a WP5.3-> feature.
        // So here we make sure the timezone is received the old fashioned way for the old fashioned WP's.
        $wp_timezone = null;
        if ( ! function_exists( 'wp_timezone' ) ) {
            $timezone_string = get_option( 'timezone_string' );
    
            if ( $timezone_string ) {
                $wp_timezone = (array) new \DateTimeZone( $timezone_string );
            } else {
                $offset  = (float) get_option( 'gmt_offset' );
                $hours   = (int) $offset;
                $minutes = ( $offset - $hours );
        
                $sign      = ( $offset < 0 ) ? '-' : '+';
                $abs_hour  = abs( $hours );
                $abs_mins  = abs( $minutes * 60 );
                $tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
        
                $wp_timezone =  (array) new \DateTimeZone( $tz_offset );
            }
        } else {
            $wp_timezone = (array) wp_timezone(); 
        }

        $wp_date_time_zone = new \DateTimeZone($wp_timezone['timezone']);
        $wp_time = new \DateTime('now', $wp_date_time_zone);

        return $timestamp - $wp_time->getOffset();
    }

    /**
     * Add a meta query if the query parameters match.
     *
     * This is used to find a Woo object through the API mathcing a specific Checkout POS type.
     * For instance, Checkout POS purchases are marked with a meta key that is used
     * for fetching all orders created by KIS.
     *
     * @param array           $args    Key value array of query var to query value.
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return array A prepared date query array.
     */
    protected function add_object_type_meta_query( array $args, WP_REST_Request $request ) : array {
        $param = $request->get_param( static::KIS_OBJECT_TYPE_QUERY_VAR );
        if ( $param ) {

            if ( ! isset( $args['meta_query'] ) ) {
                // phpcs:ignore -- Ignore the meta query.
                $args['meta_query'] = [];
            }
            $args['meta_query'][] = [
                'key'     => filter_var( $param, FILTER_SANITIZE_STRING ),
                'compare' => 'EXISTS',
            ];
        }

        return $args;
    }

    public function delete_auth_token(){
        global $wpdb;
    
        $token_id = filter_input(INPUT_GET, 'token_id', FILTER_SANITIZE_NUMBER_INT);
        if ( !empty($token_id)) {
            $wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $token_id ), array( '%d' ) );
        }   
    }
}
