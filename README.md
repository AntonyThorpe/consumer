# consumer
A SilverStripe BulkLoader for consuming external APIs

[![CI](https://github.com/AntonyThorpe/consumer/actions/workflows/ci.yml/badge.svg)](https://github.com/AntonyThorpe/consumer/actions/workflows/ci.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/antonythorpe/consumer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/antonythorpe/consumer/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/antonythorpe/consumer/v/stable)](https://packagist.org/packages/antonythorpe/consumer)
[![Total Downloads](https://poser.pugx.org/antonythorpe/consumer/downloads)](https://packagist.org/packages/antonythorpe/consumer)
[![Latest Unstable Version](https://poser.pugx.org/antonythorpe/consumer/v/unstable)](https://packagist.org/packages/antonythorpe/consumer)
[![License](https://poser.pugx.org/antonythorpe/consumer/license)](https://packagist.org/packages/antonythorpe/consumer)

Maintain data integrity with an external source of truth.  Keep dataobjects up to date with fresh data received from external APIs.  Inspired by [burnbright/silverstripe-importexport](https://github.com/burnbright/silverstripe-importexport).

## Features
* Retains a record of the maximum last edited date (to use as a limit in future API calls)
* Display, Log and/or email Bulk Loader Results showing the changes made to a dataobject
* Localisation options available for Results report
* Preview setting for dry runs leaving the dataobject untouched

## Use Case
Where there is an external source of truth that a dataobject needs to be updated from.

An [example](https://github.com/AntonyThorpe/silvershop-unleashed) is an eCommerce website where the product prices need to be kept in alignment with an online inventory system (which is used post-sale to manage fulfilment of an order).  With the eCommerce site being a subset of the total inventory items in stock, updating, without creating new product items, is required.  The pricing and other properties change frequently.  Based upon the philosophy of *entering data only once* a sync from the external source of truth would keep the website accurate, up to date and reduce end user maintenance.

## How to use
* Subclass `\AntonyThorpe\Consumer\BulkLoader` and set your column map between the external API fields and the dataobject (see docs and tests folder for guidance).
* Create a `BuildTask` to retrieve fresh API data using a tool like [Guzzle](http://docs.guzzlephp.org/en/latest/)
* Alter the dataobject via a method on your BulkLoader subclass
* Review the Bulk Loader Results report
* Create an instance of the `Consumer` class and record the last edited date for future reference
* Setup a cron job to run the `BuildTask` on a regular basis and monitor incoming emails for exceptions

## Example
A bulk loader for Silvershop Unleashed Software ([link](https://github.com/AntonyThorpe/silvershop-unleashed))

## Requirements
* [SilverStripe](http://www.silverstripe.org)

## Documentation
[Index](/docs/en/index.md)

## Support
None sorry.

## Change Log
[Link](changelog.md)

## Contributing
[Link](contributing.md)

## License
[MIT](LICENCE)
