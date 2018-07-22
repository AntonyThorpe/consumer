<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\SapphireTest;
use AntonyThorpe\Consumer\ArrayBulkLoaderSource;

class ConsumerArrayBulkLoaderSourceTest extends SapphireTest
{
    public function testIterator()
    {
        $data = array(
            array("First" => 1),
            array("First" => 2)
        );
        $source = new ArrayBulkLoaderSource($data);
        $iterator = $source->getIterator();
        $count = 0;
        foreach ($iterator as $record) {
            $this->assertEquals($data[$count], $record);
            $count++;
        }
    }
}
