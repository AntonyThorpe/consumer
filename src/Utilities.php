<?php

namespace AntonyThorpe\Consumer;

class Utilities
{
    /**
     * get duplicates in an array
     */
    public static function getDuplicates(array $data = []): array
    {
        return array_unique(array_diff_assoc($data, array_unique($data)));
    }
}
