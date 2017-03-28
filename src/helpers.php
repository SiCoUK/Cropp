<?php
if (!function_exists('cropp'))
{
    function cropp($source, $isAssetWrap = true, $directory = null) 
    {
        return \Yaro\Cropp\Cropp::make($source, $isAssetWrap, $directory);
    } // end cropp
}