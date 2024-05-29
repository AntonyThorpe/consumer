<?php

namespace AntonyThorpe\Consumer\Tests\Loader;

use SilverStripe\Dev\TestOnly;
use AntonyThorpe\Consumer\BulkLoader;

class ProductBulkLoader extends BulkLoader implements TestOnly
{
    public $columnMap = [
        'ProductCode' => 'InternalItemID',
        'ProductDescription' => 'Title',
        'DefaultSellPrice' => 'BasePrice'
    ];

    public $duplicateChecks = ['InternalItemID'];
}
