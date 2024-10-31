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
 * Register the action for the scheduled event.
 */
add_action( SARDEX_SCHED_EVENT, array( 'WC_Gateway_Sardex_Helper', 'save_merchant_profile' ) );


if ( ! class_exists( 'WC_Gateway_Sardex_Helper' ) ) :

class WC_Gateway_Sardex_Helper {

    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    public static $log = false;

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'. Possible values:
     *                      emergency|alert|critical|error|warning|notice|info|debug.
     */
    public static function log( $message, $arr = array(), $level = 'info' ) {

        if ( empty( self::$log ) ) {
            self::$log = wc_get_logger();
        }

        $message.= self::var_export( $arr );

        self::$log->log( $level, $message, array( 'source' => 'sardex' ) );

    }


    /**
     * Return the assets folder path for the plugin
     *
     * @return string
     */
    public static function get_asset_path() {
        $plugin_url = trailingslashit( plugins_url( '', SARDEX_PLUGIN_FILE ) );

        return $plugin_url . 'assets/img/';
    }

   /**
    * This prevent to change the floating point numbers precisions with var_export
    * @see also http://stackoverflow.com/a/32149358/1992799
    *
    * @param $expression mixed Same as var_export
    * @return mixed
    */
    private static function var_export( $expression ) {

        if ( empty( $expression ) ) {
            return '';
        }

        // Store the current precision
        $ini_value = ini_get( 'serialize_precision' );

        // Set the new precision and export the variable
        ini_set( 'serialize_precision', 2 );
        $value = var_export( $expression, TRUE );

        // Restore the previous value
        ini_set( 'serialize_precision', $ini_value );

        return ' ' . $value;
    }


    /**
     * Show an error message in the admin.
     *
     * @param string $error_message The message to be shown.
     */
    public static function show_admin_error( $error_message = '' ) {
        if ( ! is_admin() || empty( $error_message ) ) {
            return;
        }

        ?><div id="message" class="error">
            <p><strong><?php echo $error_message; ?></strong></p>
        </div><?php
    }

    /**
     * Store the merchant profile informations. This must be executed every time
     * the merchant saves the plugin page (wp-admin/admin.php?page=wc-settings&tab=checkout&section=sardex)
     * and each time the scheduled event of this plugin is executed through WP-cron.
     *
     * Because the scheduled event must be outside plugins_loaded, we do not have access to the
     * WC_Gateway_Sardex instance and for that reason this method is here.
     * @todo Maybe handle this in a different way and remove SARDEX_API_OPTION
     *
     * @return boolean|WP_Error
     */
    public static function save_merchant_profile() {
        $raw_response = self::fetch_merchant_info();

        if ( !$raw_response || is_wp_error( $raw_response ) || empty( $raw_response['body'] ) ) {
            self::log( "Empty get_profile response", array(), "error" );
            self::show_admin_error( SARDEX_UNKNOWN_ERROR );

            return new WP_Error( 'sardex-api', 'Empty get_profile Response' );
        }

        $response = json_decode( $raw_response['body'] );

        if ( ! empty( $raw_response['response']['code'] ) && $raw_response['response']['code'] == 200 && ! empty( $response->customValues ) ) {
            $merchant_info = array(
                'display_name' => $response->display,
                'group' => false,
                'circuit' => false,
                'bisoo_available' => false,
                'bisoo_enabled' => false,
                'bisoo_rate' => 0,
                'b2c_enabled' => false,
                'bisoo_credit_acceptance' => 0
            );

            $merchant_info['circuit'] = self::get_merchant_circuit();
            $merchant_info['group'] = self::get_merchant_group();

            $merchant_info['bisoo_available'] = self::is_bisoo_available($merchant_info['circuit']);

            foreach ( $response->customValues as $k => $v ) {
                if ( $v->field->internalName == 'doRecharge' && $v->booleanValue == true ) {
                    $merchant_info['bisoo_enabled'] = true;
                }
                elseif ( $v->field->internalName == 'bisooRate' ) {
                    $merchant_info['bisoo_rate'] = (int)$v->integerValue;
                }
                elseif ( $v->field->internalName == 'AccettaCreditiB2C' && $v->booleanValue == true ) {
                    $merchant_info['b2c_enabled'] = true;
                }
                elseif ( $v->field->internalName == 'BisooCreditAcceptance' ) {
                    $merchant_info['bisoo_credit_acceptance'] = (int)$v->integerValue;
                }
            }

            $merchant_info['get_credits']    = $merchant_info['b2c_enabled']   && $merchant_info['bisoo_credit_acceptance'] == 100;
            $merchant_info['mixed_credits']  = $merchant_info['b2c_enabled']   && $merchant_info['bisoo_credit_acceptance'] < 100;
            $merchant_info['do_recharges']   = $merchant_info['bisoo_enabled'] && $merchant_info['bisoo_rate'] > 0;
            $merchant_info['recharges_only'] = !$merchant_info['b2c_enabled']  && $merchant_info['do_recharges'];
            $merchant_info['credits_only']   = !$merchant_info['do_recharges'] && $merchant_info['get_credits'];

            // Update this values only if there is a response from the API
            update_option( SARDEX_MERCHANT_INFO_META, $merchant_info );

            self::log( ">> Merchant profile: ", $merchant_info );

            return true;
        }

        // There is an error in the response
        delete_option( SARDEX_MERCHANT_INFO_META );

        $error_message = SARDEX_UNKNOWN_ERROR;
        if ( ! empty( $raw_response['response']['code'] ) ) {
            if ( ! empty( $response->code ) && $response->code == "login" ) {
                $error_message = "Username o Password errati";
            }
            else if ( ! empty ( $response->code ) && $response->code == "inaccessibleChannel" ) {
                $error_message = "Impossibile effettuare l'accesso al servizio ecommerce.";
            }
            else if ( ! empty( $response->userStatus ) && $response->userStatus == 'blocked' ) {
                $error_message = "Il tuo account è bloccato";
            }
            else if ( ! empty( $response->passwordStatus ) && $response->passwordStatus == 'temporarilyBlocked' ) {
                $error_message = "Il tuo account è stato momentaneamente bloccato perché hai sbagliato la password per tre volte consecutive. Riprova tra 15 minuti o richiedi una nuova password se l'hai dimenticata.";
            }
        }

        $err = "Errore nel salvataggio delle opzioni: " . $error_message;

        self::log( $err, array(), 'error' );
        self::show_admin_error( $err );

        return false;
    }

    /**
     * Store the merchant profile informations.
     *
     * @return array|bool|WP_Error
     */
    private static function fetch_merchant_info() {
        $apis_endpoint = self::get_api_endpoints();
        $merchant_auth64 = self::get_merchant_auth();

        if ( empty( $apis_endpoint ) || empty( $merchant_auth64 ) ) {
            return false;
        }

        return wp_safe_remote_get( $apis_endpoint['get_profile'], array(
            'method' => 'GET',
            'headers' => array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'Channel'       => 'ecommerce',
                'Authorization' => $merchant_auth64
            ),
            'timeout' => 70,
            'user-agent' => 'WooCommerce/' . WC()->version,
            'httpversion' => '1.1',
        ));
    }

    /**
     * Returns true if the given logged user could perform SardexPay Cashback transactions
     *
     * @return bool
     */
    public static function is_bisoo_available($userCircuit) {
        if (!!$userCircuit && ($userCircuit['name'] === 'SardexPay' || $userCircuit['name'] === 'Circuito Venetex')) {
            return true;
        }

      return false;
    }

    /**
     * Returns the merchant group name
     *
     * @return false|string|WP_Error
     */
    private static function get_merchant_group() {
        $raw_response = self::fetch_merchant_info();

        if ( !$raw_response || is_wp_error( $raw_response ) || empty( $raw_response['body'] ) ) {
            self::log( "Empty get_profile response getting group", array(), "error" );
            return new WP_Error( 'sardex-api', 'Empty get_profile Response' );
        }

        $response = json_decode( $raw_response['body'] );

        if ( ! empty($response->group) && $response->group->name) {
            return $response->group->name;
        }

        return false;
    }

    /**
     * Returns the merchant circuit info
     *
     * @return false|string[]
     */
    public static function get_merchant_circuit() {
        $logo_path = plugins_url( 'assets/img/', SARDEX_PLUGIN_FILE );

        $merchantGroup = self::get_merchant_group();

        if (is_wp_error($merchantGroup)) {
            return false;
        }

        $circuit = false;

        if (preg_match('/^(SRD)\..*$/', $merchantGroup) === 1) {
            $circuit = array(
                "name" => "SardexPay",
                "logo" => $logo_path . "logo-sardexpay.svg"
            );
        }

        if (preg_match('/^(VTX)\..*$/', $merchantGroup) === 1) {
            $circuit = array(
                "name" => "Circuito Venetex",
                "logo" => $logo_path . "logo-venetex.svg"
            );
        }

        if (preg_match('/^(ABR)\..*$/', $merchantGroup) === 1) {
            $circuit = array(
                "name" => "Circuito Abrex",
                "logo" => $logo_path . "logo-abrex.svg"
            );
        }

        if (preg_match('/^(LNX)\..*$/', $merchantGroup) === 1) {
            $circuit = array(
                "name" => "Circuito Linx",
                "logo" => $logo_path . "logo-linx.svg"
            );
        }

        if (preg_match('/^(MTX)\..*$/', $merchantGroup) === 1) {
            $circuit = array(
                "name" => "Circuito Mountex",
                "logo" => $logo_path . "logo-mountex.svg"
            );
        }

        if (preg_match('/^(FLX)\..*$/', $merchantGroup) === 1) {
            $circuit = array(
                "name" => "Circuito Felix",
                "logo" => $logo_path . "logo-felix.svg"
            );
        }

        return $circuit;
    }

    /**
     * Returns the transfer type to perform payments
     *
     * @return string
     */
    public static function get_user_transfer_type() {
        $merchantGroup = self::get_merchant_group();
        $isCircuitManager = preg_match('/^[A-Z]{3}\.(Gestore)$/', $merchantGroup) === 1;

        if (preg_match('/^(SRD)\..*$/', $merchantGroup) === 1) {
            if ($isCircuitManager) {
                return 'contoCC.accreditoGestore';
            }

            return 'contoCC.accredito';
        }

        if (preg_match('/^(VTX)\..*$/', $merchantGroup) === 1) {
            if ($isCircuitManager) {
                return 'contoCC.vtxaccreditogestore';
            }

            return 'contoCC.vtxaccredito';
        }

        if (preg_match('/^(ABR)\..*$/', $merchantGroup) === 1) {
            if ($isCircuitManager) {
                return 'contoCC.accreditoGestoreABR';
            }

            return 'contoCC.abraccredito';
        }

        if (preg_match('/^(LNX)\..*$/', $merchantGroup) === 1) {
            if ($isCircuitManager) {
                return 'contoCC.accreditoGestoreLNX';
            }

            return 'contoCC.lnxaccredito';
        }

        if (preg_match('/^(MTX)\..*$/', $merchantGroup) === 1) {
            if ($isCircuitManager) {
                return 'contoCC.accreditoGestoreMTX';
            }

            return 'contoCC.mtxaccredito';
        }

        if (preg_match('/^(FLX)\..*$/', $merchantGroup) === 1) {
            if ($isCircuitManager) {
                return 'contoCC.accreditoGestoreFLX';
            }

            return 'contoCC.flxaccredito';
        }

        return '';
    }

    /**
     * Returns the transfer type to perform topups
     *
     * @return string
     */
    public static function get_topup_transfer_type() {
        $merchantGroup = self::get_merchant_group();

        if (preg_match('/^(SRD)\..*$/', $merchantGroup) === 1) {
            return 'LocalSpending.AcquistoLocale';
        }

        if (preg_match('/^(VTX)\..*$/', $merchantGroup) === 1) {
            return 'LocalSpending.VTXRicaricaBisoo';
        }

        return '';
    }

    public static function get_merchant_info() {
        return get_option( SARDEX_MERCHANT_INFO_META, array() );
    }

    /**
     * Initialise merchant auth.
     */
    public static function get_merchant_auth() {
        $username = get_option( 'wc_sardex_username' );
        $password = get_option( 'wc_sardex_password' );

        if ( ! empty( $username ) && ! empty( $password ) ) {
            return 'Basic '.base64_encode( $username.':'.$password );
        }

        return false;
    }

    public static function get_api_endpoints() {
        if (get_option( 'wc_sardex_sandbox_enabled' ) === 'yes') {
            $api_url = 'https://cyclos-sandbox.sardlab.io';
            $pay_url = 'https://pay-sandbox.sardlab.io';
        } else {
            $api_url = 'https://adm.nosu.co';
            $pay_url = 'https://pay.nosu.co';
        }

        $api = array(
            'is_sandbox'     => get_option( 'wc_sardex_sandbox_enabled' ) === 'yes',
            'get_profile'    => $api_url . '/api/users/self', // the currently authenticated user
            'create_ticket'  => $api_url . '/api/tickets',
            'process_ticket' => $api_url . '/api/tickets/{TICKET_NUMBER}/process?orderId={ORDER_ID}',
            'do_recharge'    => $api_url . '/api/pos',
            'pay'            => $pay_url . '/pay/{TICKET_NUMBER}'
        );

        return $api;
    }

    public static function get_bisoo_status() {
        $bisoo_payment_enabled = get_option('wc_sardex_bisoo_payment_enabled');
        $bisoo_recharge_enabled = get_option('wc_sardex_bisoo_recharge_enabled');

        $err_msg = '';
        $err_end = "<br>Contatta il servizio clienti all'indirizzo email broker@sardexpay.net o al numero 070 3327433 (interno 2) per maggiori informazioni.";
        $merchant_info = WC_Gateway_Sardex_Helper::get_merchant_info();

        if ( empty( $merchant_info ) ) {
            $err_msg = "Impossibile effettuare il login. Devi essere iscritto al programma SardexPay Cashback per poter utilizzare il plugin." . $err_end;
        }
        elseif ( ! empty( $merchant_info['mixed_credits'] ) ) {
            $err_msg = "I pagamenti in crediti sardex sono disabilitati. Per poterli abilitare, la percentuale di accettazione deve essere del 100%." . $err_end;
        }
        elseif ( ! empty( $merchant_info['recharges_only'] ) ) {
            $err_msg = "Il servizio è attivo in sola ricarica. Per accettare crediti dai consumatori devi essere abilitato." . $err_end;
        }
        elseif ( ! empty( $merchant_info['credits_only'] ) ) {
            $err_msg = "Il servizio è attivo in sola accettazione. Per effettuare anche le ricariche devi essere abilitato." . $err_end;
        }
        elseif ( ! $bisoo_payment_enabled && ! $bisoo_recharge_enabled ) {
            // Merchant can receive payment and do recharges but they are disabled
            $err_msg = "Attenzione: non hai abilitato i pagamenti in crediti e le ricariche SardexPay Cashback.<br>In questo modo il servizio non sarà attivo nel tuo negozio online.";
        }
        elseif ( ! $bisoo_payment_enabled ) {
            // Merchant can receive payment but they are disabled
            $err_msg = "Attenzione: non hai abilitato i pagamenti in crediti SardexPay Cashback.<br>In questo modo i tuo clienti non potranno pagare in crediti nel tuo negozio online.";
        }
        elseif ( ! $bisoo_recharge_enabled ) {
            // Merchant can do recharges but they are disabled
            $err_msg = "Attenzione: non hai abilitato le ricariche SardexPay Cashback.<br>In questo modo i tuo clienti non potranno ricevere ricariche in crediti nel tuo negozio online.";
        }

        return array(
            'payment_enabled' => empty( $merchant_info['get_credits'] ) ? false : $bisoo_payment_enabled,
            'recharge_enabled' => empty( $merchant_info['do_recharges'] ) ? false : $bisoo_recharge_enabled,
            'err_msg' => $err_msg,
            'info' => $merchant_info
        );
    }

    public static function get_settings_tab_url() {
        return get_admin_url() . 'admin.php?page=wc-settings&tab=settings_tab_sardex';
    }


    public static function get_circuit_payment_method_details() {
        $circuit = 'srd';

        $assetFolder = self::get_asset_path();
        $merchantGroup = self::get_merchant_group();

        if (!is_wp_error($merchantGroup)) {
            $prefixGroup = substr($merchantGroup, 0, 3);

            if ($prefixGroup) {
                $circuit = strtolower($prefixGroup);
            }
        }

        $methodsInfo = array();
        switch ($circuit) {
            case 'srd':
            default:
                $methodsInfo['name'] = "SardexPay";
                $methodsInfo['icon'] = $assetFolder . "logo-sardexpay.svg";
                $methodsInfo['description'] = "Paga in crediti Sardex in tutta sicurezza";
                break;
            case 'abr':
                $methodsInfo['name'] = "Circuito Abrex";
                $methodsInfo['icon'] = $assetFolder . "logo-abrex.svg";
                $methodsInfo['description'] = "Paga in crediti Abrex in tutta sicurezza";
                break;
            case 'flx':
                $methodsInfo['name'] = "Circuito Felix";
                $methodsInfo['icon'] = $assetFolder . "logo-felix.svg";
                $methodsInfo['description'] = "Paga in crediti Felix in tutta sicurezza";
                break;
            case 'lnx':
                $methodsInfo['name'] = "Circuito Linx";
                $methodsInfo['icon'] = $assetFolder . "logo-linx.svg";
                $methodsInfo['description'] = "Paga in crediti Linx in tutta sicurezza";
                break;
            case 'mtx':
                $methodsInfo['name'] = "Mountex";
                $methodsInfo['icon'] = $assetFolder . "logo-mountex.svg";
                $methodsInfo['description'] = "Paga in crediti Mountex in tutta sicurezza";
                break;
            case 'vtx':
                $methodsInfo['name'] = "Circuito Venetex";
                $methodsInfo['icon'] = $assetFolder . "logo-venetex.svg";
                $methodsInfo['description'] = "Paga in crediti Venetex in tutta sicurezza";
                break;
        }

        return $methodsInfo;
    }
}

endif; // end of class_exists( 'WC_Gateway_Sardex_Helper' )
