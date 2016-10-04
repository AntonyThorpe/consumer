<?php

class ProductBulkLoaderMock extends ConsumerBulkLoader implements TestOnly
{
    public $columnMap = array(
        'ProductCode' => 'InternalItemID',
        'ProductDescription' => 'Title',
        'DefaultSellPrice' => 'BasePrice'
    );

    public $duplicateChecks = array('InternalItemID');
}
