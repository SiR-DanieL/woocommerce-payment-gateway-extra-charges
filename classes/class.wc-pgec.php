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
        //Load plugin languages
        load_plugin_textdomain( 'wc_pgec', false, dirname( plugin_basename( __FILE__ ) ) );
        //Hooks & Filters
        add_action( 'admin_head', array( $this, 'manage_form_fields' ) );
        add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_order_totals' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ) );

        global $woocommerce;

        $this->gateways                     = $woocommerce->payment_gateways->payment_gateways();
        $this->current_gateway              = null;
        $this->current_extra_charge_type    = '';
        $this->current_extra_charge_amount  = 0;

        add_action( 'wp_footer' , array( $this, 'print_inline_checkout_js' ) );
    }

    /**
     * Manage gateways form fields
     *
     * @return string
     */
    public function manage_form_fields() {
        global $woocommerce;

        $current_tab        = !isset( $_GET['tab'] ) || empty( $_GET['tab'] )         ? '' : sanitize_text_field( urldecode( $_GET['tab'] ) );
        $current_section    = !isset( $_GET['section'] ) || empty( $_GET['section'] ) ? '' : sanitize_text_field( urldecode( $_GET['section'] ) );
        $current_gateway    = '';
        $charge_amount      = 0.00;
        $charge_type        = 'fixed';

        if( $current_tab == 'payment_gateways' && !empty( $current_section ) ) {
            foreach( $this->gateways as $gateway ) {
                if( get_class( $gateway ) == $current_section ) {
                    $current_gateway = $gateway->id;

                    if( isset( $_REQUEST['save'] ) ) {
                        update_option( $this->get_option_id( $current_gateway, 'amount' ), $_REQUEST[ $this->get_option_id( $current_gateway, 'amount' ) ] );
                        update_option( $this->get_option_id( $current_gateway, 'type' ),   $_REQUEST[ $this->get_option_id( $current_gateway, 'type' ) ] );
                    }

                    $charge_amount = get_option( $this->get_option_id( $current_gateway, 'amount' ) );
                    $charge_type   = get_option( $this->get_option_id( $current_gateway, 'type' ) );
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
                </tbody>
            </table>
            <?php

            $html = ob_get_clean();
            $html = str_replace( array( "\r", "\n" ) , '', trim( $html ) );
            $html = str_replace( "'", '"', $html );

            $woocommerce->add_inline_js( "$( '.form-table:last' ).after( '" . $html . "' );" );
        }
    }

    /**
     * Add extra charge to cart totals
     *
     * @param double $totals
     * return double
     */
    public function calculate_order_totals( $totals ) {
        global $woocommerce;

        $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
        $current_gateway = $woocommerce->session->chosen_payment_method;

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

        //Add charges to cart totals
        if( !empty( $current_gateway ) ) {
            $extra_charge_amount = get_option( $this->get_option_id( $current_gateway->id, 'amount' ) );
            $extra_charge_type   = get_option( $this->get_option_id( $current_gateway->id, 'type' ) );

            if( $extra_charge_type == 'percentage' ) {
                $extra_charge_amount = round( $totals->cart_contents_total * $extra_charge_amount / 100 , 2 );
            }

            $totals->cart_contents_total += $extra_charge_amount;

            $this->current_gateway             = $current_gateway;
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
        ?>
        <tr class="payment-extra-charge">
            <th><?php printf( __( '%s Extra Charges', 'wc_pgec' ), $this->current_gateway->title ) ?></th>
            <td>
            <?php if( $this->current_extra_charge_type == 'percentage' ) {
                printf( _x( '%1$s (%2$s&#37;)', 'value of the extra charge in order review', 'wc_pgec' ), woocommerce_price( $this->current_extra_charge_amount ), get_option( $this->get_option_id( $this->current_gateway->id, 'amount' ) ) ) ;
            } else {
                echo woocommerce_price( $this->current_extra_charge_amount );
            }
            ?>
            </td>
        </tr>
        <?php
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
     * Prints checkout payment method form hanlder
     */
    public function print_inline_checkout_js() {
        if( !is_checkout() ) return;

        global $woocommerce;
        $woocommerce->add_inline_js( "$(document.body).on('change', 'input[name=\"payment_method\"]', function() { $('body').trigger('update_checkout'); });" );
    }

    /**
     * Save order extra charge into the database
     *
     * @param $order_id
     */
    public function update_order_meta( $order_id ) {
        update_post_meta( $order_id, '_extra-charge', esc_attr( $this->current_extra_charge_amount ) );
    }
}
endif;