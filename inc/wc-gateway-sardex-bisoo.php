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

if ( ! class_exists( 'WC_Gateway_Sardex_Bisoo' ) ) :

class WC_Gateway_Sardex_Bisoo {

    private static $main_gateway = null;
    private static $minimum_order_total = 0.25;
    public static $merchant_info;
    public static $bisoo_rate;


    /**
     * Init hooks which need to be always added.
     */
    public static function setup() {
        add_action( 'admin_post_do_bisoo_recharge', array( __CLASS__, 'do_bisoo_recharge' ) );
        add_action( 'admin_post_nopriv_do_bisoo_recharge', array( __CLASS__, 'do_bisoo_recharge' ) );
        add_action( 'woocommerce_before_pay_action', array( __CLASS__, 'set_order_as_rechargeable_pay' ) );
    }

    /**
     * Setter to access to some gateway stuff and init hooks.
     */
    public static function init( $gw ) {

        self::$merchant_info = WC_Gateway_Sardex_Helper::get_merchant_info();

        if ( empty( self::$merchant_info ) || empty( self::$merchant_info['do_recharges'] ) ) {
            return;
        }

        self::$main_gateway = $gw;
        self::$bisoo_rate = empty( self::$merchant_info['bisoo_rate'] ) ? 0 : (int)self::$merchant_info['bisoo_rate'];

        add_action( 'woocommerce_review_order_before_submit',  array( __CLASS__, 'show_bisoo_recharge_info_box' ) );
        add_action( 'woocommerce_pay_order_before_submit',  array( __CLASS__, 'show_bisoo_recharge_info_box_pay' ) );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'set_order_as_rechargeable' ) );

        add_action( 'woocommerce_thankyou', array( __CLASS__, 'show_bisoo_recharge_box' ), 1 );
    }


    /**
     *  Maybe show the Bisoo box in the checkout.
     */
    public static function show_bisoo_recharge_info_box() {

        // Use the cart total to get the price including discounts. There are also
        // shipping costs that we need to subtract.
        $total = WC()->cart->total - WC()->cart->get_shipping_total();

        self::_recharge_info_box( $total );
    }

    /**
     * Maybe show the Bisoo box in the order-pay page.
     */
    public static function show_bisoo_recharge_info_box_pay() {

        if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
            $order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
            $order_id = wc_get_order_id_by_order_key( $order_key );
            $order = wc_get_order( $order_id );
            $total = self::get_order_total( $order );

            self::_recharge_info_box( $total );
        }
    }

    /**
     * Show the Bisoo box to let the customer know that can receive a recharge.
     * Also specify the amount of the recharge calculated in the same day of the order.
     */
    private static function _recharge_info_box( $total ) {

        if ( get_woocommerce_currency() != 'EUR' || $total <= self::$minimum_order_total || empty( self::$bisoo_rate ) ) {
            return; // Sardex Bisoo works only with EUR, with a minimum amount and we need the rate
        }

        $gw = self::$main_gateway;
        $recharge_amount = wc_format_decimal( $total * self::$bisoo_rate / 100, 2 );

        if ( $recharge_amount > 0 ) {
            include self::$main_gateway->plugin_path . 'templates/bisoo-checkout.php';
        }
    }


    /**
     * Save an order meta to be able to make recharges after order will be completed.
     * Only new orders paid in EUR and with this plugin active can allow SardexPay Cashback recharges.
     *
     * Possibile values of the meta-> status are:
     * - empty meta: old orders can't get a recharge;
     * - "recharged": a recharge was already done;
     * - "unrechargeable": an order paid in Sardex/Bisoo can't receive a self-recharge OR currency is not EUR
     * - "rechargeable": order can be recharged.
     *
     * @param  int $order_id Order ID.
     */
    public static function set_order_as_rechargeable( $order_id, $payment_method = false ) {

        $order = wc_get_order( $order_id );
        $currency_code = $order->get_currency();

        // Allow only recharges with payments in EUR
        if ( empty( $currency_code ) || $currency_code != 'EUR' ) {
            WC_Gateway_Sardex_Helper::log( "SardexPay Cashback recharge skipped. Reason: currency must be EUR," . $currency_code . " used." );

            update_post_meta( $order_id, SARDEX_ORDER_META_RECHARGEABLE, array(
                'status' => SARDEX_ORDER_UNRECHARGEABLE_CUR
            ));

            return;
        }

        // Disallow recharge for SRD payments.
        $pm = $payment_method == false ? $order->get_payment_method() : $payment_method;
        if ( $pm == 'sardex' || $pm == 'sardex-credits') {
            WC_Gateway_Sardex_Helper::log( "SardexPay Cashback recharge skipped. Reason: order paid with Sardex or SardexPay Cashback." );

            update_post_meta( $order_id, SARDEX_ORDER_META_RECHARGEABLE, array(
                'status' => SARDEX_ORDER_UNRECHARGEABLE_SRD
            ));

            return;
        }

        update_post_meta( $order_id, SARDEX_ORDER_META_RECHARGEABLE, array(
            'status' => SARDEX_ORDER_RECHARGEABLE
        ));
    }


    /**
     * Same stuff of set_order_as_rechargeable but for the pay action,
     * used to keep things separate.
     * woocommerce_before_pay_action hook is fired BEFORE the method is updated,
     * so we need to pass it the set_order_as_rechargeable method.
     */
    public static function set_order_as_rechargeable_pay( $order ) {

        $payment_method = isset( $_POST['payment_method'] ) ? wc_clean( $_POST['payment_method'] ) : false;
        self::set_order_as_rechargeable( $order->get_id(), $payment_method );
    }


    /**
     * Returns the order total excluding shipping costs (if any)
     */
    private static function get_order_total( $order ) {

        return $order->get_total() - $order->get_total_shipping();
    }


    /**
     * Returns the correct recharge amount
     */
    private static function get_recharge_amount( $order ) {

        return wc_format_decimal( self::get_order_total( $order ) * self::$bisoo_rate / 100, 2 );
    }


    /**
     * Show the SardexPay Cashback input box, on which the customer can add is SardexPay Cashback card number,
     * if the order has been paid. This page can also be shown be clicking on the
     * link received in the email.
     * Be first sure that the order can be recharged.
     *
     * @param  int $order_id Order ID.
     */
    public static function show_bisoo_recharge_box( $order_id ) {

        $rechargeable = get_post_meta( $order_id, SARDEX_ORDER_META_RECHARGEABLE );

        if ( empty( $rechargeable[0] ) // old order
            || $rechargeable[0]['status'] == SARDEX_ORDER_UNRECHARGEABLE_CUR
            || $rechargeable[0]['status'] == SARDEX_ORDER_UNRECHARGEABLE_SRD
            || empty( self::$bisoo_rate ) // We need to know the current recharge rate
        ) {
            return; // do nothing.
        }

        $order = wc_get_order( $order_id );
        $order_paid = in_array( $order->get_status(), array( 'completed', 'processing' ) );
        $order_date = $order->get_date_created()->format( 'd/m/Y' );
        $img_app_cashback = self::$main_gateway->img['app_cashback'];
        $img_bisoo_logo = self::$main_gateway->img['bisoo_logo'];
        $recharge_amount = self::get_recharge_amount( $order );

        if ( $rechargeable[0]['status'] == SARDEX_ORDER_RECHARGED ) {
            $d = explode( "T", $rechargeable[0]['transactionDate'] );
            $tr_date = date( "d/m/Y", strtotime( $d[0] ) );

            $tpl = 'bisoo-orderreceived-recharged.php';
        }
        else { // status == SARDEX_ORDER_RECHARGEABLE
            if ( $order_paid ) {
                $tpl = 'bisoo-orderreceived-paid.php';

                $js_path = plugins_url( 'assets/js/card-validation.js', SARDEX_PLUGIN_FILE );
                wp_enqueue_script( 'sardex-for-woocommerce-card-validation', $js_path, array( 'jquery' ), '1.0' );
            }
            else {
                $tpl = 'bisoo-orderreceived-unpaid.php';
            }
        }

        include self::$main_gateway->plugin_path . 'templates/' . $tpl;
    }

    /**
     * Send the recharge request.
     */
    public static function do_bisoo_recharge() {

        if ( empty( $_POST ) || ! isset( $_POST['wc-gateway-sardex-bisoo-nonce'] )
            || ! wp_verify_nonce( $_POST['wc-gateway-sardex-bisoo-nonce'], 'sardex-bisoo4woocommerce' )   ) {
            die();
        }

        $apis = WC_Gateway_Sardex_Helper::get_api_endpoints();
        $merchant_auth64 = WC_Gateway_Sardex_Helper::get_merchant_auth();

        if ( empty( $apis['do_recharge'] ) || empty( $merchant_auth64 ) ) {
            wp_safe_redirect( $_POST['http_referer'] );
            die();
        }

        // Server side card check and formatting with spaces
        $bisoo_card = sanitize_text_field( $_POST['sardex_bisoo_card_number'] );
        $cc = str_replace( array('-', ' '), '', $bisoo_card );
        $cc_len = strlen( $cc );
        $cc_arr = str_split( $cc, 4 );
        $bisoo_card = implode( " ", $cc_arr );

        $order_id = (int) $_POST['order_id'];
        $order = wc_get_order( $order_id );

        $err = '';
        if ( empty( $order ) ) {
            $err = "Si è verificato un problema con l'identificazione dell'ordine";
        }
        elseif ( empty( $bisoo_card ) ) {
            $err = "È necessario inserire il numero di carta SardexPay Cashback per poter ricevere la ricarica!";
        }
        elseif ( $cc_len != 16 || strspn( $cc, '0123456789' ) != $cc_len ) { // card is not valid
            $err = "Si prega di ricontrollare il numero di carta SardexPay Cashback inserito.";
        }
        else {
            $order_amount = number_format( self::get_order_total( $order ), 2, '.', '' );

            $topupTransferType = WC_Gateway_Sardex_Helper::get_topup_transfer_type();
            $raw_response = wp_safe_remote_post( $apis['do_recharge'], array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Channel' => 'ecommerce',
                    'Authorization' => $merchant_auth64
                ),
                'body' => wp_json_encode( array(
                    "amount" => $order_amount,
                    "subject" => $bisoo_card,
                    "description" => "Ricarica da " . get_bloginfo( 'url' ),
                    "type" => $topupTransferType,
                    "currency" => "EUR",
                )),
                'timeout' => 70,
                'user-agent' => 'WooCommerce/' . WC()->version,
                'httpversion' => '1.1',
            ));


            if ( is_wp_error( $raw_response ) ) {
                $err = $raw_response->get_error_message();
            }
            else {

                $error_code = wp_remote_retrieve_response_code( $raw_response );

                if ( empty( $raw_response['response'] ) && empty( $error_code ) ) {
                    $err = SARDEX_UNKNOWN_ERROR;
                }
                else if ( $error_code != 201 ) {
                    switch ( (int)$error_code ) {
                        case 404:
                            $err = "Il numero della carta non è corretto";
                            break;
                        case 422:
                            $err = "Impossibile completare l'operazione";
                            break;
                        default:
                            $err = SARDEX_UNKNOWN_ERROR;
                    }
                }
                else { // 201 - OK
                    try {
                        $response = json_decode( $raw_response['body'] );

                        if ( ! empty( $response->transactionNumber ) ) {
                            // Set as recharged
                            update_post_meta( $order_id, SARDEX_ORDER_META_RECHARGEABLE, array(
                                'status' => SARDEX_ORDER_RECHARGED,
                                'transactionNumber' => $response->transactionNumber,
                                'transactionDate' => $response->date
                            ));

                            $recharge_amount = self::get_recharge_amount( $order );
                            if ( ! empty( $recharge_amount ) ) {
                                $note = "La ricarica SardexPay Cashback di ". $recharge_amount ." SRD è stata effettuata sul totale di ". $order_amount ." [TX ID: ".$response->transactionNumber."]";
                                $order->add_order_note( $note );
                                WC_Gateway_Sardex_Helper::log( $note );
                            }
                        }
                        else {
                            /**
                             * UNHANDLED ERROR
                             * @todo Non c'è il transactionNumber?! Non so se la transazione sia effettivamente avvenuta oppure no
                             * Se no, mostrare errore all'utente?
                             */
                        }
                    }
                    catch ( Exception $e ) {
                        $err = $e->getMessage();
                    }
                }
            }
        }

        if ( ! empty( $err ) ) {
            session_start();
            set_transient( 'transient-sardex-bisoo-recharge-error', $err, 60*60*12 );
        }

        wp_safe_redirect( $_POST['http_referer'] );
        die();
    }

}

endif; // end of class_exists( 'WC_Gateway_Sardex_Bisoo' )