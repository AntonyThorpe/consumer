<?php

namespace AntonyThorpe\Consumer\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class User extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'User';

    /**
     * @config
     */
    private static array $db = [
        'Name' => 'Varchar',
        'Email' => 'Varchar(254)',
        'UserName' => 'Varchar',
        'Phone' => 'Varchar',
        'Website' => 'Varchar',
        'Guid' => 'Varchar'
    ];
}
