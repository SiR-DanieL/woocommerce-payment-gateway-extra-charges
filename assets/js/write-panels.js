jQuery( document ).ready( function( $ ) {
    $( 'button.calc_totals').unbind( 'click' );

    console.log( $( 'button.calc_totals').unbind( 'click' ) );

    $( 'button.calc_totals').on( 'click', function() {
        // Block write panel
        $('#woocommerce-order-totals').block({ message: null, overlayCSS: { background: '#fff url(' + woocommerce_writepanel_params.plugin_url + '/assets/images/ajax-loader.gif) no-repeat center', opacity: 0.6 } });

        var answer = confirm(woocommerce_writepanel_params.calc_totals);

        if (answer) {

            // Get row totals
            var line_subtotals 		= 0;
            var line_subtotal_taxes = 0;
            var line_totals 		= 0;
            var cart_discount 		= 0;
            var cart_tax 			= 0;
            var order_shipping 		= $('#_order_shipping').val() || '0';
            var order_shipping_tax 	= $('#_order_shipping_tax').val() || '0';
            var order_discount		= $('#_order_discount').val() || '0';
            var extra_charges       = $('#_extra-charge').val() || '0';

            order_shipping = accounting.unformat( order_shipping.replace(',', '.') );
            order_shipping_tax = accounting.unformat( order_shipping_tax.replace(',', '.') );
            order_discount = accounting.unformat( order_discount.replace(',', '.') );
            extra_charges = accounting.unformat( extra_charges.replace(',', '.') );

            $('#order_items_list tr.item').each(function(){

                var line_subtotal 		= $(this).find('input.line_subtotal').val() || '0';
                var line_subtotal_tax 	= $(this).find('input.line_subtotal_tax').val() || '0';
                var line_total 			= $(this).find('input.line_total').val() || '0';
                var line_tax 			= $(this).find('input.line_tax').val() || '0';

                line_subtotal = accounting.unformat( line_subtotal.replace(',', '.') );
                line_subtotal_tax = accounting.unformat( line_subtotal_tax.replace(',', '.') );
                line_total = accounting.unformat( line_total.replace(',', '.') );
                line_tax = accounting.unformat( line_tax.replace(',', '.') );

                line_subtotals = line_subtotals + line_subtotal;
                line_subtotal_taxes = line_subtotal_taxes + line_subtotal_tax;
                line_totals = line_totals + line_total;

                if ( woocommerce_writepanel_params.round_at_subtotal=='no' ) {
                    line_tax = accounting.toFixed( line_tax, 2 );
                }

                cart_tax = cart_tax + parseFloat( line_tax );
            });

            // Tax
            if (woocommerce_writepanel_params.round_at_subtotal=='yes') {
                cart_tax = accounting.toFixed( cart_tax, 2 );
            }

            // Cart discount
            var cart_discount = ( line_subtotals + line_subtotal_taxes ) - ( line_totals + cart_tax );
            if ( cart_discount < 0 ) cart_discount = 0;
            cart_discount = accounting.toFixed( cart_discount, 2 );

            $('#order_items_list tr.fee').each(function(){
                var line_total 			= $(this).find('input.line_total').val() || '0';;
                var line_tax 			= $(this).find('input.line_tax').val() || '0';;

                line_total = accounting.unformat( line_total.replace(',', '.') );
                line_tax = accounting.unformat( line_tax.replace(',', '.') );

                line_totals = line_totals + line_total;

                if ( woocommerce_writepanel_params.round_at_subtotal=='no' ) {
                    line_tax = accounting.toFixed( line_tax, 2 );
                }

                cart_tax = cart_tax + parseFloat( line_tax );
            });

            // Tax
            if (woocommerce_writepanel_params.round_at_subtotal=='yes') {
                cart_tax = parseFloat( accounting.toFixed( cart_tax, 2 ) );
            }

            // Total
            var order_total = line_totals + cart_tax + order_shipping + order_shipping_tax + extra_charges - order_discount;
            order_total = accounting.toFixed( order_total, 2 );
            cart_tax = accounting.toFixed( cart_tax, 2 );

            // Set fields
            $('#_cart_discount').val( cart_discount ).change();
            $('#_order_tax').val( cart_tax ).change();
            $('#_order_total').val( order_total ).change();

            $('#woocommerce-order-totals').unblock();

        } else {
            $('#woocommerce-order-totals').unblock();
        }
        return false;
    } );
} );