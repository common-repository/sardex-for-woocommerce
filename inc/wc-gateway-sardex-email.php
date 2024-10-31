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

if ( ! class_exists( 'WC_Gateway_Sardex_Email' ) ) :

class WC_Gateway_Sardex_Email {

    public function __construct( $order_id, $status_transition_to, $order ) {

        $this->id = 'wc_sardex_email';

        if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
            return FALSE;
        }

        $this->order = $order;
        $this->order_id = $order_id;

        // Email heading and subject
        $this->status = $this->_get_status( $status_transition_to );
        $this->recipient = $this->order->get_billing_email();

        if ( empty( $this->status ) || empty( $this->recipient ) ) {
            return FALSE;
        }

        $this->placeholders = array(
			'{site_title}'   => $this->_get_blogname(),
			'{order_date}'   => wc_format_datetime( $this->order->get_date_created(), 'd/m/Y' ),
            '{order_number}' => $this->order->get_order_number()
        );

        $this->subject = $this->_str_ph_replace( '[{site_title}] Info ricarica SardexPay Cashback per l\'ordine {order_number} del {order_date}' );
    }


    /**
	 * Send the email if the order is rechergeable.
	 */
	public function send_email() {

        // Check if the order is rechergeable
        $rechargeable = get_post_meta( $this->order_id, SARDEX_ORDER_META_RECHARGEABLE );
        if ( empty( $rechargeable[0] ) || $rechargeable[0]['status'] != SARDEX_ORDER_RECHARGEABLE ) {
            return;
        }

        $post_meta = '_wc_sardex_bisoo_email_' . $this->status;

		// Send email only once per order completed or wait
		if ( get_post_meta( $this->order_id, $post_meta, true )  ) {
			return;
        }

        // Send the email
        $mess = $this->_get_message();
        if ( empty( $mess ) ) {
            return;
        }

		$res = wp_mail( $this->recipient, $this->subject, $mess, $this->_get_headers(), array() );

        if ( $res && ! empty( $this->order ) ) {
            // add order note and meta to prevent to send multiple emails for the same order status
            $this->order->add_order_note( 'Email SardexPay Cashback inviata ['.$this->status.']' );
            update_post_meta( $this->order_id, $post_meta, 1 );
        }
    }


    /**
	 * Get email content message
	 */
    private function _get_message() {

        if ( $this->status == 'completed' ) {
            $email_contents = array(
                'subject' => 'La tua ricarica ti aspetta!',
                'salutation' => '',
                'message' => '<a href="'.$this->_get_checkout_return_url().'" target="_blank" style="color:#0599a9;">Clicca qui</a> per ricevere la ricarica ottenuta grazie al tuo acquisto.'
            );
        }
        else if ( $this->status == 'wait' ) {
            $email_contents = array(
                'subject' => 'Grazie per l\'acquisto!',
                'salutation' => '',
                'message' => 'Il tuo ordine è in fase di elaborazione. Una volta ricevuto il pagamento ti invieremo via mail il link da cui potrai ricevere la tua ricarica.'
            );
        }
        else {
            return '';
        }

        $email_contents['img_header'] = plugins_url( 'assets/img/logo-sardexpay.svg', SARDEX_PLUGIN_FILE );

        ob_start();
        include plugin_dir_path( SARDEX_PLUGIN_FILE ) . 'templates/bisoo-email-tpl.php';
        return ob_get_clean();
    }


    /**
	 * Check the order status.
     *
     * @param string $st_to - The status transition -> to
	 *
	 * @return string|false
	 */
    private function _get_status( $st_to ) {

        if ( $st_to == 'completed' || $st_to == 'processing' ) {
            return 'completed';
        }
        else if ( $st_to == 'on-hold' ) {
            return 'wait';
        }

        return false;
    }

    /**
	 * Get WordPress blog name.
	 *
	 * @return string
	 */
	private function _get_blogname() {

		return wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
    }


    /**
	 * Get email headers.
	 *
	 * @return string
	 */
	private function _get_headers() {

		$header = "Content-Type: text/html \r\n";

		if ( $this->order && $this->order->get_billing_email() && ( $this->order->get_billing_first_name() || $this->order->get_billing_last_name() ) ) {
			$header .= 'Reply-to: ' . $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name() . ' <' . $this->order->get_billing_email() . ">\r\n";
		}

		return $header;
    }


    /**
	 * Replace placeholders in a string.
	 *
	 * @return string
	 */
    private function _str_ph_replace( $str ) {

        foreach( $this->placeholders as $key => $value ) {
            $str = str_replace( $key, $value, $str );
        }

        return $str;
    }


    /**
	 * Replace the url of the order received.
	 *
	 * @return string
	 */
    private function _get_checkout_return_url() {

        if ( $this->order ) {
			$return_url = $this->order->get_checkout_order_received_url();
        }
        else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
		}

		if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
			$return_url = str_replace( 'http:', 'https:', $return_url );
        }

        return $return_url;
    }
}

endif; // end of class_exists( 'WC_Gateway_Sardex_Email' )