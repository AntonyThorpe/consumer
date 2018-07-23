<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class CourseSelection extends DataObject implements TestOnly
{
    private static $table_name = 'CourseSelection';

    private static $db = array(
        "Term" => "Int"
    );

    private static $has_one = array(
        "Course" => Course::class
    );
}
