<?php

/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * This template will be included in the order received page to show the Bisoo infobox when the payment is completed.
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="sardex-bisoo-wrapper-order-received" class="sardex-bisoo-wrapper sardex-bisoo-order-completed-wrapper">
    <div id="col-1" class="sardex-bisoo-cols">
        <div class="sardex-bisoo-cols-inner">
            <p class="sardex-bisoo-card"><img src="<?php echo $img_app_cashback; ?>" id="bisoo-card" /></p>
            <p class="sardex-bisoo-blue-medium">Importo ricarica:</p>
            <p class="sardex-bisoo-blue-big"><?php echo $recharge_amount; ?> SRD*</p>
            <p class="sardex-bisoo-grey-italic-small">
                * Importo calcolato in base alla percentuale di ricarica applicata il <?php echo date_i18n( 'j/n/Y', time() ); ?>
            </p>
        </div>
    </div>
    <div id="col-2" class="sardex-bisoo-cols">
        <div class="sardex-bisoo-cols-inner">
            <p class="sardex-bisoo-logo-wrapper">
                <img src="<?php echo $img_bisoo_logo; ?>" id="sardex-bisoo-logo" width="250px" />
            </p>
            <p class="sardex-bisoo-blue-big">Richiedi qui la tua ricarica!</p>
            <p class="sardex-bisoo-thanks-p">Inserisci il numero della tua carta SardexPay Cashback e ottieni subito i crediti che potrai spendere presso le attività aderenti al programma </p>
            <p class="sardex-bisoo-recharge-form-wrapper">
                <span class="sardex-bisoo-blue-medium">Numero di carta</span><br>

                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                    <input type="text" name="sardex_bisoo_card_number" class="input-text" placeholder="1234 1234 1234 1234" id="sardex-bisoo-card-number" value="" />
                    <input type="hidden" name="action" value="do_bisoo_recharge">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="http_referer" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <?php wp_nonce_field( 'sardex-bisoo4woocommerce', 'wc-gateway-sardex-bisoo-nonce' ); ?>
                    <input type="submit" id="sardex-bisoo-get-recharge" value="Conferma">
                </form>
            </p>
            <p class="sardex-bisoo-order-reference">
                <span class="first">Rif. ordine n°: <?php echo $order_id; ?></span>
                <span class="second">Eseguito in data: <?php echo $order_date; ?></span>
            </p>
        </div>
    </div>
</div>

<?php
$err_message = get_transient( 'transient-sardex-bisoo-recharge-error' );

if ( false !== $err_message ) : ?>
    <div id="sardex-bisoo-recharge-error-message"><strong>Errore: </strong><?php echo $err_message; ?></div>
    <?php delete_transient( 'transient-sardex-bisoo-recharge-error' ); ?>
<?php endif; ?>