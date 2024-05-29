<?php

namespace AntonyThorpe\Consumer\Tests\Model;

use Page;
use SilverStripe\Dev\TestOnly;

class Product extends Page implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'Product';

    /**
     * @config
     */
    private static array $db = [
        'InternalItemID' => 'Varchar(30)',
        'Title' => 'Varchar(200)',
        'BasePrice' => 'Currency'
    ];
}
