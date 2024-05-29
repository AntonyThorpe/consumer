<?php

namespace AntonyThorpe\Consumer\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class CourseSelection extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'CourseSelection';

    /**
     * @config
     */
    private static array $db = [
        "Term" => "Int"
    ];

    /**
     * @config
     */
    private static array $has_one = [
        "Course" => Course::class
    ];
}
