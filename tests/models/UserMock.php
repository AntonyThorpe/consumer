<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class UserMock extends DataObject implements TestOnly
{
    private static $db = array(
        'Name' => 'Varchar',
        'Email' => 'Varchar(254)',
        'UserName' => 'Varchar',
        'Phone' => 'Varchar',
        'Website' => 'Varchar',
        'Guid' => 'Varchar'
    );
}
