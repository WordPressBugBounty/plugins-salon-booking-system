<?php

class SLN_Repository_ResourceRepository extends SLN_Repository_AbstractWrapperRepository
{
    private $resources;

    public function getWrapperClass()
    {
        return SLN_Wrapper_Resource::_CLASS;
    }

    protected function processCriteria($criteria)
    {
        $criteria = apply_filters('sln.repository.resource.processCriteria', $criteria);
        return parent::processCriteria($criteria);
    }

    /**
     * @return SLN_Wrapper_Service[]
     */
    public function getAll()
    {
        if (!defined('SLN_VERSION_PAY') || !SLN_VERSION_PAY || !SLN_Plugin::getInstance()->getSettings()->isResourcesEnabled()) {
            return array();
        }

        if ( ! isset($this->resources)) {
            $this->resources = $this->get(array('post_status' => 'any'));
        }

        return $this->resources;
    }

    /**
     * @return SLN_Wrapper_Resource[]
     */
    public function getAllEnabled()
    {
        $ret = array();
        foreach ($this->getAll() as $s) {
            if ($s->getEnabled()) {
                $ret[] = $s;
            }
        }

        return $ret;
    }

    public function getStandardCriteria()
    {
        return $this->processCriteria(array());
    }

    public function findByService(SLN_Wrapper_Service $service)
    {
        $ret = array();

        foreach ($this->getAllEnabled() as $resource) {
            if (in_array($service->getId(), $resource->getServices())) {
                $ret[] = $resource;
            }
        }

        return $ret;
    }


}
