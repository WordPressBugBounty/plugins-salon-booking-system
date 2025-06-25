<?php

interface SLN_Wrapper_ResourceInterface
{
    function getUnitPerHour();
    function getEnabled();
    function getServices();
    function getMeta($key);
}
