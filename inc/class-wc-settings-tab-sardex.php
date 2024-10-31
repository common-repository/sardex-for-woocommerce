<?php

/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Gateway_Sardex' ) ) :

    class WC_Settings_Tab_Sardex {

        /**
         * Bootstraps the class and hooks required actions & filters.
         */
        public static function init() {
            add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
            add_action( 'woocommerce_settings_settings_tab_sardex', __CLASS__ . '::output' );
            add_action( 'woocommerce_update_options_settings_tab_sardex', __CLASS__ . '::update_settings' );
        }

        /**
         * Add a new settings tab to the WooCommerce settings tabs array.
         *
         * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding this tab.
         * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including this tab.
         */
        public static function add_settings_tab( $settings_tabs ) {
            $settings_tabs['settings_tab_sardex'] = 'SardexPay for WooCommerce';

            return $settings_tabs;
        }

        /**
         * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
         *
         * @uses woocommerce_update_options()
         * @uses self::get_settings()
         */
        public static function update_settings() {
            woocommerce_update_options( self::get_settings() );

            return WC_Gateway_Sardex_Helper::save_merchant_profile();
        }

        /**
         * Output the settings and add some custom JS.
         */
        public static function output() {

            // self::maybe_show_admin_errors();
            echo '<div class="sardex-admin-main">';
            
            $sardexPayLogo = trailingslashit( plugins_url( '', SARDEX_PLUGIN_FILE ) ) . 'assets/img/logo-sardexpay.svg';
            echo '<img src="' . $sardexPayLogo . '" alt="SardexPay" width="200" />';

            WC_Admin_Settings::output_fields( self::get_settings() );

            echo "</div>";
        }

        /**
         * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
         *
         * @return array Array of settings for @see woocommerce_admin_fields() function.
         */
        public static function get_settings() {
            $plugin_url = trailingslashit( plugins_url( '', SARDEX_PLUGIN_FILE ) );
            $b2b_logo = $plugin_url . 'assets/img/sardex-logo-big.png';
            $bisoo_logo = $plugin_url . 'assets/img/sardexpay-cashback-logo-big.png';

            $merchant_info = WC_Gateway_Sardex_Helper::get_merchant_info();
            $circuit_info = WC_Gateway_Sardex_Helper::get_merchant_circuit();
            $login_info = array();
            if ($circuit_info) {
                $login_info = array(
                    'title' => "Il tuo circuito",
                    'type' => 'title',
                    'desc' => "Il tuo utente fa parte del circuito: <br> "
                              . "<img src='". $circuit_info["logo"] . "' 
                                      alt='" . $circuit_info["name"] . "' 
                                      height='35'
                                      style='vertical-align: middle; margin: 10px 10px 10px 0'>"
                              . "<strong>" . $circuit_info['name'] . "</strong>",
                    'id' => 'wc_sardex_login_info',
                );
            }

            $bisoo_status = WC_Gateway_Sardex_Helper::get_bisoo_status();
            $bisoo_enabled = !empty($merchant_info) && !$merchant_info['mixed_credits'] && ($merchant_info['b2c_enabled'] || $merchant_info['bisoo_enabled']);
            $get_credits = !empty($merchant_info) && $bisoo_enabled && !$merchant_info['recharges_only'];
            $do_recharges = !empty($merchant_info) && $bisoo_enabled && !$merchant_info['credits_only'];

            $bisoo_description = '<img src="' . $bisoo_logo . '" width="100%" /><br>' .
                                 'Con SardexPay per Woocommerce puoi effettuare ricariche e accettare pagamenti in crediti direttamente sul tuo negozio online.
                                  Per usufruire del servizio devi essere iscritto al Circuito SardexPay e partecipare al programma SardexPay Cashback.<br>';
            
            if (!$bisoo_enabled) {
                $bisoo_description .= 'Non sei ancora iscritto? <strong><a href="https://www.sardexpay.net/" target="_blank">Partecipa!</a></strong>';
            }

            if ($bisoo_status['err_msg']) {
                $bisoo_description .= '<p class="sardex-warning">' . $bisoo_status['err_msg'] . '</p>';
            }

            $settings = array(
                // ------------------------------------------------- Main options
                array(
                    'title' => "Login",
                    'type' => 'title',
                    'desc' => 'Inserisci le credenziali per abilitare il tuo negozio online',
                    'id' => 'wc_sardex_login_section',
                ),
                array(
                    'title' => 'Sandbox',
                    'type' => 'checkbox',
                    'label' => 'Abilita la modalità Sandbox quando selezionato',
                    'desc' => 'Se selezionato, attiva la modalità Sandbox',
                    'default' => 'no',
                    'id' => 'wc_sardex_sandbox_enabled',
                ),
                array(
                    'title' => 'Username',
                    'type' => 'text',
                    'desc' => "Inserisci il nome utente",
                    'default' => '',
                    'id' => 'wc_sardex_username',
                ),
                array(
                    'title' => 'Password',
                    'type' => 'password',
                    'desc' => "Inserire la password",
                    'default' => '',
                    'id' => 'wc_sardex_password',
                ),

                array(
                    'type' => 'sectionend',
                    'id' => 'wc_sardex_login_section',
                ),
                $login_info,
                // ------------------------------------------------- B2B options
                array(
                    'title' => "Pagamenti in crediti",
                    'type' => 'title',
                    'desc' => '<img src="' . $b2b_logo . '" width="100%"><br>' . 
                              'Abilita il tuo ecommerce alla ricezione di pagamenti in crediti.<br> Per usufruire del servizio devi essere iscritto al Circuito SardexPay o ad un Circuito di Credito Commerciale partner (Venetex, Linx, Abrex, Felix o Mountex).',
                    'id' => 'wc_sardex_b2b_section',
                ),
                array(
                    'title' => 'Abilita i pagamenti B2B',
                    'type' => 'checkbox',
                    'label' => 'Abilita i pagamenti in Sardex B2B quando selezionato.',
                    'desc' => 'Se selezionato, <strong>attiva i pagamenti in crediti per gli iscritti a SardexPay o ad un altro Circuito di Credito Commerciale partner</strong>. Deselezionare per disabilitare.',
                    'default' => 'no',
                    'id' => 'wc_sardex_b2b_payment_enabled',
                    'custom_attributes' => !empty($merchant_info) ? '' : array('disabled' => 'disabled')
                ),
                array(
                    'type' => 'sectionend',
                    'id' => 'wc_sardex_b2b_section',
                )
            );

            if (isset($merchant_info['bisoo_available']) && $merchant_info['bisoo_available']) {
                // ------------------------------------------------- SardexPay Cashback options
                $settings[] = array(
                    'title' => "SardexPay Cashback",
                    'type' => 'title',
                    'desc' => $bisoo_description,
                    'id' => 'wc_sardex_bisoo_section'
                );
                $settings[] = array(
                    'title' => 'Abilita i pagamenti in SardexPay Cashback',
                    'type' => 'checkbox',
                    'label' => 'Abilita i pagamenti in SardexPay Cashback quando selezionato.',
                    'desc' => 'Se selezionato, <strong>attiva i pagamenti in crediti sardex per gli iscritti al programma SardexPay Cashback</strong>. Disponibile compatibilmente con le modalità di adesione al progamma. Deselezionare per disabilitare.',
                    'default' => 'no',
                    'id' => 'wc_sardex_bisoo_payment_enabled',
                    'custom_attributes' => !empty($merchant_info) && $get_credits? '' : array('disabled' => 'disabled')
                );
                $settings[] = array(
                    'title' => 'Abilita le ricariche SardexPay Cashback',
                    'type' => 'checkbox',
                    'label' => 'Abilita le ricariche SardexPay Cashback quando selezionato.',
                    'desc' => 'Se selezionato, <strong>attiva le ricariche SardexPay Cashback</strong> verso gli acquirenti che effettuano acquisti in euro. Disponibile compatibilmente con le modalità di adesione al progamma. Deselezionare per disabilitare.',
                    'default' => 'no',
                    'id' => 'wc_sardex_bisoo_recharge_enabled',
                    'custom_attributes' => !empty($merchant_info) && $do_recharges ? '' : array('disabled' => 'disabled')
                );
                $settings[] = array(
                    'type' => 'sectionend',
                    'id' => 'wc_sardex_login_section',
                );
            }

            return apply_filters( 'sardex_settings_tab', $settings );
        }
    }    

endif; // class exists


WC_Settings_Tab_Sardex::init();