<?php

namespace AntonyThorpe\Consumer\Tests\Loader;

use SilverStripe\Dev\TestOnly;
use AntonyThorpe\Consumer\BulkLoader;

class UserBulkLoader extends BulkLoader implements TestOnly
{
    public $columnMap = [
        'name' => 'Name',
        'email' => 'Email',
        'username' => 'UserName',
        'phone' => 'Phone',
        'website' => 'Website'
    ];

    public $duplicateChecks = ['Email'];
}
