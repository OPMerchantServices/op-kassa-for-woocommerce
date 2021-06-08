<?php
/**
 * This class contains methods to render and save data from order shipping info metaboxes
 */

namespace CheckoutFinland\WooCommerceKIS\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Class OrderInfoMetaBox
 *
 * @since      0.4.0
 * @package CheckoutFinland\WooCommerceKIS
 */
final class OrderInfoMetaBox {
    /**
     * Unique identifier for the meta box
     */
    const META_BOX_IDENTIFIER = 'woocommerce-kis-orderbox-shipping-info';

    /**
     * Title shown on the box
     */
    const META_BOX_TITLE = 'Checkout POS Tracking Information';

    /**
     * Prefix for WordPress post metadata keys
     */
    const POST_META_PREFIX = '_kassa_';

    /**
     * Metadata key names
     */
    const TRACKING_CODE_FIELD_NAME = 'tracking_code';
    const CARRIER_FIELD_NAME = 'carrier';
    const PICKUP_ID_FIELD_NAME = 'pickup_id';


    /**
     * Callback to save post metadata when post is saved
     *
     * @param int $postID
     * @param WP_Post $postAfter
     * @param WP_Post $postBefore
     * @return void
     *
     * @see https://codex.wordpress.org/Plugin_API/Action_Reference/post_updated
     */
    public function save_metabox_data($postID, $postAfter, $postBefore) {
        if (get_post_type($postID) !== 'shop_order') {
            return;
        }

        if (!isset($_POST[self::POST_META_PREFIX . self::TRACKING_CODE_FIELD_NAME])) {
            return;
        }

        $trackingCode = filter_input( INPUT_POST, self::POST_META_PREFIX . self::TRACKING_CODE_FIELD_NAME, FILTER_SANITIZE_STRING );

        update_post_meta($postID, self::POST_META_PREFIX . self::TRACKING_CODE_FIELD_NAME, $trackingCode);
    }

    /**
     * Add a new metabox to shop_order post page
     *
     * @return void
     */
    public function orderbox_metabox() {
        add_meta_box(
            self::META_BOX_IDENTIFIER,
            __(self::META_BOX_TITLE, 'woocommerce-kis'),
            [$this, 'metabox_render_callback'],
            'shop_order'
        );
    }

    /**
     * Callback for the metabox render on shop_order post page
     *
     * @return void
     */
    public function metabox_render_callback() {
        global $post;
        $fields = [
            self::TRACKING_CODE_FIELD_NAME => 'Tracking code',
            self::CARRIER_FIELD_NAME       => 'Carrier',
            self::PICKUP_ID_FIELD_NAME     => 'Pickup ID',
        ];
?>
        <div class="panel-wrap woocommerce">
            <div class="panel">
                <?php foreach ($fields as $field => $title): ?>
                    <p class="form-field form-row">
                        <label for="<?= self::POST_META_PREFIX . $field ?>"><?= __($title, 'woocommerce-kis') ?>:</label>
                        <input type="text"
                            <?= $field !== self::TRACKING_CODE_FIELD_NAME ? 'disabled' : '' ?>
                            id="<?= self::POST_META_PREFIX . $field ?>"
                            name="<?= self::POST_META_PREFIX . $field ?>"
                            value="<?= get_post_meta($post->ID, self::POST_META_PREFIX . $field, true) ?>"
                        />
                    </p>
                <?php endforeach; ?>
            </div>
        </div>
<?php
    }
}
