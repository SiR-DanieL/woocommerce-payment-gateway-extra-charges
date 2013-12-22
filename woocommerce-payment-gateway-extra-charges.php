<?php
/*
Plugin Name: WooCommerce Payment Gateway Extra Charges
Plugin URI: https://github.com/SiR-DanieL/woocommerce-payment-gateway-extra-charges
Description: A WooCommerce Extension that allows to add extra charges to your payment gateways
Version: 1.0
Author: Nicola Mustone
Author URI: http://nicolamustone.it
*/

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

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'init', 'wc_pgec_init' );
function wc_pgec_init() {
    global $woocommerce;

    if( !isset( $woocommerce ) ) { return; }

    require_once( 'classes/class.wc-pgec.php' );

    new WooCommerce_Payment_Gateway_Extra_Charges();
}

function wc_pgec_debug() {
    $values = func_get_args();

    for( $i = 0; $i < count( $values ); $i++ ) {
        echo '<pre>Param ' . $i . ': ';
        var_dump( $values[$i] );
        echo '</pre>';
    }
}