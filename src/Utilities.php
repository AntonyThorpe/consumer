<?php

namespace AntonyThorpe\Consumer;

class Utilities
{
    /**
     * get duplicates in an array
     *
     * @param  array  $data a list
     * @return array  The items that are duplicates
     */
    public static function getDuplicates($data = array())
    {
        return array_unique(array_diff_assoc($data, array_unique($data)));
    }
}
