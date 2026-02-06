<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_SaveNote extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        check_ajax_referer('ajax_post_validation', 'security');

        $note = '';
        if (isset($_POST['sln']['note'])) {
            $note = sanitize_textarea_field(wp_unslash($_POST['sln']['note']));
        }

        $builder = $this->plugin->getBookingBuilder();
        $builder->set('note', $note);
        $builder->save();

        return array(
            'success' => true,
            'note'    => $note,
            'clientId' => $builder->getClientId(),
        );
    }
}
