<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\TestOnly;
use AntonyThorpe\Consumer\ConsumerBulkLoader;

class UserBulkLoaderMock extends ConsumerBulkLoader implements TestOnly
{
    public $columnMap = array(
        'name' => 'Name',
        'email' => Email::class,
        'username' => 'UserName',
        'phone' => 'Phone',
        'website' => 'Website'
    );

    public $duplicateChecks = array(Email::class);
}
