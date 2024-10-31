<?php

/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * This template will be included in the order received page to show the SardexPay Cashback infobox when the recharge is done.
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="sardex-bisoo-wrapper-order-received" class="sardex-bisoo-wrapper sardex-bisoo-order-completed-wrapper sardex-bisoo-order-recharged">
    <div id="col-1" class="sardex-bisoo-cols">
        <div class="sardex-bisoo-cols-inner">
            <p class="sardex-bisoo-card"><img src="<?php echo $img_app_cashback; ?>" id="bisoo-card" /></p>
            <p class="sardex-bisoo-blue-medium">Importo ricarica:</p>
            <p class="sardex-bisoo-blue-big"><?php echo $recharge_amount; ?> SRD</p>
            <p class="sardex-bisoo-blue-medium">Accreditato il: <?php echo $tr_date; ?></p>
        </div>
    </div>
    <div id="col-2" class="sardex-bisoo-cols">
        <div class="sardex-bisoo-cols-inner">
            <p class="sardex-bisoo-logo-wrapper">
                <img src="<?php echo $img_bisoo_logo; ?>" id="sardex-bisoo-logo" width="250px" />
            </p>
            <p class="sardex-bisoo-blue-big">Buone notizie!</p>
            <p class="sardex-bisoo-thanks-p">La tua ricarica è stata effettuata con successo!<br>Verifica il tuo saldo crediti sul tuo account SardexPay Cashback</p>
            <p class="sardex-bisoo-order-reference">
                <span class="first">Rif. ordine n°: <?php echo $order_id; ?></span>
                <span class="second">Eseguito in data: <?php echo $order_date; ?></span>
            </p>
        </div>
    </div>
</div>