<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\SapphireTest;
use AntonyThorpe\Consumer\Tests\User;

class BulkLoaderDeleteManyRecordsTest extends SapphireTest
{
    protected static $fixture_file = ['fixtures/User.yml'];

    protected static $extra_dataobjects = [User::class];

    public function testDeleteManyRecords()
    {
        $shanna = User::get()->find('Email', 'Shanna@melissa.tv');
        $nathan = User::get()->find('Email', 'Nathan@yesenia.net');
        $this->assertTrue($shanna->exists(), 'Shanna exists in User');
        $this->assertTrue($nathan->exists(), 'Nathan exists in User');

        $apidata = json_decode($this->jsondata_to_delete, true);
        $results = UserBulkLoader::create('AntonyThorpe\Consumer\Tests\User')->deleteManyRecords($apidata);

        // Check Results
        $this->assertEquals($results->CreatedCount(), 0);
        $this->assertEquals($results->UpdatedCount(), 0);
        $this->assertEquals($results->DeletedCount(), 2);
        $this->assertEquals($results->SkippedCount(), 1);
        $this->assertEquals($results->Count(), 0);

        $this->assertContains(
            '[Email] => Shanna@melissa.tv',
            print_r($results->getDeleted(), true),
            'Results show Sincere@april.biz will be updated'
        );
        $this->assertContains(
            '[Email] => Nathan@yesenia.net',
            print_r($results->getDeleted(), true),
            'Results show Sincere@april.biz has been changed'
        );
        $this->assertNotContains(
            'shouldnotchangeanything@net.net',
            print_r($results, true),
            'shouldnotchangeanything@net.net was never in a dataobject so should not be deleted in the log'
        );

        // Check Dataobjects
        $this->assertNull(
            User::get()->find('Email', 'Shanna@melissa.tv'),
            'Shanna@melissa.tv should not exist as a dataobject in User after been deleted'
        );
        $this->assertNull(
            User::get()->find('Email', 'Nathan@yesenia.net'),
            'Nathan@yesenia.net should not exist as a dataobject in User after been deleted'
        );

        $this->assertTrue(
            User::get()->find('Email', 'Lucio_Hettinger@annie.ca')->exists(),
            'Lucio_Hettinger@annie.ca still exists in the User as we did not ask for it to be deleted'
        );
        $this->assertNull(
            User::get()->find('Email', 'shouldnotchangeorbedeleted@net.net'),
            'shouldnotchangeorbedeleted@net.net was never in a dataobject so should not appear'
        );
        $this->assertEquals(
            4,
            User::get()->count(),
            "Should be two items left in User"
        );
    }

    public function testDeleteManyWithPreview()
    {
        $original_count = User::get()->count();
        $apidata = json_decode($this->jsondata_to_delete, true);
        UserBulkLoader::create('AntonyThorpe\Consumer\Tests\User')->deleteManyRecords($apidata, true);

        $this->assertEquals(
            $original_count,
            User::get()->count(),
            'Total instances of User is unchanged'
        );

        $remains = User::get()->find('Email', 'Nathan@yesenia.net');
        $this->assertSame($remains->Email, 'Nathan@yesenia.net', 'Nathan@yesenia.net not removed from User');

        $remains = User::get()->find('Email', 'Shanna@melissa.tv');
        $this->assertSame($remains->Email, 'Shanna@melissa.tv', 'Shanna@melissa.tv not removed from User');
    }

    /**
     * JSON data for test
     *
     * @link (JSONPlaceholder, http://jsonplaceholder.typicode.com/)
     * @var string
     */
    protected $jsondata_to_delete = '[
      {
        "id": 2,
        "name": "Ervin Howell",
        "username": "Antonette",
        "email": "Shanna@melissa.tv",
        "phone": "010-692-6593 x09125",
        "website": "anastasia.net",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 3,
        "name": "Clementine Bauch",
        "username": "Samantha",
        "email": "Nathan@yesenia.net",
        "phone": "1-463-123-4447",
        "website": "ramiro.info",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 18,
        "name": "Brand new guy who doesnt do anything",
        "username": "Never been near a dataobject",
        "email": "shouldnotchangeorbedeleted@net.net",
        "phone": "493-170-9623 x156",
        "website": "yea.biz",
        "lastmodified": "2015-11-21T08:07:20"
      }
    ]';
}
