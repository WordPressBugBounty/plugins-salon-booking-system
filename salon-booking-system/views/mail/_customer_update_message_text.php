<?php

    $updated_message = isset($updated_message) && !empty($updated_message) ? $updated_message : $plugin->getSettings()->get('booking_update_message');

    $updated_message = str_replace(
	array('[DATE]', '[TIME]', '[NAME]', '[SALON NAME]', '\\\\r\\\\n', '\\r\\n', '\\\\n', '\\n'), array(
	    $plugin->format()->date($booking->getDate()),
            $plugin->format()->time($booking->getTime()),
	    $booking->getDisplayName(),
	    $plugin->getSettings()->get('gen_name') ? $plugin->getSettings()->get('gen_name') : get_bloginfo('name'),
	    '<br/>',
	    '<br/>',
	    '<br/>',
	    '<br/>'
	),
	$updated_message
    );
?>
<p style="Margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:'open sans', 'helvetica neue', helvetica, arial, sans-serif;line-height:30px;color:#505050;font-size:20px"><?php echo esc_html($updated_message) ?></p>