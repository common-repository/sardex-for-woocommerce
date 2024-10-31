/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

(function ($) {
  // Works on checkout and order_review
  var form = "form.checkout";
  if ($("form#order_review").length > 0) {
    form = "form#order_review";
  }

  /**
   * Prevent to show the SardexPay Cashback info box if the customer selects SardexPay Cashback to pay an order.
   * Show the info box if on load or on change
   */
  function enableDisableSardexInfoBox(val) {
    if (typeof val === "undefined") {
      return;
    }

    var $sardexw = $("#sardex-bisoo-wrapper-checkout");

    if (val == "sardex" || val == "sardex-credits") {
      $sardexw.hide();
    } else {
      if ($sardexw.css("display") == "none") {
        $sardexw.fadeIn();
      }
    }
  }

  // For order review must be also checked on load.
  var startChecked = $(form + ' input[name^="payment_method"]:checked').val();
  enableDisableSardexInfoBox(startChecked);

  // after woocommerce triggers updated_checkout
  // Note: this trigger comes after the load event and must be used because it overrides the element visibility (I don't know why but that's it).
  // For that reason the info box starts as hidden.
  $(document.body).on("updated_checkout", function () {
    var startChecked = $(form + ' input[name^="payment_method"]:checked').val();
    enableDisableSardexInfoBox(startChecked);
  });

  // when customer changes the selected payment method
  $(form).on("change", 'input[name^="payment_method"]', function () {
    enableDisableSardexInfoBox($(this).val());
  });
})(jQuery);
