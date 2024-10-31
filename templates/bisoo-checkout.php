<?php

/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * This template will be included in the checkout to show the Bisoo infobox.
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="sardex-bisoo-wrapper-checkout" class="sardex-bisoo-wrapper col2-set" style="display: none">
    <div class="col-1">
        <p class="sardex-bisoo-logo-wrapper">
            <img src="<?php echo $gw->img['bisoo_logo']; ?>" id="sardex-bisoo-logo"/>
        </p>
        <p id="sardex-bisoo-row1">Sei iscritto al programma SardexPay Cashback?</p>
        <p class="sardex-bisoo-blue-medium">Completando quest’ordine puoi ottenere una ricarica di</p>
        <p class="sardex-bisoo-blue-big"><?php echo $recharge_amount; ?> SRD*</p>
        <p class="sardex-bisoo-grey-italic-small">
            <strong>* Importo calcolato in base alla percentuale di ricarica applicata il <?php echo date_i18n( 'j/n/Y', time() ); ?>.</strong><br>
            Potrebbe subire variazioni in caso di ordini effettuati con metodi di pagamento con lunghi tempi di lavorazione
            (es: bonifico bancario, contanti alla consegna, etc...).
            L'importo della ricarica viene ricalcolato e accreditato una volta ricevuto il pagamento.
        </p>
    </div>
    <div class="col-2">
        <p class="sardex-bisoo-card"><img src="<?php echo $gw->img['app_cashback']; ?>" id="bisoo-card" /></p>
        <p id="sardex-bisoo-row5">Con SardexPay Cashback, grazie ai tuoi acquisti di ogni giorno, ottieni crediti Sardex spendibili nelle imprese che fanno parte del programma.</p>
        <p id="sardex-bisoo-row6">Non sei ancora iscritto al programma?
            <br><a href="https://cashback.sardexpay.net" target="_blank">Scopri di più su cashback.sardexpay.net!</a>
        </p>
    </div>
</div>
