<?php

class SLN_Wrapper_Resource extends SLN_Wrapper_Abstract implements SLN_Wrapper_ResourceInterface
{
    const _CLASS = 'SLN_Wrapper_Resource';

    public function getPostType()
    {
        return SLN_Plugin::POST_TYPE_RESOURCE;
    }

    function getUnitPerHour()
    {
        $ret = $this->getMeta('unit');
        $ret = !is_numeric($ret) ? null : intval($ret);

        return $ret;
    }

    function getEnabled()
    {
        $ret = $this->getMeta('enabled');
        return $ret !== '' ? (bool)$ret : true;
    }

    function getServices()
    {
        $ret = $this->getMeta('services');
        return is_array($ret) ? array_filter(array_map('intval', $ret)) : array();
    }

    public function __toString()
    {
        return $this->getTitle();
    }

}
