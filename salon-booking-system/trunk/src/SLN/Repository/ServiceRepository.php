<?php
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query

class SLN_Repository_ServiceRepository extends SLN_Repository_AbstractWrapperRepository
{
    const SERVICE_ORDER = '_sln_service_order';

    private $services;

    public function getWrapperClass()
    {
        return SLN_Wrapper_Service::_CLASS;
    }

    protected function processCriteria($criteria)
    {
        if (isset($criteria['@sort'])) {
            $criteria['@wp_query'] = array(
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key'     => self::SERVICE_ORDER,
                        'compare' => 'EXISTS',
                    ),
                    array(
                        'key'     => self::SERVICE_ORDER,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
                'orderby'    => self::SERVICE_ORDER,
                'order'      => 'ASC',
            );
            unset($criteria['@sort']);
        }

        $criteria = apply_filters('sln.repository.service.processCriteria', $criteria);

        return parent::processCriteria($criteria);
    }

    /**
     * @return SLN_Wrapper_Service[]
     */
    public function getAll()
    {
        if ( ! isset($this->services)) {
            $this->services = $this->get(array('@sort' => true));
        }

        return $this->services;
    }

    /**
     * @return SLN_Wrapper_Service[]
     */
    public function getAllSecondary()
    {
        $ret = array();
        foreach ($this->getAll() as $s) {
            if ($s->isSecondary()) {
                $ret[] = $s;
            }
        }

        return $ret;
    }

    /**
     * @return SLN_Wrapper_Service[]
     */
    public function getAllPrimary()
    {
        $ret = array();
        foreach ($this->getAll() as $s) {
            if ( ! $s->isSecondary()) {
                $ret[] = $s;
            }
        }

        return $ret;
    }

    public function getStandardCriteria()
    {
        return $this->processCriteria(array('@sort' => true));
    }

    /**
     * @return SLN_DateTime
     */
    public function getMinPrimaryServiceDuration()
    {
        $min      = false;
        $services = self::getAllPrimary();
        foreach ($services as $service) {
            $duration = $service->getTotalDuration();
            if ( ! $min) {
                $min = $duration;
            } elseif ($min > $duration) {
                $min = $duration;
            }
        }

        return ($min ? $min : new SLN_DateTime('1970-01-01 00:00'));
    }

    /**
     * @param SLN_Wrapper_Service[] $services
     *
     * @return SLN_Wrapper_Service[]
     */
    public function sortByExec($services)
    {
        usort($services, array($this, 'serviceCmp'));

        return $services;
    }

    /**
     * @param SLN_Wrapper_Service[] $services
     *
     * @return SLN_Wrapper_Service[]
     */
    public function sortByExecAndTitleDESC($services)
    {
        usort($services, array($this, 'serviceExecAndTitleDescCmp'));

        return $services;
    }

    public static function serviceExecAndTitleDescCmp($a, $b)
    {
        if ( ! $b) {
            return $a;
        }
        if ( ! $a) {
            return $b;
        }
        if ( ! $a instanceof SLN_Wrapper_Service) /** @var SLN_Wrapper_Service $a */ {
            $a = SLN_Plugin::getInstance()->createService($a);
        }
        if ( ! $b instanceof SLN_Wrapper_Service) /** @var SLN_Wrapper_Service $b */ {
            $b = SLN_Plugin::getInstance()->createService($b);
        }
        $aExecOrder = $a->getExecOrder();
        $bExecOrder = $b->getExecOrder();
        if ($aExecOrder != $bExecOrder) {
            return $aExecOrder > $bExecOrder ? 1 : -1;
        } else {
            $aPosOrder = $a->getPosOrder();
            $bPosOrder = $b->getPosOrder();
            if ($aPosOrder != $bPosOrder) {
                return $aPosOrder > $bPosOrder ? 1 : -1;
            } elseif ($a->getName() > $b->getName()) {
                return -1;
            } else {
                return 1;
            }
        }
    }

    public static function serviceExecCmp($a, $b)
    {
        if ( ! $b) {
            return $a;
        }
        if ( ! $a) {
            return $b;
        }
        if ( ! $a instanceof SLN_Wrapper_Service) {
            $a = SLN_Plugin::getInstance()->createService($a);
        }
        if ( ! $b instanceof SLN_Wrapper_Service) {
            $b = SLN_Plugin::getInstance()->createService($b);
        }

        /** @var SLN_Wrapper_Service $a */
        /** @var SLN_Wrapper_Service $b */
        $aExecOrder = $a->getExecOrder();
        $bExecOrder = $b->getExecOrder();
        if ($aExecOrder > $bExecOrder) {
            return 1;
        } else {
            return -1;
        }
    }

    public static function serviceCmp($a, $b)
    {
        if ( ! $b) {
            return $a;
        }
        if ( ! $a) {
            return $b;
        }
        if ( ! $a instanceof SLN_Wrapper_Service)  {
            $a = SLN_Plugin::getInstance()->createService($a);
        }
        if ( ! $b instanceof SLN_Wrapper_Service)  {
            $b = SLN_Plugin::getInstance()->createService($b);
        }

        /** @var SLN_Wrapper_Service $a */
        /** @var SLN_Wrapper_Service $b */
        $aExecOrder = $a->getExecOrder();
        $bExecOrder = $b->getExecOrder();
        if ($aExecOrder != $bExecOrder) {
            return $aExecOrder > $bExecOrder ? 1 : -1;
        } else {
            $aPosOrder = $a->getPosOrder();
            $bPosOrder = $b->getPosOrder();
            if ($aPosOrder != $bPosOrder) {
                return $aPosOrder > $bPosOrder ? 1 : -1;
            } elseif ($a->getId() > $b->getId()) {
                return 1;
            } else {
                return -1;
            }
        }
    }

    public static function groupServicesByCategory($services)
    {
        global $wpdb;
        $order = explode(',', get_option(SLN_Plugin::CATEGORY_ORDER, ''));
        $ret = array(0 => array('term' => false, 'services' => array()));

        foreach ($services as $s) {
            $post_terms = wp_get_object_terms($s->getId(), SLN_Plugin::TAXONOMY_SERVICE_CATEGORY);


            if ( ! empty($post_terms)) {
                foreach ($post_terms as $post_term) {
                    $post_term_obj = new SLN_Wrapper_ServiceCategory($post_term);
                    $order_pos = array_search($post_term->term_id, $order);
                    if(!is_int($order_pos)){ // if category not fing in order pos
                        $order_pos = count($order);
                        $order[] = $post_term->term_id;
                    }
                    $order_pos++;
                    if ($post_term_obj->getId() == $post_term_obj->getId()) {
                        $ret[$order_pos]['term']       = $post_term_obj;
                        $ret[$order_pos]['services'][] = $s;
                    }
                }
            } else {
                $ret[0]['services'][$s->getId()] = $s;
            }
        }
        foreach($ret as $term_id => &$data){
            if($term_id !== 0 && count($data['services']) < 2){
                $ret[0]['services'] = array_merge((array)$ret[0]['services'], $data['services']);
                $data['services'] = array();
            }
        }
        ksort($ret);
        return $ret;
    }
}
