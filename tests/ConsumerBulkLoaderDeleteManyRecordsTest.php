<?php

class ConsumerBulkLoaderDeleteManyRecordsTest extends SapphireTest
{
    protected static $fixture_file = array(
        'consumer/tests/fixtures/usermock.yml'
    );

    protected $extraDataObjects = array(
        'UserMock'
    );

    public function testDeleteManyRecords()
    {
        $shanna = UserMock::get()->find('Email', 'Shanna@melissa.tv');
        $nathan = UserMock::get()->find('Email', 'Nathan@yesenia.net');
        $this->assertTrue($shanna->exists(), 'Shanna exists in UserMock');
        $this->assertTrue($nathan->exists(), 'Nathan exists in UserMock');

        $apidata = json_decode($this->jsondata_to_delete, true);
        $results = UserBulkLoaderMock::create("UserMock")->deleteManyRecords($apidata);

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
            UserMock::get()->find('Email', 'Shanna@melissa.tv'),
            'Shanna@melissa.tv should not exist as a dataobject in UserMock after been deleted'
        );
        $this->assertNull(
            UserMock::get()->find('Email', 'Nathan@yesenia.net'),
            'Nathan@yesenia.net should not exist as a dataobject in UserMock after been deleted'
        );

        $this->assertTrue(
            UserMock::get()->find('Email', 'Lucio_Hettinger@annie.ca')->exists(),
            'Lucio_Hettinger@annie.ca still exists in the UserMock as we did not ask for it to be deleted'
        );
        $this->assertNull(
            UserMock::get()->find('Email', 'shouldnotchangeorbedeleted@net.net'),
            'shouldnotchangeorbedeleted@net.net was never in a dataobject so should not appear'
        );
        $this->assertEquals(
            4,
            UserMock::get()->count(),
            "Should be two items left in UserMock"
        );
    }

    public function testDeleteManyWithPreview()
    {
        $original_count = UserMock::get()->count();
        $apidata = json_decode($this->jsondata_to_delete, true);
        UserBulkLoaderMock::create('UserMock')->deleteManyRecords($apidata, true);

        $this->assertEquals(
            $original_count,
            UserMock::get()->count(),
            'Total instances of UserMock is unchanged'
        );

        $remains = UserMock::get()->find('Email', 'Nathan@yesenia.net');
        $this->assertSame($remains->Email, 'Nathan@yesenia.net', 'Nathan@yesenia.net not removed from UserMock');

        $remains = UserMock::get()->find('Email', 'Shanna@melissa.tv');
        $this->assertSame($remains->Email, 'Shanna@melissa.tv', 'Shanna@melissa.tv not removed from UserMock');
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
