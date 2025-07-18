<?php // algolplus
// phpcs:ignoreFile WordPress.Security.NonceVerification.Recommended

class SLN_Action_Ajax_RemoveNotice extends SLN_Action_Ajax_Abstract
{
	public function execute()
	{
        try {
            setcookie("remove_notice", "true", time() + (30 * 24 * 60 * 60), "/");
            return 'true';
        } catch(Exception $e) {
            $errors[] = $e->getMessage();
            return compact('errors');
        }

    }
}