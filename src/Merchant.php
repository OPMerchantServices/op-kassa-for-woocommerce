<?php
/**
 * This class is used to wrap the Checkout POS OAuth response data.
 */

namespace CheckoutFinland\WooCommerceKIS;

/**
 * Class Merchant
 *
 * This class is used to wrap the Checkout POS OAuth response data.
 *
 * @package CheckoutFinland\WooCommerceKIS
 */
class Merchant {

    /**
     * The merchant id.
     *
     * @var int
     */
    protected $merchant_id;

    /**
     * The merchant owner email.
     *
     * @var string
     */
    protected $owner_email;

    /**
     * The merchant mode.
     *
     * @var string
     */
    protected $merchant_mode;

    /**
     * The merchant owner phone number.
     *
     * @var string
     */
    protected $owner_phone_number;

    /**
     * * The merchant name.
     *
     * @var string
     */
    protected $merchant_name;

    /**
     * The merchant billing street name.
     *
     * @var string
     */
    protected $merchant_billing_street_name;

    /**
     * The merchant billing zip.
     *
     * @var string
     */
    protected $merchant_billing_zip;

    /**
     * The merchant billing city.
     *
     * @var string
     */
    protected $merchant_billing_city;

    /**
     * MerchantDetails constructor.
     *
     * @param object $data The decoded JSON data.
     */
    public function __construct( $data ) {
        if ( empty( $data ) ) {
            return;
        }

        foreach ( $data as $key => $value ) {
            if ( property_exists( $this, $key ) ) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Get the merchant id.
     *
     * @return int
     */
    public function get_merchant_id() : ?int {

        return $this->merchant_id;
    }

    /**
     * Get the owner email.
     *
     * @return string
     */
    public function get_owner_email() : ?string {

        return $this->owner_email;
    }

    /**
     * Get the merchant mode.
     *
     * @return string
     */
    public function get_merchant_mode() : ?string {

        return $this->merchant_mode;
    }

    /**
     * Get the owner phone number.
     *
     * @return string
     */
    public function get_owner_phone_number() : ?string {

        return $this->owner_phone_number;
    }

    /**
     * Get the merchant_name.
     *
     * @return string
     */
    public function get_merchant_name() : ?string {

        return $this->merchant_name;
    }

    /**
     * Get the merchant billing street name.
     *
     * @return string
     */
    public function get_merchant_billing_street_name() : ?string {

        return $this->merchant_billing_street_name;
    }

    /**
     * Get the merchant billing zip.
     *
     * @return string
     */
    public function get_merchant_billing_zip() : ?string {

        return $this->merchant_billing_zip;
    }

    /**
     * Get the merchant billing city.
     *
     * @return string
     */
    public function get_merchant_billing_city() : ?string {

        return $this->merchant_billing_city;
    }

    /**
     * Format the merchant address into a printable format.
     *
     * @return string
     */
    public function format_address() : string {
        $address = [
            $this->merchant_billing_street_name,
            $this->merchant_billing_zip,
            $this->merchant_billing_city,
        ];

        $address = array_filter( $address );

        return implode( ', ', $address );
    }

    /**
     * Evaluate the object validity.
     *
     * @return bool
     */
    public function is_valid() {
        if ( empty( $this->owner_email ) ) {
            return false;
        }
        return true;
    }
}
