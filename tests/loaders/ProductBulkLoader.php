<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\TestOnly;
use AntonyThorpe\Consumer\BulkLoader;

class ProductBulkLoader extends BulkLoader implements TestOnly
{
    public $columnMap = array(
        'ProductCode' => 'InternalItemID',
        'ProductDescription' => 'Title',
        'DefaultSellPrice' => 'BasePrice'
    );

    public $duplicateChecks = array('InternalItemID');
}
