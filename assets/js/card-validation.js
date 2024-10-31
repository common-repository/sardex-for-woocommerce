/**
 * SardexPay for WooCommerce
 *
 * Copyright: © 2019-2024 Sardex S.p.A. (https://www.sardexpay.net/ - info@sardexpay.net)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

document.getElementById("sardex-bisoo-card-number").oninput = function () {
  var v = this.value.replace(/\s+/g, "").replace(/[^0-9]/gi, "");
  var matches = v.match(/\d{4,16}/g);
  var match = (matches && matches[0]) || "";
  var parts = [];
  for (var i = 0, len = match.length; i < len; i += 4) {
    parts.push(match.substring(i, i + 4));
  }
  this.value = parts.length ? parts.join(" ") : this.value;
};
