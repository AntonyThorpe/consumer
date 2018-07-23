<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\TestOnly;

class Product extends Page implements TestOnly
{
    private static $table_name = 'Product';

    private static $db = array(
        'InternalItemID' => 'Varchar(30)',
        'Title' => 'Varchar(200)',
        'BasePrice' => 'Currency'
    );
}
