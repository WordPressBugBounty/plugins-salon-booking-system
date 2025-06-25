<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLB_Discount_Action_Ajax_ApplyDiscountIdOnStart extends SLN_Action_Ajax_Abstract{

    protected $date;
    protected $time;
    protected $errors = array();

    public function execute(){
        $discount = sanitize_text_field(wp_unslash($_POST['discount_id']));
        $discount = $this->plugin->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT)->create($discount);
        $bb = $this->plugin->getBookingBuilder();
        if(!empty($discount->getId())){
            $errors = $discount->validateDiscount((new DateTime())->getTimestamp());
            if(empty($errors)){
                $bb->set('discount', array('id' => $discount->getId(), 'amount' => 0));
                $bb->save();
                return array('success' => 1, $bb->getData());
            }else{
                return array('error' => $errors[0]);
            }
        }else{
            return array('error' => __('Discount id is not valid', 'salon-booking-system'));
        }
    }
}