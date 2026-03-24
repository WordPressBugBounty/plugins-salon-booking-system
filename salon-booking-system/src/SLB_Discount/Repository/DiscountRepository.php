<?php

class SLB_Discount_Repository_DiscountRepository extends SLN_Repository_AbstractWrapperRepository
{
    private $discounts;

    public function getWrapperClass()
    {
        return SLB_Discount_Wrapper_Discount::_CLASS;
    }

    /**
     * @param array $criteria
     *
     * @return SLB_Discount_Wrapper_Discount[]
     */
    public function getAll($criteria = array())
    {
        if ( ! isset($this->discounts)) {
            $this->discounts = $this->get($criteria);
        }

        return $this->discounts;
    }

    protected function processCriteria($criteria)
    {
        $criteria = apply_filters('sln.repository.discount.processCriteria', $criteria);

        // Always default to published discounts only.
        // Without this, WP_Query can include drafts when is_admin() returns true
        // (e.g. during AJAX requests), causing draft discounts to leak into
        // the My Account tab and other customer-facing queries.
        if ( ! isset( $criteria['post_status'] ) ) {
            $criteria['post_status'] = 'publish';
        }

        return parent::processCriteria($criteria);
    }
}