<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\SapphireTest;
use AntonyThorpe\Consumer\Tests\User;

class BulkLoaderUpsertManyRecordsTest extends SapphireTest
{
    protected static $fixture_file = array('fixtures/User.yml');

    protected static $extra_dataobjects = [User::class];

    public function testUpsertManyRecords()
    {
        $apidata = json_decode($this->jsondata_to_upsert, true);
        $results = UserBulkLoader::create('AntonyThorpe\Consumer\Tests\User')->upsertManyRecords($apidata);

        // Check Results
        $this->assertEquals($results->CreatedCount(), 1);
        $this->assertEquals($results->UpdatedCount(), 2);
        $this->assertEquals($results->DeletedCount(), 0);
        $this->assertEquals($results->SkippedCount(), 0);
        $this->assertEquals($results->Count(), 3);

        $this->assertStringContainsString(
            '[Email] => brandnewguy@net.net',
            print_r($results->getCreated(), true),
            'Results show brandnewguy@net.net has been created'
        );
        $this->assertStringContainsString(
            '[before] => (string) Will Be Updated',
            print_r($results->getUpdated(), true),
            'Results show ID 2 will be updated'
        );
        $this->assertStringContainsString(
            '[after] => (string) anastasia.net',
            print_r($results->getUpdated(), true),
            'Results show ID 2 has been changed'
        );

        // Check Dataobjects
        $this->assertEquals(7, User::get()->Count(), '7 instances in User');
        $this->assertSame(
            'New Name',
            User::get()->find('Email', 'Shanna@melissa.tv')->Name,
            'Shanna@melissa.tv Name should be New Name'
        );
        $this->assertSame(
            'New Phone Number',
            User::get()->find('Email', 'Shanna@melissa.tv')->Phone,
            'Shanna@melissa.tv Phone should be New Phone Number'
        );
        $this->assertSame(
            'NewUserName',
            User::get()->find('Email', 'Nathan@yesenia.net')->UserName,
            'Nathan@yesenia.net should have a UserName of NewUserName'
        );
        $this->assertSame(
            'Brand new guy',
            User::get()->find('Email', 'brandnewguy@net.net')->Name,
            'brandnewguy@net.net should be a new dataobject'
        );
        $this->assertSame(
            'Welcome to the dataobject',
            User::get()->find('Email', 'brandnewguy@net.net')->UserName,
            'brandnewguy@net.net should be a new dataobject'
        );
    }

    public function testUpsertManyRecordsWithPreview()
    {
        $apidata = json_decode($this->jsondata_to_upsert, true);
        UserBulkLoader::create('AntonyThorpe\Consumer\Tests\User')->upsertManyRecords($apidata, true);

        $remains = User::get()->find('Email', 'Sincere@april.biz');
        $this->assertSame(
            'Will Be Updated',
            $remains->Name,
            'Sincere@april.biz Name not updated'
        );
        $this->assertSame(
            'Will Be Updated',
            $remains->Phone,
            'Sincere@april.biz Phone not updated'
        );

        $remains = User::get()->find('Email', 'Shanna@melissa.tv');
        $this->assertSame(
            'Will Be Updated',
            $remains->Name,
            'Shanna@melissa.tv Name not updated'
        );
        $this->assertSame(
            'Will Be Updated',
            $remains->Phone,
            'Shanna@melissa.tv Phone not updated'
        );

        $this->assertNull(
            User::get()->find('Email', 'brandnewguy@net.net'),
            'brandnewguy@net.net has not been created'
        );
    }

    /**
     * JSON data for test
     *
     * @link (JSONPlaceholder, http://jsonplaceholder.typicode.com/)
     * @var string
     */
    protected $jsondata_to_upsert = '[
      {
        "id": 2,
        "name": "New Name",
        "username": "Antonette",
        "email": "Shanna@melissa.tv",
        "phone": "New Phone Number",
        "website": "anastasia.net",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 3,
        "name": "Clementine Bauch",
        "username": "NewUserName",
        "email": "Nathan@yesenia.net",
        "phone": "1-463-123-4447",
        "website": "ramiro.info",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 18,
        "name": "Brand new guy",
        "username": "Welcome to the dataobject",
        "email": "brandnewguy@net.net",
        "phone": "493-170-9623 x156",
        "website": "yea.biz",
        "lastmodified": "2015-11-21T08:07:20"
      }
    ]';
}
