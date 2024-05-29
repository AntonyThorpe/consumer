<?php

namespace AntonyThorpe\Consumer\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Country extends Dataobject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'Country';

    /**
     * @config
     */
    private static array $db = [
        "Title" => "Varchar",
        "Code" => "Varchar"
    ];
}
