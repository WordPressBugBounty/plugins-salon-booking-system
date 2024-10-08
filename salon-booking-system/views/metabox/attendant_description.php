<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var stdClass $post
 */
?>
<div class="<?php echo in_array(SLN_Plugin::USER_ROLE_WORKER,  wp_get_current_user()->roles) ? 'sln-disabled' : '' ?>">
<label class="screen-reader-text" for="excerpt">
    <?php esc_html_e('Assistant Description', 'salon-booking-system') ?>
</label>
<textarea rows="1" cols="40" name="excerpt"
          id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped ?></textarea>
<p><?php esc_html_e('A very short description of this assistant. It is optional', 'salon-booking-system'); ?></p>
</div>