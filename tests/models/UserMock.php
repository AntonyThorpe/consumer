<?php

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
