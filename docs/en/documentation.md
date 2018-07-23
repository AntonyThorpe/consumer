# Documentation of Consumer

## Starting Point Example
A dataobject class in an eCommerce website:
```php
namespace 'Your\Namespace';

class Product extends Page
{
    private static $db = array(
        'InternalItemID' => 'Varchar(30)',
        'Title' => 'Varchar(200)',
        'BasePrice' => 'Currency'
    );
}
```

## Subclass the BulkLoader class
Set your column map between the external API fields and the dataobject (see tests for advanced settings, like Relations/callbacks for each API data row).
```php
use AntonyThorpe\Consumer\BulkLoader;

class ProductBulkLoader extends BulkLoader
{
    public $columnMap = array(
        'ProductCode' => 'InternalItemID',
        'ProductDescription' => 'Title',
        'DefaultSellPrice' => 'BasePrice'
    );

    public $duplicateChecks = array('InternalItemID');
}
```

## Create a BuildTask to alter the dataobject
Create a [BuildTask](http://www.balbuss.com/creating-tasks/) to call an external API and update the dataobject.
```php
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Convert;
use Your\Namespace\ProductBulkLoader;
use Your\Namspace\ProductCategory;

class ProductBuildTaskExample extends BuildTask
{
    public function run($request)
    {
        // $response = call to external API to get products;
        // below code assumes the use of Guzzle(http://docs.guzzlephp.org/en/latest/)
        if ($response->getStatusCode() == '200' && $apidata = json_decode($response->getBody()->getContents(), true)) {
            $loader = ProductBulkLoader::create('Your\Namspace\Product');  // must be single quotes
            $loader->transforms = array(
                'Parent' => array(
                    'callback' => function ($value, $placeholder) {
                        $obj = ProductCategory::get()->find('Guid', $value['Guid']);
                        if ($obj) {
                            return $obj;
                        } else {
                            return ProductCategory::get()->find('Title', $value['GroupName']);
                        }
                    }
                ),
                'BasePrice' => array(
                    'callback' => function ($value, $placeholder) {
                        return (float)$value;
                    }
                ),
                'Title' => array(
                    'callback' => function ($value, &$placeholder) {
                        // set the URLSegment
                        $placeholder->URLSegment = Convert::raw2url($value);
                        return $value;
                    }
                )
            );
            $results = $loader->updateRecords($apidata);
        }
    }
}
```

## Dry Run and Review Results
To preview the Bulk Loader Results without altering the dataobject, add `true` as the second argument in the BulkLoader method.  Example below:
```php
    $results = ProductBulkLoader::create('User')->updateRecords($apidata, true);

    // to screen
    echo "<div>" . Debug::text($results->getData()) . "</div>";

    // send email
    if ($results->Count()) {
        $email = Email::create(
            'from_email',
            'to_email',
            'Subject: Preview Results',
            Debug::text($results->getData())
        );
        $email->send();
    }
```

## BulkLoader Methods
* `updateRecords` - update a record if it exists
* `upsertManyRecords` - update an existing record or create a new one
* `deleteManyRecords` - delete the dataobjects matched to the supplied data
* `clearAbsentRecords` - clears a property value if the record is absent in the supplied data

## Maximum Last Edited Date
Having the maximum edited date provides an option to limit the next API call (`url/endpoint?modifiedSince=Date`).
The example below saves the maximum `lastmodified` field(provided by the external API):
```php
use AntonyThorpe\Consumer\Consumer;

$jsondata = '[
    {
        "id": 2,
        "lastmodified": "2015-11-21T08:07:20"
    },
    {
        "id": 3,
        "lastmodified": "2015-11-21T08:07:20"
    }
];
$apidata = json_decode($jsondata, true);
$consumer = Consumer::create(
    array(
        'Title' => 'ProductUpdate',
        'ExternalLastEditedKey' => 'lastmodified'
    )
)->setMaxExternalLastEdited($apidata);
$consumer->write();
```
Later grab the date before making the next API call:
```php
$consumer = Product::get()->find('Title', 'ProductUpdate');
$date = new DateTime($consumer->ExternalLastEdited);
$modified_since = substr($date->format('Y-m-d\TH:i:s.u'), 0, 23);  // use this variable to limit the next call.
```
