<?php

class UserBulkLoaderMock extends ConsumerBulkLoader implements TestOnly
{
    public $columnMap = array(
        'name' => 'Name',
        'email' => 'Email',
        'username' => 'UserName',
        'phone' => 'Phone',
        'website' => 'Website'
    );

    public $duplicateChecks = array('Email');
}
