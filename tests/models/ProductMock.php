<?php

class ProductMock extends Page implements TestOnly
{
    private static $db = array(
        'InternalItemID' => 'Varchar(30)',
        'Title' => 'Varchar(200)',
        'BasePrice' => 'Currency'
    );
}
