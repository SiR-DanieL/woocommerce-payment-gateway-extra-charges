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
     * Constructor
     *
     * @param string $id Order id
     */
    public function __construct() {
        //Load plugin languages
        load_plugin_textdomain( 'wc_pgec', false, dirname( plugin_basename( __FILE__ ) ) );
        //Hooks & Filters
        add_action( 'admin_head', array( $this, 'manage_form_fields' ) );

        global $woocommerce;

        $this->gateways = $woocommerce->payment_gateways->payment_gateways();
    }

    /**
     * Manage gateways form fields
     *
     * @return string
     */
    public function manage_form_fields() {
        $current_tab        = empty( $_GET['tab'] )         ? '' : sanitize_text_field( urldecode( $_GET['tab'] ) );
        $current_section    = empty( $_REQUEST['section'] ) ? '' : sanitize_text_field( urldecode( $_REQUEST['section'] ) );
        $current_gateway    = '';
        $charge_amount      = 0.00;
        $charge_type        = 'fixed';

        if( $current_tab == 'payment_gateways' && $current_section != '' ) {
            foreach( $this->gateways as $gateway ) {
                if( get_class( $gateway ) == $current_section ) {
                    $current_gateway = $gateway->id;

                    if( isset( $_REQUEST['save'] ) ) {
                        update_option( 'woocommerce_' . $current_gateway . '_extra_charge_amount', $_REQUEST[ 'woocommerce_' . $current_gateway . '_extra_charge_amount' ] );
                        update_option( 'woocommerce_' . $current_gateway . '_extra_charge_type',   $_REQUEST[ 'woocommerce_' . $current_gateway . '_extra_charge_type' ] );
                    }

                    $charge_amount = get_option( 'woocommerce_' . $current_gateway . '_extra_charge_amount' );
                    $charge_type   = get_option( 'woocommerce_' . $current_gateway . '_extra_charge_type' );
                }
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
        ?>
        <script>
        jQuery( document ).ready( function( $ ) { $( '.form-table:last').after( '<?php echo $html ?>' ); } );
        </script>
        <?php
    }
}
endif;