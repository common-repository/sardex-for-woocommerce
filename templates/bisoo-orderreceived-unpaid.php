<?php

/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * This template will be included in the order received page to show the SardexPay Cashback infobox when the payment is NOT completed.
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="sardex-bisoo-wrapper-order-received" class="sardex-bisoo-wrapper col2-set">
    <div class="col-1">
        <p class="sardex-bisoo-card"><img src="<?php echo $img_app_cashback; ?>" id="bisoo-card" /></p>
    </div>
    <div class="col-2">
        <p class="sardex-bisoo-logo-wrapper">
            <img src="<?php echo $img_bisoo_logo; ?>" id="sardex-bisoo-logo" width="250px" />
        </p>
        <p class="sardex-bisoo-blue-big">Grazie per l'acquisto!</p>
        <p class="sardex-bisoo-thanks-p">Il tuo pagamento è in fase di elaborazione. Quando sarà completato ti manderemo via email il link per ottenere la tua ricarica.</p>
    </div>
</div>