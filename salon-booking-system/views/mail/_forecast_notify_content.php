<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * Forecast slot notification email body.
 *
 * @var SLN_Plugin           $plugin
 * @var SLN_Wrapper_Customer $customer
 * @var SLN_Wrapper_Service  $service
 * @var array                $suggestions
 */

$customer_name = $customer->getName() ?: $customer->get( 'display_name' );
$service_name  = $service->getName();
$optout_url    = SLN_Action_ForecastNotify::getOptoutUrl( $customer );
?>
<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#505050;font-size:20px">
    <?php
    if ( $customer_name ) {
        printf(
            /* translators: %s: customer first name */
            esc_html__( 'Hi %s,', 'salon-booking-system' ),
            esc_html( $customer_name )
        );
    } else {
        esc_html_e( 'Hi there,', 'salon-booking-system' );
    }
    ?>
</p>

<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:28px;color:#505050;font-size:18px;padding-top:15px">
    <?php
    printf(
        /* translators: %s: service name */
        esc_html__( 'Based on your booking habits, you\'re due for your next %s. Here are some available times:', 'salon-booking-system' ),
        '<strong>' . esc_html( $service_name ) . '</strong>'
    );
    ?>
</p>

<table cellpadding="0" cellspacing="0" width="100%" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;margin-top:20px">
    <?php foreach ( $suggestions as $slot ) :
        $dt           = new SLN_DateTime( $slot['date'] . ' ' . $slot['time'] );
        $display_date = esc_html( $plugin->format()->date( $dt ) );
        $display_time = esc_html( $plugin->format()->time( $dt ) );
        $slot_label   = esc_html( $slot['label'] );
        $book_url     = esc_url( SLN_Action_ForecastNotify::buildSlotDeepLink( $plugin, $slot ) );
        ?>
    <tr>
        <td align="left" style="padding:0 0 12px 0;Margin:0">
            <table cellpadding="0" cellspacing="0" width="100%" bgcolor="#F2F6FD" role="presentation" style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:separate;border-spacing:0px;background-color:#f2f6fd;border-radius:10px">
                <tr>
                    <td align="left" style="padding:18px 20px;Margin:0">
                        <p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lato, 'helvetica neue', helvetica, arial, sans-serif;line-height:22px;color:#0978bd;font-size:14px;font-weight:700;text-transform:uppercase">
                            <?php echo $slot_label ?>
                        </p>
                        <p style="Margin:8px 0 0 0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'source sans pro', 'helvetica neue', helvetica, arial, sans-serif;line-height:28px;color:#333333;font-size:20px">
                            <strong><?php echo $display_date ?> &middot; <?php echo $display_time ?></strong>
                        </p>
                        <p style="Margin:14px 0 0 0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:22px;font-size:16px">
                            <span class="es-button-border" style="border-style:solid;border-color:#1e396c;background:#1e396c;border-width:2px;display:inline-block;border-radius:5px;width:auto;mso-border-alt:10px">
                                <a href="<?php echo $book_url ?>" target="_blank" class="es-button" style="mso-style-priority:100 !important;text-decoration:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;color:#FFFFFF;font-size:16px;padding:12px 24px;display:inline-block;background:#1e396c;border-radius:5px;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;font-weight:700;line-height:22px;text-align:center;border-color:#1e396c">
                                    <?php esc_html_e( 'Book this slot', 'salon-booking-system' ) ?> &rarr;
                                </a>
                            </span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php endforeach ?>
</table>

<p style="Margin:24px 0 0 0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:22px;color:#888888;font-size:14px">
    <?php esc_html_e( 'Don\'t want these reminders?', 'salon-booking-system' ); ?>
    <a href="<?php echo esc_url( $optout_url ) ?>" style="color:#0978bd;text-decoration:underline">
        <?php esc_html_e( 'Unsubscribe', 'salon-booking-system' ); ?>
    </a>
</p>
