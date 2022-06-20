<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Core\Convert;
use SilverStripe\Dev\SapphireTest;
use AntonyThorpe\Consumer\Tests\User;
use AntonyThorpe\Consumer\Tests\Product;

class BulkLoaderUpdateRecordsTest extends SapphireTest
{
    protected static $fixture_file = array(
        'fixtures/User.yml',
        'fixtures/Product.yml'
    );

    protected static $extra_dataobjects = [
        User::class,
        Product::class
    ];

    public function setUp()
    {
        parent::setUp();

        //publish some product categories and products so as to test the Live version
        $this->objFromFixture(Product::class, 'products')->publish('Stage', 'Live');
        $this->objFromFixture(Product::class, 'pm1')->publish('Stage', 'Live');
        $this->objFromFixture(Product::class, 'pm2')->publish('Stage', 'Live');
        $this->objFromFixture(Product::class, 'pm3')->publish('Stage', 'Live');
    }

    public function testUpdateRecords()
    {
        $apidata = json_decode($this->jsondata1, true);
        $results = UserBulkLoader::create('AntonyThorpe\Consumer\Tests\User')->updateRecords($apidata);

        // Check Results
        $this->assertEquals($results->CreatedCount(), 0);
        $this->assertEquals($results->UpdatedCount(), 3);
        $this->assertEquals($results->DeletedCount(), 0);
        $this->assertEquals($results->SkippedCount(), 7);
        $this->assertEquals($results->Count(), 3);

        $this->assertContains(
            '[before] => (string) Will Be Updated',
            print_r($results->getUpdated(), true),
            'Results show Sincere@april.biz will be updated'
        );
        $this->assertContains(
            '[after] => (string) Leanne Graham',
            print_r($results->getUpdated(), true),
            'Results show Sincere@april.biz has been changed'
        );

        // Check Dataobjects
        $obj = User::get()->find('Email', 'Sincere@april.biz');
        $this->assertSame(
            'Leanne Graham',
            $obj->Name,
            'Should have changed the Name of Sincere@april.biz to Leanne Graham'
        );
        $this->assertSame(
            'Bret',
            $obj->UserName,
            'Should have changed the UserName of Sincere@april.biz to Bret'
        );
        $this->assertSame(
            '1-770-736-8031 x56442',
            $obj->Phone,
            'Should have changed the Phone number of Sincere@april.biz to 1-770-736-8031 x56442'
        );

        $obj_untouched = User::get()->find('Email', 'LocalOnly@local.net');
        $this->assertSame(
            'LocalOnly',
            $obj_untouched->UserName,
            'Calling the Consumer functions does not alter an existing unsynched dataobject in User'
        );

        $this->assertNull(
            User::get()->find('Email', 'Rey.Padberg@karina.biz'),
            'Rey.Padberg@karina.biz has not been accidently created'
        );
    }

    public function testUpdateRecordsWithPreview()
    {
        $original_count = User::get()->count();
        $apidata = json_decode($this->jsondata1, true);
        UserBulkLoader::create('AntonyThorpe\Consumer\Tests\User')->updateRecords($apidata, true);

        $this->assertEquals(
            $original_count,
            User::get()->count(),
            'Total instances of User is unchanged'
        );

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
    }

    public function testUpdateRecordsWhenDataobjectExtendsPage()
    {
        $apidata = json_decode($this->jsondata2, true);
        $loader = ProductBulkLoader::create('AntonyThorpe\Consumer\Tests\Product');
        $loader->transforms = array(
            'Title' => array(
                'callback' => function ($value, &$placeholder) {
                    $placeholder->URLSegment = Convert::raw2url($value);
                    return $value;
                }
            )
        );
        $results = $loader->updateRecords($apidata);

        // Check Results
        $this->assertEquals($results->CreatedCount(), 0);
        $this->assertEquals($results->UpdatedCount(), 2);
        $this->assertEquals($results->DeletedCount(), 0);
        $this->assertEquals($results->SkippedCount(), 0);
        $this->assertEquals($results->Count(), 2);

        $this->assertContains(
            '[before] => (string) Space - Beyond the Solar System',
            print_r($results->getUpdated(), true),
            'Results show product code SPACE will be updated'
        );
        $this->assertContains(
            '[after] => (string) Space - The Final Frontier',
            print_r($results->getUpdated(), true),
            'Results show product code SPACE has been changed'
        );
        $this->assertNotContains(
            'ShowInMenus',
            print_r($results->getUpdated(), true),
            'Does not contain ShowInMenus (a default of the Page class) - these are left as is and not altered by ProductBulkLoader'
        );
        $this->assertNotContains(
            'ShowInSearch',
            print_r($results->getUpdated(), true),
            'Does not contain ShowInMenus (a default of the Page class) - these are left as is and not altered by ProductBulkLoader'
        );

        $book = Product::get()->find('InternalItemID', 'BOOK');
        $this->assertSame(
            'Book of Science',
            $book->Title,
            'Title has been changed to Book of Science'
        );
        $this->assertSame(
            'book-of-science',
            $book->URLSegment,
            'The URLSegment has been changed to book-of-science'
        );

        $space = Product::get()->find('InternalItemID', 'SPACE');
        $this->assertSame(
            '95.95',
            $space->BasePrice,
            'DefaultSellPrice has been set to 95.95'
        );

        $this->assertSame(
            0,
            (int)$space->ShowInMenus,
            'ProductBulkLoader does not set to true ShowInMenus and ShowInSearch'
        );
    }


    /**
     * JSON data for test
     *
     * @link (JSONPlaceholder, http://jsonplaceholder.typicode.com/)
     * @var string
     */
    protected $jsondata1 = '[
      {
        "id": 1,
        "name": "Leanne Graham",
        "username": "Bret",
        "email": "Sincere@april.biz",
        "phone": "1-770-736-8031 x56442",
        "website": "hildegard.org",
        "lastmodified": "2015-11-21T08:07:20"
      },
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
        "id": 4,
        "name": "Patricia Lebsack",
        "username": "Karianne",
        "email": "Julianne.OConner@kory.org",
        "phone": "493-170-9623 x156",
        "website": "kale.biz",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 5,
        "name": "Chelsey Dietrich",
        "username": "Kamren",
        "email": "Lucio_Hettinger@annie.ca",
        "phone": "(254)954-1289",
        "website": "demarco.info",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 6,
        "name": "Mrs. Dennis Schulist",
        "username": "Leopoldo_Corkery",
        "email": "Karley_Dach@jasper.info",
        "phone": "1-477-935-8478 x6430",
        "website": "ola.org",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 7,
        "name": "Kurtis Weissnat",
        "username": "Elwyn.Skiles",
        "email": "Telly.Hoeger@billy.biz",
        "phone": "210.067.6132",
        "website": "elvis.io",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 8,
        "name": "Nicholas Runolfsdottir V",
        "username": "Maxime_Nienow",
        "email": "Sherwood@rosamond.me",
        "phone": "586.493.6943 x140",
        "website": "jacynthe.com",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 9,
        "name": "Glenna Reichert",
        "username": "Delphine",
        "email": "Chaim_McDermott@dana.io",
        "phone": "(775)976-6794 x41206",
        "website": "conrad.com",
        "lastmodified": "2015-11-21T08:07:20"
      },
      {
        "id": 10,
        "name": "Clementina DuBuque",
        "username": "Moriah.Stanton",
        "email": "Rey.Padberg@karina.biz",
        "phone": "024-648-3804",
        "website": "ambrose.net",
        "lastmodified": "2015-11-21T08:07:20"
      }
    ]';

    /**
     * JSON data for test
     *
     * @link (Unleashed Software, https://apidocs.unleashedsoftware.com/Products)
     * @var string
     */
    protected $jsondata2 = '[
        {
            "ProductCode": "SPACE",
            "ProductDescription": "Space - The Final Frontier",
            "Barcode": null,
            "DefaultSellPrice": 95.95,
            "Guid": "c92823f0-a5af-4e66-91a7-d2c66c25f885",
            "LastModifiedOn": "2016-02-21T08:07:20"
        },
        {
            "ProductCode": "BOOK",
            "ProductDescription": "Book of Science",
            "Barcode": null,
            "DefaultSellPrice": 24.95,
            "Guid": "5cf1c9ba-89de-4f59-879c-c08a447fd49c",
            "LastModifiedOn": "2015-11-21T08:07:20"
        }
    ]';
}
