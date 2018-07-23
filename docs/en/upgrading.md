# Upgrading

## 2.0.0
### Namespace
Add `use AntonyThorpe\Consumer\...` after your namespace declaration
### Class Name Changes
* `ConsumerBulkLoader` has been changed to `BulkLoader` under the above namespace
* Also changed is `ConsumerBulkLoaderResult`, which is now `BulkLoaderResult`
### Methods
The use of your sub-classed BulkLoader requires your namespaceed dataobject within single quotes in the brackets.
```php
YourBulkLoader::create('Your\Namspace\YourClassName')->updateRecords($apidata);
```
