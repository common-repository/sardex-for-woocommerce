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
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
    <title><?php echo get_bloginfo( 'name', 'display' ); ?></title>
</head>
<body style="font-family: sans-serif;">
    <div class="sardex-mail" style="width: 100%; max-width: 600px; margin: 0 auto; border-radius: 20px; background: white; padding: 20px 0;">
        <div class="header" style="padding: 20px; box-sizing: border-box; border-bottom: 1px solid #dddddd;">
            <img src="<?php echo $email_contents['img_header']; ?>" width="230" />
        </div>
        <div class="body" style="padding: 20px; box-sizing: border-box;">
            <div class="subject" style="font-family: sans-serif; font-size: 20px; color: #19344b; font-weight: 600;">
                <?php echo $email_contents['subject']; ?>
                <div class="decoration" style="width: 50px; height: 4px; background: #e72d7e; margin: 15px 0 20px;"></div>
            </div>
            <div class="salutation" style="font-weight: 600; color: #19344b; margin: 5px 0;">
            <?php echo $email_contents['salutation']; ?>
            </div>
            <div class="text" style="font-family: sans-serif; color: #3d3d3d;">
                <p><?php echo $email_contents['message']; ?></p>
            </div>
            <div class="text" style="color: #3d3d3d;">
                A presto!<br />
                Il team <b><font color="#19344b">SardexPay</font></b>
            </div>
        </div>
        <div class="footer" style="font-family: sans-serif; padding: 20px; box-sizing: border-box; border-top: 1px solid #dddddd; color: #19344b;">
            <strong>Sardex SpA</strong><br />
            <small>
                Per informazioni:<br />
                070 332 4652<br />
                <a href="mailto:cashback@sardexpay.net">cashback@sardexpay.net<br /></a> Seguici
                su <a href="https://www.facebook.com/sardexpaycashback/">Facebook</a> e
                <a href="https://www.instagram.com/sardexpaycashback/">Instagram</a>!<br />
            </small>
        </div>
    </div>
</body>
</html>