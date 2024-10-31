<?php

/**
 * Plugin Name: SardexPay for WooCommerce
 * Plugin URI: http://wordpress.org/plugins/sardex-for-woocommerce/
 * Description: Accept payments in Sardex credits or reward your customers with SardexPay Cashback topups directly from your WooCommerce-powered online shop.
 * Version: 2.2.2
 * Author: Sardex SpA
 * Author URI: https://www.sardexpay.net/
 *
 * WC requires at least: 3.3
 * WC tested up to: 8.7
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 * Developed by: Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/
 */


// ------ Defines

define( "SARDEX_PLUGIN_FILE", __FILE__ ); // MUST BE HERE!

define( "SARDEX_MERCHANT_INFO_META", "_wc_gateway_sardex_merchant_info" );
define( "SARDEX_ORDER_META_RECHARGEABLE", "_rechargeable_by_sardex_bisoo" );
define( "SARDEX_API_OPTION", "wc_gateway_sardex_api" );
define( "SARDEX_SCHED_EVENT", "sardex_for_woocommerce_scheduled_event" );

define( "SARDEX_ORDER_RECHARGED", 0 );
define( "SARDEX_ORDER_RECHARGEABLE", 1 );
define( "SARDEX_ORDER_UNRECHARGEABLE_CUR", 2 );
define( "SARDEX_ORDER_UNRECHARGEABLE_SRD", 3 );

define( "SARDEX_UNKNOWN_ERROR", "Si è verificato un errore imprevisto. Riprova più tardi" );


// ------ Include extra classes
require_once 'inc/class-wc-settings-tab-sardex.php';
require_once 'inc/wc-gateway-sardex-helper.php';
require_once 'inc/wc-gateway-sardex-bisoo.php';
require_once 'inc/wc-gateway-sardex-email.php';


// ------ Add main plugin file
add_action( 'plugins_loaded', 'wc_gateway_sardex_on_plugins_loaded' );
function wc_gateway_sardex_on_plugins_loaded() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        $err = "Attenzione: è necessario installare/attivare WooCommerce per poter utilizzare il plugin SardexPay for WooCommerce.";
        WC_Gateway_Sardex_Helper::show_admin_error( $err );
        return;
    }

    // Main WooCommerce gateway code
    require_once 'inc/wc-gateway-sardex.php';

    require_once 'inc/wc-gateway-sardex-credits.php';
}


// ------ Add plugin action link
add_filter( 'plugin_action_links', 'wc_gateway_sardex_on_plugin_action_links', 10, 2 );
function wc_gateway_sardex_on_plugin_action_links( $links, $file ) {

	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}

    $action_links = array(
        'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=settings_tab_sardex' ) . '">Configurazione</a>',
    );

    return array_merge( $action_links, $links );
}


// ------ Show row meta on the plugin screen.
add_filter( 'plugin_row_meta', 'wc_gateway_sardex_on_plugin_row_meta', 10, 2 );
function wc_gateway_sardex_on_plugin_row_meta( $links, $file ) {

    if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
    }

    $row_meta = array(
        'docs'    => '<a href="' . esc_url( 'https://bisoo-documents.s3-eu-west-1.amazonaws.com/guide/Sardex_for_Woocommerce_Guida_al_funzionamento_del_plugin-v2.0.0.pdf' ) . '" target="_blank">Documentazione</a>',
        'support' => '<a href="' . esc_url( 'https://wordpress.org/support/plugin/sardex-for-woocommerce/' ) . '" target="_blank">Supporto</a>',
    );

    return array_merge( $links, $row_meta );
}


// ------ Add Sardex to the payment gateways.
add_filter( 'woocommerce_payment_gateways', 'wc_gateway_sardex_on_woocommerce_payment_gateways' );
function wc_gateway_sardex_on_woocommerce_payment_gateways( $methods ) {

    $merchant_info = WC_Gateway_Sardex_Helper::get_merchant_info();

    $bisoo_available = isset($merchant_info['bisoo_available']) && $merchant_info['bisoo_available'];

    if ( class_exists( 'WC_Gateway_Sardex' ) && $bisoo_available ) {
        $methods[] = 'WC_Gateway_Sardex';
    }

    if ( class_exists( 'WC_Gateway_Sardex_Credits' ) ) {
        $methods[] = 'WC_Gateway_Sardex_Credits';
    }

    return $methods;
}


// ------ Execute some stuff on init
add_action( 'init', 'wc_gateway_sardex_on_init', 999 );
function wc_gateway_sardex_on_init() {

    // Check the server response: this should be invoked by successWebhook
    if ( ! empty( $_POST['wc-api'] ) && $_POST['wc-api'] == 'WC_Gateway_Sardex' && class_exists( 'WC_Gateway_Sardex' ) ) {
        WC_Gateway_Sardex_Helper::log( "Checking Sardex server response: ", $_POST );
        $Sardex = new WC_Gateway_Sardex();
        $Sardex->payment_response();
    }

    // If recharges are enabled, add Bisoo init hooks
    $recharge_enabled = get_option('wc_sardex_bisoo_recharge_enabled');
    if ( isset( $recharge_enabled ) && "yes" == $recharge_enabled && class_exists( 'WC_Gateway_Sardex_Bisoo' ) ) {
        WC_Gateway_Sardex_Bisoo::setup();
    }
}


// ------ Register a custom menu page
function wc_gateway_sardex_register_my_custom_menu_page() {
    $sardexpayIcon = "data:image/svg+xml;base64,PHN2ZyBpZD0iTGl2ZWxsb18xIiBkYXRhLW5hbWU9IkxpdmVsbG8gMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgMjAwIDIwMCI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiNhN2FhYWQ7fTwvc3R5bGU+PC9kZWZzPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTE5NSw4My44N0gxNDguMzlMMTc4LDQwLjcyYy42MS0xLjk1LS4wOS00LTMtNEgxNDUuMjVhNi44OCw2Ljg4LDAsMCwwLTYuMzMsMy4xN2wtMzYuODcsNTVhOC40OSw4LjQ5LDAsMCwwLDAsOS44NGwzNi44Nyw1NWE2Ljg4LDYuODgsMCwwLDAsNi4zNCwzLjE2SDE3NWMzLDAsMy42OC0yLjEzLDMtNC4xbC0yNS44NC0zOS41NS0yLjMzLTMuNTNIMTk1YTUsNSwwLDAsMCw1LTVWODguODdBNSw1LDAsMCwwLDE5NSw4My44N1oiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik01LjgzLDExNS43NUg1Mi40NUwyMi44LDE1OC45MWMtLjYxLDIsLjA5LDQsMy4wNSw0SDU1LjU4YTYuODgsNi44OCwwLDAsMCw2LjM0LTMuMTZsMzYuODctNTVhOC40Nyw4LjQ3LDAsMCwwLDAtOS44NGwtMzYuODYtNTVhNi44OSw2Ljg5LDAsMCwwLTYuMzQtMy4xN0gyNS44NWMtMywwLTMuNjgsMi4xMy0zLDQuMTFMNDguNjcsODAuMzUsNTEsODMuODdINS44M2E1LDUsMCwwLDAtNSw1djIxLjg5QTUsNSwwLDAsMCw1LjgzLDExNS43NVoiLz48L3N2Zz4=";

    add_menu_page(
        __( 'SardexPay', 'sardex' ),
        'SardexPay',
        'manage_options',
        'admin.php?page=wc-settings&tab=settings_tab_sardex',
        '',
        $sardexpayIcon,
        56
    );
}
add_action( 'admin_menu', 'wc_gateway_sardex_register_my_custom_menu_page' );


// ------ Send Sardex emails
add_filter( 'woocommerce_order_status_changed', 'wc_gateway_sardex_on_woocommerce_order_status_changed', 10, 4 );
function wc_gateway_sardex_on_woocommerce_order_status_changed( $order_id, $status_transition_from, $status_transition_to, $order = false ) {

    $sardex_email = new WC_Gateway_Sardex_Email( $order_id, $status_transition_to, $order );
    if ( ! empty( $sardex_email ) ) {
        $sardex_email->send_email();
    }
}


// ------ When this plugin is activated...
register_activation_hook( __FILE__, 'wc_gateway_sardex_on_activation' );
function wc_gateway_sardex_on_activation() {

    // Sets a WP-cron event twice a day and creates the option for the merchant info.
    if ( ! wp_next_scheduled ( SARDEX_SCHED_EVENT ) ) {
        wp_schedule_event( time(), 'twicedaily', SARDEX_SCHED_EVENT );
    }

    add_option( SARDEX_MERCHANT_INFO_META, array() );
}


// ------ When this plugin is deactivated...
register_deactivation_hook( __FILE__, 'wc_gateway_sardex_on_deactivation' );
function wc_gateway_sardex_on_deactivation() {

    wp_clear_scheduled_hook( SARDEX_SCHED_EVENT );
}


// ------ When this plugin is uninstalled...
register_uninstall_hook( __FILE__, 'wc_gateway_sardex_on_uninstall' );
function wc_gateway_sardex_on_uninstall() {

    $options = array(
        SARDEX_MERCHANT_INFO_META,
        SARDEX_API_OPTION,
        "woocommerce_sardex_settings",
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }
}

