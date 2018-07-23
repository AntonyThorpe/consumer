<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Person extends DataObject implements TestOnly
{
    private static $table_name = 'Person';

    private static $db = array(
        "FirstName" => "Varchar",
        "Surname" => "Varchar",
        "Age" => "Int"
    );

    private static $has_one = array(
        "Country" => Country::class
    );
}
