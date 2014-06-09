<?php
/**
 * WooCommerce Payment Gateway Extra Charges
 * Copyright (C) 2013 Nicola Mustone. All rights reserved.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Contact the author at mustone.nicola@gmail.com
 */

/**
 * Main plugin class
 *
 * @author Nicola Mustone
 */

if( !class_exists( 'WooCommerce_Payment_Gateway_Extra_Charges' ) ) :
class WooCommerce_Payment_Gateway_Extra_Charges {
    /**
     * @var string
     */
    public $version;

    /**
     * @var string
     */
    public $suffix;

    /**
     * @var string
     */
    public $plugin_url;

    /**
     * @var string
     */
    public $plugin_path;

    /**
     * @var array
     */
    public $gateways;

    /**
     * @var object
     */
    public $current_gateway;

    /**
     * @var string
     */
    public $current_extra_charge_type;

    /**
     * @var double
     */
    public $current_extra_charge_amount;

    /**
     * Constructor
     *
     * @param string $id Order id
     */
    public function __construct() {
        global $pagenow;

        $this->version                              = '1.3';
        $this->suffix                               = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        $this->plugin_url                           = $this->plugin_url();
        $this->plugin_path                          = $this->plugin_path();
        $this->gateways                             = WC()->payment_gateways->payment_gateways();
        $this->current_gateway                      = null;
        $this->current_extra_charge_type            = '';
        $this->current_extra_charge_amount          = 0;
        $this->current_extra_charge_max_cart_value  = 0;

        //Load plugin languages
        load_plugin_textdomain( 'wc_pgec', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/i18n/' );

        //Hooks & Filters
        add_action( 'woocommerce_calculate_totals',             array( $this, 'calculate_order_totals' ) );
        add_action( 'woocommerce_checkout_update_order_meta',   array( $this, 'update_order_meta' ) );
        add_action( 'woocommerce_get_order_item_totals',        array( $this, 'add_emails_row' ), 10, 2 );
        add_action( 'wp_footer' ,                               array( $this, 'print_inline_checkout_js' ) );

        if( is_admin() ) {
            add_action( 'admin_head',                                    array( $this, 'manage_form_fields' ) );
            add_action( 'woocommerce_admin_order_totals_after_shipping', array( $this, 'add_order_write_panel_row' ) );
            add_action( 'woocommerce_process_shop_order_meta',           array( $this, 'update_shop_order_meta' ), 10, 2 );

            //Add this hook only in post.php in the admin. The file enqueued needs a WooCommerce file that is loaded only in this page
            if( $pagenow == 'post.php' && $_GET['action'] == 'edit' && isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) == 'shop_order' ) {
                //This file overwrite a function of WooCommerce. If there are problems after an update, try to remove it.
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            }
        }
    }

    /**
     * Manage gateways form fields
     *
     * @return string
     */
    public function manage_form_fields() {
        $current_tab        = !isset( $_GET['tab'] )     || empty( $_GET['tab'] )     ? '' : sanitize_text_field( urldecode( $_GET['tab'] ) );
        $current_section    = !isset( $_GET['section'] ) || empty( $_GET['section'] ) ? '' : sanitize_text_field( urldecode( $_GET['section'] ) );
        $current_gateway    = '';
        $charge_amount      = 0.00;
        $charge_type        = 'fixed';
        $max_cart_value     = 0.00;

        if( $current_tab == 'checkout' && !empty( $current_section ) ) {
            foreach( $this->gateways as $gateway ) {
                if( strtolower( get_class( $gateway ) ) == $current_section ) {
                    $current_gateway = $gateway->id;

                    if( isset( $_REQUEST['save'] ) ) {
                        update_option( $this->get_option_id( $current_gateway, 'amount' ), $_REQUEST[ $this->get_option_id( $current_gateway, 'amount' ) ] );
                        update_option( $this->get_option_id( $current_gateway, 'type' ),   $_REQUEST[ $this->get_option_id( $current_gateway, 'type' ) ] );
                        update_option( $this->get_option_id( $current_gateway, 'max_cart_value' ), $_REQUEST[ $this->get_option_id( $current_gateway, 'max_cart_value' ) ] );
                    }

                    $charge_amount  = get_option( $this->get_option_id( $current_gateway, 'amount' ) );
                    $charge_type    = get_option( $this->get_option_id( $current_gateway, 'type' ) );
                    $max_cart_value = get_option( $this->get_option_id( $current_gateway, 'max_cart_value' ) );
                }
            }

            ob_start() ?>
            <h4><?php _e( 'Extra charge for this payment method', 'wc_pgec' ) ?></h4>
            <p><?php _e( 'Optionally add extra charge fixed/percentage amount to this payment method.', 'wc_pgec' ) ?></p>
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc"><label for="woocommerce_<?php echo $current_gateway ?>_extra_charge_amount"><?php _e( 'Extra charge amount', 'wc_pgec' ) ?></label></th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e( 'Extra charge amount', 'wc_pgec' ) ?></span></legend>
                                <input class="input-text regular-input " type="number" name="woocommerce_<?php echo $current_gateway ?>_extra_charge_amount" id="woocommerce_<?php echo $current_gateway ?>_extra_charge_amount" style="width:70px" value="<?php echo $charge_amount ?>" placeholder="" min="0" step="0.01">
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc"><label for="woocommerce_<?php echo $current_gateway ?>_extra_charge_type"><?php _e( 'Extra charge type', 'wc_pgec' ) ?></label></th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e( 'Extra charge type', 'wc_pgec' ) ?></span></legend>
                                <select name="woocommerce_<?php echo $current_gateway ?>_extra_charge_type" id="woocommerce_<?php echo $current_gateway ?>_extra_charge_type" style="" class="select ">
                                    <option value="fixed" <?php selected( $charge_type, 'fixed' ) ?>><?php _e( 'Fixed amount', 'wc_pgec' ) ?></option>
                                    <option value="percentage"<?php selected( $charge_type, 'percentage' ) ?>><?php _e( 'Percentage amount', 'wc_pgec' ) ?></option>
                                </select>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc"><label for="woocommerce_<?php echo $current_gateway ?>_extra_charge_max_cart_value"><?php _e( 'Maximum cart value for adding fee:', 'wc_pgec' ) ?></label></th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php _e( 'Maximum cart value for adding fee:', 'wc_pgec' ) ?></span></legend>
                                <input class="input-text regular-input " type="number" name="woocommerce_<?php echo $current_gateway ?>_extra_charge_max_cart_value" id="woocommerce_<?php echo $current_gateway ?>_extra_charge_max_cart_value" style="width:70px" value="<?php echo $max_cart_value ?>" placeholder="" min="0" step="0.01">
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php

            $html = ob_get_clean();
            $html = str_replace( array( "\r", "\n" ) , '', trim( $html ) );
            $html = str_replace( "'", '"', $html );

            wc_enqueue_js( "$( '.form-table:last' ).after( '" . $html . "' );" );
        }
    }

    /**
     * Add extra charge to cart totals
     *
     * @param double $totals
     * return double
     */
    public function calculate_order_totals( $totals ) {
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $current_gateway = WC()->session->chosen_payment_method;

        if( !empty( $available_gateways ) ) {
            //Get the current gateway
            if ( isset( $current_gateway ) && isset( $available_gateways[ $current_gateway ] ) ) {
                $current_gateway = $available_gateways[ $current_gateway ];
            } elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
                $current_gateway = $available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
            } else {
                $current_gateway = current( $available_gateways );
            }
        }

        $extra_charge_max_cart_value    = get_option( $this->get_option_id( $current_gateway->id, 'max_cart_value' ) );

        //Add charges to cart totals
        if( !empty( $current_gateway ) && !empty( $extra_charge_max_cart_value ) && $extra_charge_max_cart_value >= $totals->cart_contents_total ) {
            $extra_charge_amount            = get_option( $this->get_option_id( $current_gateway->id, 'amount' ) );
            $extra_charge_type              = get_option( $this->get_option_id( $current_gateway->id, 'type' ) );

            if( $extra_charge_type == 'percentage' ) {
                $extra_charge_amount = round( $totals->cart_contents_total * $extra_charge_amount / 100 , 2 );
            }

            $totals->cart_contents_total += $extra_charge_amount;

            $this->current_gateway             = $current_gateway; //Note: this is an object
            $this->current_extra_charge_amount = $extra_charge_amount;
            $this->current_extra_charge_type   = $extra_charge_type;

            //Print the extra charge row in order review table
            add_action( 'woocommerce_review_order_before_order_total',  array( $this, 'add_order_review_row' ) );
        }
    }

    /**
     * Add extra charge row in order review table
     *
     * @return void
     */
    public function add_order_review_row(){
        if( $this->current_extra_charge_amount <= 0 ) { return; }
        ?>
        <tr class="payment-extra-charge">
            <th><?php printf( _x( '%s fee', '%s is the payment gateway choosen', 'wc_pgec' ), $this->current_gateway->title ) ?></th>
            <td>
            <?php if( $this->current_extra_charge_type == 'percentage' ) {
                printf( _x( '%1$s (%2$.2f&#37;)', 'value of the fees in order review ( ie: 10,50€ (5%) )', 'wc_pgec' ), wc_price( $this->current_extra_charge_amount ), get_option( $this->get_option_id( $this->current_gateway->id, 'amount' ) ) ) ;
            } else {
                echo wc_price( $this->current_extra_charge_amount );
            }
            ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Add a box on the right of the order detail admin page to handle the extra charge of the order
     *
     * @param $order_id
     */
    public function add_order_write_panel_row( $order_id ) {
        $extra_charge = get_post_meta( $order_id, '_extra-charge', true );
        ?>
            <div class="clear"></div>
        </div>
        <!-- close previous div, due to the hook from WooCommerce -->
        <div class="totals_group">
            <h4><?php _e( 'Payment method fee', 'wc_pgec' ); ?></h4>
            <ul class="totals">
                <li class="wide">
                    <label><?php _e( 'Payment method fee', 'wc_pgec' )?>:</label>
                    <input type="number" step="0.01" min="0" id="_extra-charge" name="_extra-charge" placeholder="0.00" value="<?php echo esc_attr( number_format( $extra_charge, 2 ) ) ?>" class="calculated" />
                </li>
            </ul>
            <div class="clear"></div>
        <?php
    }

    /**
     * Add extra charge row to the emails
     *
     * @param $total_rows
     * @param $wc_order
     * @return array
     */
    public function add_emails_row( $total_rows, $wc_order ) {
        $last_element = array_pop( $total_rows );

        $total_rows['extra_charge'] = array(
            'label' => __( 'Payment method fee', 'wc_pgec' ) . ':',
            'value' => wc_price( get_post_meta( $wc_order->id, '_extra-charge', true ) )
        );

        $total_rows[] = $last_element;

        return $total_rows;
    }

    /**
     * Return formatted name of the option
     *
     * @param string $payment_gateway
     * @param string $option
     * @return string
     */
    public function get_option_id( $payment_gateway, $option ) {
        return 'woocommerce_' . $payment_gateway . '_extra_charge_' . $option;
    }

    /**
     * Save order extra charge into the database
     *
     * @param $order_id
     * @return bool
     */
    public function update_order_meta( $order_id ) {
        return update_post_meta( $order_id, '_extra-charge', wc_clean( $this->current_extra_charge_amount ) );
    }

    /**
     * Update order extra charge when the order is saved in the admin
     *
     * @param $order_id
     * @param $post
     * @return bool
     */
    public function update_shop_order_meta( $order_id, $post ) {
        if( isset( $_POST['_extra-charge'] ) ) {
            return update_post_meta( $order_id, '_extra-charge', wc_clean( $_POST['_extra-charge'] ) );
        }

        return false;
    }

    /**
     * Enqueue JavaScript files
     */
    public function enqueue_scripts() {
        wp_enqueue_script( 'wc-pgec-write-panels', $this->plugin_url() . '/assets/js/write-panels' . $this->suffix . '.js', array( 'woocommerce_admin_meta_boxes' ), $this->version, true );
    }

    /**
     * Print checkout payment method form hanlder
     */
    public function print_inline_checkout_js() {
        if( !is_checkout() ) return;

        wc_enqueue_js( "$(document.body).on('change', 'input[name=\"payment_method\"]', function() { $('body').trigger('update_checkout'); });" );
    }

    /**
     * Get the plugin url.
     *
     * @return string
     */
    public function plugin_url() {
        if ( $this->plugin_url ) return $this->plugin_url;
        return $this->plugin_url = untrailingslashit( plugins_url( '/', dirname( __FILE__ ) ) );
    }


    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        if ( $this->plugin_path ) return $this->plugin_path;

        return $this->plugin_path = untrailingslashit( dirname( plugin_dir_path( __FILE__ ) ) );
    }
}
endif;