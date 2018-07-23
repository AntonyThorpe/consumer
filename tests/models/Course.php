<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Course extends DataObject implements TestOnly
{
    private static $table_name = 'Course';

    private static $db = array(
        "Title" => "Varchar"
    );
}
