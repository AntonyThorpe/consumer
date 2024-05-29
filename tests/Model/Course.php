<?php

namespace AntonyThorpe\Consumer\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Course extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'Course';

    /**
     * @config
     */
    private static array $db = [
        "Title" => "Varchar"
    ];
}
