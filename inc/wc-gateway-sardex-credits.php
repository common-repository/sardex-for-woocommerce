<?php

/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;


/**
* Add the gateway to WooCommerce.
*/
class WC_Gateway_Sardex_Credits extends WC_Payment_Gateway {


    function __construct() {

        $this->plugin_url = trailingslashit( plugins_url( '', SARDEX_PLUGIN_FILE ) );
        $this->plugin_path = plugin_dir_path( SARDEX_PLUGIN_FILE );
        $base_img_url = $this->plugin_url . 'assets/img/';

        $this->id = 'sardex-credits';

        $this->method_description = 'Abilita i pagamenti B2B in crediti';
        $this->has_fields = false; // this is a redirect gateway, no need payment fields.

        $this->img = array(
            'admin_logo' => $base_img_url . 'sardex-logo-big.png',
        );

        // Payment method details based on user details
        $paymentMethodDetails = WC_Gateway_Sardex_Helper::get_circuit_payment_method_details();
        $this->method_title = $paymentMethodDetails['name'];
        $this->title = $paymentMethodDetails['name'];
        $this->description = $paymentMethodDetails['description'];
        $this->icon = $paymentMethodDetails['icon']; // WC_Payment_Gateway->icon enables a small icon near the payment gateway name

        $this->apis = WC_Gateway_Sardex_Helper::get_api_endpoints();
        $this->merchant_auth64 = WC_Gateway_Sardex_Helper::get_merchant_auth();

        $this->info = $this->get_enabled_merchant_info();

        $currency_eur_ok = get_woocommerce_currency() === 'EUR';

        // Check if the payment gateway can be enabled.
        if ( !empty($this->merchant_auth64) && empty($this->info['err_msg']) && $currency_eur_ok && get_option('wc_sardex_b2b_payment_enabled') === 'yes' ) {
            // Payment actions
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'payment_response' ) );
            add_filter( 'woocommerce_available_payment_gateways', array( $this, 'order_can_be_paid_with_sardex' ) );
        } else {
            $this->enabled = 'no'; // Disable payment on checkout
            update_option('wc_sardex_b2b_payment_enabled', 'no');
        }

        // Enqueue stuff
        wp_enqueue_style( 'sardex-for-woocommerce-css', $this->plugin_url . 'assets/css/sardex-for-woocommerce.css' );
        wp_enqueue_script( 'sardex-for-woocommerce-checkout', $this->plugin_url . 'assets/js/checkout.js', array( 'jquery' ), '1', true );

        // Settings action
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Admin Panel Options
     */
    function admin_options() {
        $settings_page_url = WC_Gateway_Sardex_Helper::get_settings_tab_url();
        
        echo '<h2>' . esc_html( $this->get_method_title() );
        wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
        echo '</h2>';
        ?>

    <div class="sardex-admin-main">
        <div class="sardex-admin-main-inner">
            <div class="sardex-logo-wrapper">
                <a href="https://www.sardexpay.net/" target="_blank"><img alt="sardexpay.net" src="<?php echo $this->img['admin_logo']; ?>" id="sardex-logo"/></a>
            </div>
            <div class="sardex-merchant-info">

            <?php if ( !empty( $this->info['info'] ) ) { ?>

                <?php
                if ($this->enabled && empty( $this->info['err_msg'] ) ) {
                    echo "<div class='sardex-warning'>" .
                        "<span class=\"dashicons dashicons-yes\" style='vertical-align: middle'></span>" .
                        "<strong>Il metodo di pagamento è attivo.</strong>" .
                        "</div>";
                }
                ?>

                <h3>Informazioni esercente</h3>
                <?php

                if ( ! empty( $this->info['err_msg'] ) ) {
                    echo '<div class="sardex-warning">' . $this->info['err_msg'] . '</div>';
                }

                if ( !empty( $this->info['info']['display_name'] ) ) {
                    $labels = array(

                        'display_name' => 'Nome online',
                    );

                    if ( ! empty( $this->info['info'] ) ) {
                        echo '<ul>';
                        foreach ( $this->info['info'] as $k => $v ) {
                            if ( ! empty( $labels[$k] ) ) {


                                    $value = $v;


                                echo '<li>' . $labels[$k] . ': <strong>' . $value . '</strong></li>';
                            }
                        }
                        echo '</ul>';
                    }
                }
            }
            else {
                if ( ! empty( $this->merchant_auth64 ) && ! empty( $this->info['err_msg'] ) ) {
                    echo '<div class="sardex-warning">' . $this->info['err_msg'] . '</div>';
                }
            }
            ?>
            <a href="<?php echo $settings_page_url; ?>" class="sardex-button-primary">Vai alle impostazioni</a>
            </div>
        </div>
    </div>
        <?php
    }


    /**
     * Check whether or not the payment gateway can be enabled.
     *
     * @return array
     */
    private function get_enabled_merchant_info() {

        $err_msg = '';
        $err_end = "<br>Contatta il servizio clienti all'indirizzo email broker@sardexpay.net o al numero 070 3327433 (interno 2) per maggiori informazioni.";
        $merchant_info = WC_Gateway_Sardex_Helper::get_merchant_info();

        if ( empty( $merchant_info ) ) {
            $err_msg = "<strong>Impossibile effettuare il login.</strong><br>" .
                        "Per poter utilizzare il plugin devi essere iscritto al Circuito SardexPay " .
                        "o ad un Circuito di Credito Commerciale partner." . $err_end;
        } elseif(get_option('wc_sardex_b2b_payment_enabled') !== 'yes') {
            $err_msg = "Attenzione: non hai abilitato i pagamenti in crediti nel panello delle <strong>Impostazioni</strong>.<br>In questo modo i tuo clienti non potranno effettuare pagamenti in crediti nel tuo negozio online.";
        }

        return array(
            'err_msg' => $err_msg,
            'info' => $merchant_info
        );
    }


    /**
     * Disable SardexPay Cashback payments if the currency is not EUR.
     *
     * @param  array $gateway_list List of all gateways available.
     * @return array
     */
    public function order_can_be_paid_with_sardex( $gateway_list ) {

        $the_currency = get_option( 'woocommerce_currency' );
        if ( $the_currency != 'EUR' ) {
            unset( $gateway_list['sardex'] );
        }

        return $gateway_list;
    }


    /**
     * Process the payment and return the result.
     *
     * @param  int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) {
        $transfer_type = WC_Gateway_Sardex_Helper::get_user_transfer_type();

        try {
            WC_Gateway_Sardex_Helper::log( ">> Processing order n. " . $order_id );

            $order = wc_get_order( $order_id );
            $success_url = trim(get_bloginfo( 'url' ), '/') . '/?wc-api=' . get_class( $this );

            $raw_response = wp_safe_remote_post( $this->apis['create_ticket'], array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Channel' => 'ecommerce',
                    'Authorization' => $this->merchant_auth64
                ),
                'body' => wp_json_encode( array(
                    "orderId" => $order_id,
                    "amount" => number_format( $order->get_total(), 2, '.', '' ),
                    "description" => "Addebito da " . get_bloginfo( 'url' ),

                    // The transfer type, either the id or qualified internal name.
                    "type" => $transfer_type,

                    // The url to redirect when canceling the approve ticket flow.
                    "cancelUrl" => esc_url_raw( $order->get_cancel_order_url_raw() ),

                    // The url to redirect after successful approving a ticket.
                    "successUrl" => $success_url,
                    "successWebhook" => $success_url,

                    // Defines the expiration interval. If none is given, it is assumed that the ticket expires in one day.
                    "expiresAfter" => array(
                        "amount" => 1,
                        "field" => "days"
                    )
                )),
                'timeout' => 70,
                'user-agent' => 'WooCommerce/' . WC()->version,
                'httpversion' => '1.1',
            ));

            if ( is_wp_error( $raw_response ) || empty( $raw_response['body'] ) ) {
                WC_Gateway_Sardex_Helper::log( "Empty create_ticket response", array(), "error" );
                throw new Exception( 'Empty Response' );
            }

            $response = json_decode( $raw_response['body'] );

            if ( empty( $response->ticketNumber ) ) {

                $err = 'Ticket number not found';
                if ( ! empty( $raw_response['response']['message'] ) ) {
                    $err = $raw_response['response']['message'];
                }

                WC_Gateway_Sardex_Helper::log( "Error on process_payment(): " . $err, $raw_response['body'], "error" );
                throw new Exception( $err );
            }

            WC_Gateway_Sardex_Helper::log( ">> Sardex ticket number: " . $response->ticketNumber );

            update_post_meta( $order_id, '_sardex_ticket_number', $response->ticketNumber );

            // Send the customer to the payment page
            return array(
                'result'   => 'success',
                'redirect' => str_replace( '{TICKET_NUMBER}', $response->ticketNumber,  $this->apis['pay'] )
            );
        }
        catch ( Exception $e ) {

			wc_add_notice( 'Impossibile completare il pagamento [' . $e->getMessage() . ']', 'error' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
        }

    } // end process_payment()


    /**
     * Check the Sardex response after returning from the payment.
     */
    function payment_response() {

        WC_Gateway_Sardex_Helper::log( "... Checking Sardex APIs response: ", $_REQUEST );

        // Transactions must have these informations.
        if ( empty( $_REQUEST['orderId'] ) || empty( $_REQUEST['ticketNumber'] ) ) {
            WC_Gateway_Sardex_Helper::log( "orderId or ticketNumber is empty", array(), "error" );
            die();
        }

        $order_id = $_REQUEST['orderId'];
        $order = wc_get_order( $order_id );
        $ticket_number = get_post_meta( $order_id, '_sardex_ticket_number', true );

        // Check if there are problems loading the order object or the ticket number.
        if ( empty( $order ) || empty( $ticket_number ) ) {
            WC_Gateway_Sardex_Helper::log( "Loaded order or ticket number is empty for order {$order_id}", array(), "error" );
            die();
        }

        // Check if there is a correspondence between the received ticket number and the one saved when processing the payment.
        if ( $ticket_number != $_REQUEST['ticketNumber'] ) {
            WC_Gateway_Sardex_Helper::log( "Received ticketNumber is different from the one saved.", array(), "error" );
            die();
        }

        // Check also that the current order is not already completed
        if ( ! in_array( $order->get_status(), array( 'completed', 'processing' ) ) ) {

            // Pingback APIs to process an approved transaction.
            $process_url = str_replace(
                array( '{TICKET_NUMBER}', '{ORDER_ID}' ),
                array( $ticket_number, $order_id ),
                $this->apis['process_ticket']
            );

            $raw_response = wp_safe_remote_post( $process_url, array(
                'method' => 'POST',
                'headers' => array(
                    'Channel'       => 'ecommerce',
                    'Authorization' => $this->merchant_auth64
                ),
                'timeout' => 70,
                'user-agent' => 'WooCommerce/' . WC()->version,
                'httpversion' => '1.1',
            ));

            if ( is_wp_error( $raw_response ) ) {
                return new WP_Error( 'sardex-api', 'Error Response process_ticket: ' . $raw_response->get_error_message() );
            }
            else if ( empty( $raw_response['response'] ) ) {
                return new WP_Error( 'sardex-api', 'Empty process_ticket Response' );
            }

            $process_resp = $raw_response['response'];

            WC_Gateway_Sardex_Helper::log( "Pingback process_ticket Response", $process_resp );

            // Pingback results are ok
            if ( $process_resp['code'] == 200 && $process_resp['message'] == 'OK' ) {
                // Complete order, add transaction ID and note.
                $order->payment_complete( $ticket_number );
                $order->add_order_note( 'Pagamento con Sardex completato. ID Ticket: ' . $ticket_number );

                WC_Gateway_Sardex_Helper::log( '>>> OK >>> Order ' . $order_id . ' completed successfully.  ' );
            }
            else {

                if ( $process_resp['code'] == 500 ) {
                    $err_msg = SARDEX_UNKNOWN_ERROR;
                }
                else {
                    $err_msg = $process_resp['code'] . ' ' . $process_resp['message'];
                }

                $order->update_status( 'failed' );
                $order->add_order_note( 'Pagamento con Sardex fallito. Motivo: "' . $err_msg . '". ' );

                WC_Gateway_Sardex_Helper::log( '*** KO *** Order ' . $order_id . ' failed for reason: ' . $err_msg, array(), 'warning' );
            }

            // Be sure the cart is empty
            WC()->cart->empty_cart();
        }

        // Make a redirect to the right page, without overriding the previous source in Google Analytics.
        $return_url = add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) );
        header( "Location: " . $return_url );

        // Prevent multiple executions when invoking the return URL through S2S responses.
        // This is also used after the header redirect.
        die();

    } // end payment_response()

}