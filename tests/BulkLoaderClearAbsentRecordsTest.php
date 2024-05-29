<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Dev\SapphireTest;
use AntonyThorpe\Consumer\Tests\Loader\UserBulkLoader;
use AntonyThorpe\Consumer\Tests\Model\User;

class BulkLoaderClearAbsentRecordsTest extends SapphireTest
{
    protected static $fixture_file = ['Fixtures/User.yml'];

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [User::class];

    public function testClearAbsentRecords(): void
    {
        $apidata = (array) json_decode($this->jsondata, true);
        $results = UserBulkLoader::create(User::class)->clearAbsentRecords($apidata, 'guid', 'Guid');

        // Check Results
        $this->assertEquals($results->CreatedCount(), 0);
        $this->assertEquals($results->UpdatedCount(), 1);
        $this->assertEquals($results->DeletedCount(), 0);
        $this->assertEquals($results->SkippedCount(), 0);
        $this->assertEquals($results->Count(), 1);

        $this->assertStringContainsString(
            '[before] => (string) 99999',
            print_r($results->getUpdated(), true),
            'Results show that LocalOnly@local.net will have its Guid property was 99999'
        );

        // Check Dataobjects
        $obj = User::get()->find('Email', 'LocalOnly@local.net');
        $this->assertEmpty(
            $obj->Guid,
            'LocalOnly@local.net Should have nothing in the Guid field'
        );

        $obj = User::get()->find('Email', 'Sincere@april.biz');
        $this->assertSame(
            '1',
            $obj->Guid,
            'Sincere@april.biz Should not have changed the Guid field'
        );
    }

    public function testClearAbsentRecordsWithPreview(): void
    {
        $apidata = (array) json_decode($this->jsondata, true);
        UserBulkLoader::create(User::class)->clearAbsentRecords($apidata, 'guid', 'Guid', true);

        $item = User::get()->find('Email', 'LocalOnly@local.net');
        $this->assertSame(
            '99999',
            $item->Guid,
            'LocalOnly@local.net Guid property not updated'
        );
    }

    /**
     * JSON data for test
     *
     * @link (JSONPlaceholder, http://jsonplaceholder.typicode.com/)
     * @var string
     */
    protected $jsondata = '[
      {
        "id": 1,
        "name": "Leanne Graham",
        "username": "Bret",
        "email": "Sincere@april.biz",
        "phone": "1-770-736-8031 x56442",
        "website": "hildegard.org",
        "guid": "1",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 2,
        "name": "Ervin Howell",
        "username": "Antonette",
        "email": "Shanna@melissa.tv",
        "phone": "010-692-6593 x09125",
        "website": "anastasia.net",
        "guid": "2",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 3,
        "name": "Clementine Bauch",
        "username": "Samantha",
        "email": "Nathan@yesenia.net",
        "phone": "1-463-123-4447",
        "website": "ramiro.info",
        "guid": "3",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 4,
        "name": "Patricia Lebsack",
        "username": "Karianne",
        "email": "Julianne.OConner@kory.org",
        "phone": "493-170-9623 x156",
        "website": "kale.biz",
        "guid": "4",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 5,
        "name": "Chelsey Dietrich",
        "username": "Kamren",
        "email": "Lucio_Hettinger@annie.ca",
        "phone": "(254)954-1289",
        "website": "demarco.info",
        "guid": "5",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 6,
        "name": "Mrs. Dennis Schulist",
        "username": "Leopoldo_Corkery",
        "email": "Karley_Dach@jasper.info",
        "phone": "1-477-935-8478 x6430",
        "website": "ola.org",
        "guid": "6",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 7,
        "name": "Kurtis Weissnat",
        "username": "Elwyn.Skiles",
        "email": "Telly.Hoeger@billy.biz",
        "phone": "210.067.6132",
        "website": "elvis.io",
        "guid": "7",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 8,
        "name": "Nicholas Runolfsdottir V",
        "username": "Maxime_Nienow",
        "email": "Sherwood@rosamond.me",
        "phone": "586.493.6943 x140",
        "website": "jacynthe.com",
        "guid": "8",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 9,
        "name": "Glenna Reichert",
        "username": "Delphine",
        "email": "Chaim_McDermott@dana.io",
        "phone": "(775)976-6794 x41206",
        "website": "conrad.com",
        "guid": "9",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 10,
        "name": "Clementina DuBuque",
        "username": "Moriah.Stanton",
        "email": "Rey.Padberg@karina.biz",
        "phone": "024-648-3804",
        "website": "ambrose.net",
        "guid": "10",
        "lastmodified": "2015-11-21T08:07:20"
      }
    ]';
}
