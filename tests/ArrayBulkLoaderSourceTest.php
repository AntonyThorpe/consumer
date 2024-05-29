<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\SapphireTest;
use AntonyThorpe\Consumer\ArrayBulkLoaderSource;

class ArrayBulkLoaderSourceTest extends SapphireTest
{
    public function testIterator(): void
    {
        $data = [
            ["First" => 1],
            ["First" => 2]
        ];
        $source = new ArrayBulkLoaderSource($data);
        $iterator = $source->getIterator();
        $count = 0;
        foreach ($iterator as $record) {
            $this->assertEquals($data[$count], $record);
            $count++;
        }
    }
}
