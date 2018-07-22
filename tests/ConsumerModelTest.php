<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\SapphireTest;
use AntonyThorpe\Consumer\Consumer;

class ConsumerModelTest extends SapphireTest
{
    public function testCreateUpdate()
    {
        $apidata = json_decode($this->jsondata, true);
        $dataobject = Consumer::create(
            array(
                'Title' => 'ProductUpdate',
                'ExternalLastEditedKey' => 'lastmodified'
            )
        )->setMaxExternalLastEdited($apidata);
        $id = $dataobject->write();

        $item = Consumer::get()->byId($id);  // grab item using the id
        $this->assertSame(
            'ProductUpdate',
            $item->Title,
            'Title has been set'
        );
        $this->assertSame(
            'lastmodified',
            $item->ExternalLastEditedKey,
            'ExternalLastEditedKey is set to lastmodified'
        );
        $this->assertSame(
            $item->ExternalLastEdited,
            '2015-11-21 08:07:20',
            'Maximum date saved'
        );
    }

    public function testCreateUpdateWithUnixDateFormat()
    {
        $apidata = json_decode($this->jsondata2, true);
        $dataobject = Consumer::create(
            array(
                'Title' => 'ProductUpdate2',
                'ExternalLastEditedKey' => 'lastmodified'
            )
        )->setMaxExternalLastEdited($apidata);
        $id = $dataobject->write();

        $item = Consumer::get()->byId($id);  // grab item using the id
        $this->assertSame(
            $item->ExternalLastEdited,
            '2012-04-08 15:37:19',
            "Maximum Unix date saved"
        );
    }

    public function testCreateUpdateWithUnixDateFormatInMilliseconds()
    {
        $apidata = json_decode($this->jsondata3, true);
        $dataobject = Consumer::create(
            array(
                'Title' => 'ProductUpdate3',
                'ExternalLastEditedKey' => 'lastmodified'
            )
        )->setMaxExternalLastEdited($apidata);
        $id = $dataobject->write();

        $item = Consumer::get()->byId($id);  // grab item using the id
        $this->assertSame(
            $item->ExternalLastEdited,
            '2012-04-08 15:37:19',
            "Maximum Unix date in milliseconds saved - but note milliseconds not recorded by SS"
        );
    }

    /**
     * JSON data for testing
     *
     * @link (JSONPlaceholder, http://jsonplaceholder.typicode.com/)
     * @var string
     */
    protected $jsondata = '[
        {
            "id": 1,
            "lastmodified": "2014-11-21T08:07:20"
        },
        {
            "id": 2,
            "lastmodified": "2015-11-21T08:07:20"
        },
        {
            "id": 3,
            "lastmodified": "2013-11-21T08:07:20"
        }
    ]';

    /**
     * Additional JSON data for testing
     * @var string
     */
    protected $jsondata2 = '[
        {
            "id": 1,
            "lastmodified": "/Date(1333699439)/"
        },
        {
            "id": 2,
            "lastmodified": "/Date(1333799439)/"
        },
        {
            "id": 3,
            "lastmodified": "/Date(1333899439)/"
        }
    ]';

    /**
     * Additional JSON data for testing
     * @var string
     */
    protected $jsondata3 = '[
        {
            "id": 1,
            "lastmodified": "/Date(1333699439123)/"
        },
        {
            "id": 2,
            "lastmodified": "/Date(1333799439456)/"
        },
        {
            "id": 3,
            "lastmodified": "/Date(1333899439789)/"
        }
    ]';
}
