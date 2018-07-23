<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Country extends Dataobject implements TestOnly
{
    private static $table_name = 'Country';

    private static $db = array(
        "Title" => "Varchar",
        "Code" => "Varchar"
    );
}
