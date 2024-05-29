<?php

namespace AntonyThorpe\Consumer\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Person extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'Person';

    /**
     * @config
     */
    private static array $db = [
        "FirstName" => "Varchar",
        "Surname" => "Varchar",
        "Age" => "Int"
    ];

    /**
     * @config
     */
    private static array $has_one = [
        "Country" => Country::class
    ];
}
