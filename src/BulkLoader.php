<?php

namespace AntonyThorpe\Consumer;

use SilverStripe\Core\Environment;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\DataObject;
use Exception;

/**
 * The bulk loader allows large-scale uploads to SilverStripe via the ORM.
 *
 * Data comes from a given BulkLoaderSource, providing an iterator of records.
 *
 * Incoming data can be mapped to fields, based on a given mapping,
 * and it can be used to find or create related objects to be linked to.
 *
 * Failed record imports will be marked as skipped
 *
 * Modified from BetterBulkLoader of SilverStripe-ImportExport (https://github.com/burnbright/silverstripe-importexport) 2018-07-21
 */
class BulkLoader extends \SilverStripe\Dev\BulkLoader
{
    /**
     * Fields and corresponding labels
     * that can be mapped to.
     * Can include dot notations.
     * @var array
     */
    public $mappableFields = array();

    /**
     * Transformation and relation handling
     * @var array
     */
    public $transforms = array();

    /**
     * Specify a colsure to be run on every imported record.
     * @var function
     */
    public $recordCallback;

    /**
     * Bulk loading source
     * @var BulkLoaderSource
     */
    protected $source;

    /**
     * Add new records while importing
     * @var Boolean
     */
    protected $addNewRecords = true;

    /**
     * The default behaviour for linking relations
     * @var boolean
     */
    protected $relationLinkDefault = true;

    /**
     * The default behaviour creating relations
     * @var boolean
     */
    protected $relationCreateDefault = true;

    /**
     * Determines whether pages should be published during loading
     * @var boolean
     */
    protected $publishPages = true;

    /**
     * Cache the result of getMappableColumns
     * @var array
     */
    protected $mappableFields_cache;

    /**
     * Set the BulkLoaderSource for this BulkLoader.
     * @param BulkLoaderSource $source
     */
    public function setSource(BulkLoaderSource $source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Get the BulkLoaderSource for this BulkLoader
     * @return \AntonyThorpe\Consumer\BulkLoaderSource $source
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set the default behaviour for linking existing relation objects.
     * @param boolean $default
     * @return \AntonyThorpe\Consumer\BulkLoader
     */
    public function setRelationLinkDefault($default)
    {
        $this->relationLinkDefault = $default;
        return $this;
    }

    /**
     * Set the default behaviour for creating new relation objects.
     * @param boolean $default
     * @return \AntonyThorpe\Consumer\BulkLoader
     */
    public function setRelationCreateDefault($default)
    {
        $this->relationCreateDefault = $default;
        return $this;
    }

    /**
     * Set pages to published upon upload
     * @param boolean $default
     * @return \AntonyThorpe\Consumer\BulkLoader
     */
    public function setPublishPages($dopubilsh)
    {
        $this->publishPages = $dopubilsh;
        return $this;
    }

    /**
     * Delete all existing records
     */
    public function deleteExistingRecords()
    {
        DataObject::get($this->objectClass)->removeAll();
    }

    /**
     * Get the DataList of objects this loader applies to.
     * @return \SilverStripe\ORM\DataList
     */
    public function getDataList()
    {
        $class = $this->objectClass;
        return $class::get();
    }

    /**
     * Preview a file import (don't write anything to the database).
     * Useful to analyze the input and give the users a chance to influence
     * it through a UI.
     * @param string $filepath Absolute path to the file we're importing
     * @return array See {@link self::processAll()}
     */
    public function preview($filepath)
    {
        return null;
    }



    /**
     * Start loading of data
     * @param  string  $filepath
     * @param  boolean $preview  Create results but don't write
     * @return \AntonyThorpe\Consumer\BulkLoaderResult
     */
    public function load($filepath = null, $preview = false)
    {
        Environment::increaseTimeLimitTo(3600);
        Environment::increaseMemoryLimitTo('512M');

        $this->mappableFields_cache = $this->getMappableColumns();
        return $this->processAll($filepath, $preview);
    }

    /**
     * Import all records from the source
     * @param  string  $filepath
     * @param  boolean $preview
     * @return \AntonyThorpe\Consumer\BulkLoaderResult
     */
    protected function processAll($filepath, $preview = false)
    {
        if (!$this->source) {
            user_error(
                _t('Consumer.NoSourceForBulkLoader', 'No source has been configured for the bulk loader'),
                E_USER_WARNING
            );
        }
        $results = new BulkLoaderResult();
        $iterator = $this->getSource()->getIterator();
        foreach ($iterator as $record) {
            $this->processRecord($record, $this->columnMap, $results, $preview);
        }

        return $results;
    }


    /**
     * Process a single record from source
     * @param object $record
     * @param array $columnMap
     * @param boolean $preview
     * @param BulkLoaderResult $results
     * @param string $preview set to true to prevent writing to the dataobject
     * @return \AntonyThorpe\Consumer\BulkLoaderResult
     */
    protected function processRecord($record, $columnMap, &$results, $preview = false)
    {
        if (!is_array($record) || empty($record) || !array_filter($record)) {
            $results->addSkipped("Empty/invalid record data.");
            return;
        }

        //map incoming record according to the standardisation mapping (columnMap)
        $record = $this->columnMapRecord($record);
        //skip if required data is not present
        if (!$this->hasRequiredData($record)) {
            $results->addSkipped("Required data is missing.");
            return;
        }
        $modelClass = $this->objectClass;
        $placeholder = new $modelClass();

        //populate placeholder object with transformed data
        foreach ($this->mappableFields_cache as $field => $label) {
            //skip empty fields
            if (!isset($record[$field]) || empty($record[$field])) {
                continue;
            }
            $this->transformField($placeholder, $field, $record[$field]);
        }

        //Next find existing duplicate of placeholder data
        $existing = null;
        $data = $placeholder->getQueriedDatabaseFields();

        if (!$placeholder->ID && !empty($this->duplicateChecks)) {
            $mapped_values = array_values($this->columnMap);
            //don't match on ID, ClassName or RecordClassName
            unset($data['ID'], $data['ClassName'], $data['RecordClassName']);

            // Remove default records if not needed (avoids changing existing properties to their default values)
            $defaults = $placeholder::config()->defaults;
            if ($defaults && is_array($defaults)) {
                foreach (array_keys($defaults) as $default) {
                    if (!in_array($default, $mapped_values)) {
                        unset($data[$default]);
                    }
                }
            }

            $existing = $this->findExistingObject($data);
        }
        if (!empty($existing)) {
            $obj = $existing;
            $obj->update($data);
        } else {
            // new record
            if (!$this->addNewRecords) {
                $results->addSkipped('New record not added');
                return;
            }
            $obj = $placeholder;
        }

        //callback access to every object
        if (method_exists($this, $this->recordCallback)) {
            $callback  = $this->recordCallback;
            $this->{$callback}($obj, $record);
        }

        $changed = $existing && $obj->isChanged();
        //try/catch for potential write() Exception
        try {
            // save update to Results
            if ($existing) {
                if ($changed) {
                    $results->addUpdated($obj, null, $this->duplicateChecks);
                } else {
                    $results->addSkipped("No data was changed.");
                }
            }

            // write obj record
            if (!$preview) {
                $obj->write();

                if ($obj instanceof SiteTree) {
                    if ($obj->isPublished() || $this->publishPages) {
                        $obj->publish('Stage', 'Live');
                    }
                }

                $obj->flushCache(); // avoid relation caching confusion
            }

            // save create to Results
            if (!$existing) {
                $results->addCreated($obj);
            }
        } catch (Exception $e) {
            $results->addSkipped($e->getMessage());
        }

        $objID = $obj->ID;
        // reduce memory usage
        $obj->destroy();
        unset($obj);

        return $objID;
    }

    /**
     * Convert the record's keys to the appropriate columnMap keys
     * @return array record
     */
    protected function columnMapRecord($record)
    {
        $adjustedmap = $this->columnMap;
        $newrecord = array();
        foreach ($record as $field => $value) {
            if (isset($adjustedmap[$field])) {
                $newrecord[$adjustedmap[$field]] = $value;
            } else {
                $newrecord[$field] = $value;
            }
        }

        return $newrecord;
    }

    /**
     * Check if the given mapped record has the required data.
     * @param  array $mappedrecord
     * @return boolean
     */
    protected function hasRequiredData($mappedrecord)
    {
        if (!is_array($mappedrecord) || empty($mappedrecord) || !array_filter($mappedrecord)) {
            return false;
        }
        foreach ($this->transforms as $field => $t) {
            if (
                is_array($t) &&
                isset($t['required']) &&
                $t['required'] === true &&
                (!isset($mappedrecord[$field]) ||
                empty($mappedrecord[$field]))
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Perform field transformation or setting of data on placeholder.
     * @param  \SilverStripe\ORM\DataObject $placeholder
     * @param  string $field
     * @param  mixed $value
     */
    protected function transformField($placeholder, $field, $value)
    {
        $callback = isset($this->transforms[$field]['callback']) &&
                    is_callable($this->transforms[$field]['callback']) ?
                    $this->transforms[$field]['callback'] : null;
        //handle relations
        if ($this->isRelation($field)) {
            $relation = null;
            $relationName = null;
            $columnName = null;
            //extract relationName and columnName, if present
            if (strpos($field, '.') !== false) {
                list($relationName, $columnName) = explode('.', $field);
            } else {
                $relationName = $field;
            }
            //get the list that relation is added to/checked on
            if (isset($this->transforms[$field]['list'])) {
                $relationlist = $this->transforms[$field]['list'];
            } else {
                $relationlist = null;
            }

            //check for the same relation set on the current record
            if ($placeholder->{$relationName."ID"}) {
                $relation = $placeholder->{$relationName}();
                if ($columnName) {
                    $relation->{$columnName} = $value;
                }
            } elseif ($callback) {  //get/make relation via callback
                $relation = $callback($value, $placeholder);
                if ($columnName) {
                    $relation->{$columnName} = $value;
                }
            } elseif ($columnName) { //get/make relation via dot notation
                if ($relationClass = $placeholder->getRelationClass($relationName)) {
                    $relation = $relationClass::get()
                                    ->filter($columnName, $value)
                                    ->first();
                    //create empty relation object
                    //and set the given value on the appropriate column
                    if (!$relation) {
                        $relation = $placeholder->{$relationName}();
                    }
                    //set data on relation
                    $relation->{$columnName} = $value;
                }
            }

            //link and create relation objects
            $linkexisting = isset($this->transforms[$field]['link']) ?
                                (bool)$this->transforms[$field]['link'] :
                                $this->relationLinkDefault;
            $createnew = isset($this->transforms[$field]['create']) ?
                                (bool)$this->transforms[$field]['create'] :
                                $this->relationCreateDefault;
            //ditch relation if we aren't linking
            if (!$linkexisting && $relation && $relation->isInDB()) {
                $relation = null;
            }
            //fail validation gracefully
            try {
                //write relation object, if configured
                if ($createnew && $relation && !$relation->isInDB()) {
                    $relation->write();
                } elseif ($relation && $relation->isInDB() && $relation->isChanged()) {  //write changes to existing relations
                    $relation->write();
                }
                //add relation to relationlist, if it exists
                if ($relationlist && !$relationlist->byID($relation->ID)) {
                    $relationlist->add($relation);
                }
            } catch (ValidationException $e) {
                $relation = null;
            }
            //add the relation id to the placeholder
            if ($relationName && $relation && $relation->exists()) {
                $placeholder->{$relationName."ID"} = $relation->ID;
            }
        } else { //handle data fields
            //transform field value via callback
            //(callback can also update placeholder directly)
            if ($callback) {
                $value = $callback($value, $placeholder);
            }
            //set field value
            $placeholder->update(array(
                $field => $value
            ));
        }
    }

    /**
     * Detect if a given record field is a relation field.
     * @param  string  $field
     * @return boolean
     */
    protected function isRelation($field)
    {
        //get relation name from dot notation
        if (strpos($field, '.') !== false) {
            list($field, $columnName) = explode('.', $field);
        }
        $has_ones = singleton($this->objectClass)->hasOne();
        //check if relation is present in has ones
        return isset($has_ones[$field]);
    }

    /**
     * Given a record field name, find out if this is a relation name
     * and return the name
     * @param string
     * @return string
     */
    protected function getRelationName($recordField)
    {
        $relationName = null;
        if (isset($this->relationCallbacks[$recordField])) {
            $relationName = $this->relationCallbacks[$recordField]['relationname'];
        }
        if (strpos($recordField, '.') !== false) {
            list($relationName, $columnName) = explode('.', $recordField);
        }

        return $relationName;
    }

    /**
     * Find an existing objects based on one or more uniqueness columns
     * specified via {@link self::$duplicateChecks}
     * @param array $record data
     * @return mixed
     */
    public function findExistingObject($record)
    {
        // checking for existing records (only if not already found)
        foreach ($this->duplicateChecks as $fieldName => $duplicateCheck) {
            //plain duplicate checks on fields and relations
            if (is_string($duplicateCheck)) {
                $fieldName = $duplicateCheck;
                //If the dupilcate check is a dot notation, then convert to ID relation
                if (strpos($duplicateCheck, '.') !== false) {
                    list($relationName, $columnName) = explode('.', $duplicateCheck);
                    $fieldName = $relationName."ID";
                }
                //@todo also convert plain relation names to include ID

                //skip current duplicate check if field value is empty
                if (!isset($record[$fieldName]) || empty($record[$fieldName])) {
                    continue;
                }
                $existingRecord = $this->getDataList()
                                    ->filter($fieldName, $record[$fieldName])
                                    ->first();
                if ($existingRecord) {
                    return $existingRecord;
                }
            } elseif (//callback duplicate checks
                is_array($duplicateCheck) &&
                isset($duplicateCheck['callback']) &&
                is_callable($duplicateCheck['callback'])
            ) {
                $callback = $duplicateCheck['callback'];
                if ($existingRecord = $callback($fieldName, $record)) {
                    return $existingRecord;
                }
            } else {
                user_error(
                    _t('Consumer.BulkLoaderWrongFormatDuplicateChecks', 'BulkLoader::processRecord(): Wrong format for $duplicateChecks'),
                    E_USER_WARNING
                );
            }
        }

        return false;
    }

    /**
     * Get the field-label mapping of fields that data can be mapped into.
     * @return array
     */
    public function getMappableColumns()
    {
        if (!empty($this->mappableFields)) {
            return $this->mappableFields;
        }
        $scaffolded = $this->scaffoldMappableFields();
        if (!empty($this->transforms)) {
            $transformables = array_keys($this->transforms);
            $transformables = array_combine($transformables, $transformables);
            $scaffolded = array_merge($transformables, $scaffolded);
        }
        natcasesort($scaffolded);

        return $scaffolded;
    }

    /**
     * Generate a field-label list of fields that data can be mapped into.
     * @param boolean $includerelations
     * @return array
     */
    public function scaffoldMappableFields($includerelations = true)
    {
        $map = $this->getMappableFieldsForClass($this->objectClass);
        //set up 'dot notation' (Relation.Field) style mappings
        if ($includerelations) {
            if ($has_ones = singleton($this->objectClass)->hasOne()) {
                foreach ($has_ones as $relationship => $type) {
                    $fields = $this->getMappableFieldsForClass($type);
                    foreach ($fields as $field => $title) {
                        $map[$relationship.".".$field] =
                            $this->formatMappingFieldLabel($relationship, $title);
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Get the fields and labels for a given class
     * @param  string $class
     * @return array fields
     */
    protected function getMappableFieldsForClass($class)
    {
        $singleton = singleton($class);
        $fields = (array)$singleton->fieldLabels(false);
        foreach ($fields as $field => $label) {
            if (!$singleton->hasField($field)) {
                unset($fields[$field]);
            }
        }
        return $fields;
    }

    /**
     * Format mapping field laabel
     * @param  string $relationship
     * @param  string $title
     */
    protected function formatMappingFieldLabel($relationship, $title)
    {
        return sprintf("%s: %s", $relationship, $title);
    }

    /**
     * Check that the class has the required settings
     */
    public function preprocessChecks()
    {
        // Checks
        if (!$this->objectClass) {
            user_error(
                _t('Consumer.NoObjectClass', 'No objectClass set in the subclass'),
                E_USER_WARNING
            );
        }
        if (!is_array($this->columnMap)) {
            user_error(
                _t('Consumer.NoColumnMap', 'No columnMap set in the subclass'),
                E_USER_WARNING
            );
        }
        if (!is_array($this->duplicateChecks)) {
            user_error(
                _t('Consumer.NoDuplicateChecks', 'No duplicateChecks set in subclass'),
                E_USER_WARNING
            );
        }
    }


    /**
     * Update the dataobject with data from the external API
     *
     * @param array $apidata An array of arrays
     * @param boolean $preview Set to true to not write
     * @return \AntonyThorpe\Consumer\BulkLoaderResult
     */
    public function updateRecords(array $apidata, $preview = false)
    {
        if (is_array($apidata)) {
            $this->setSource(new ArrayBulkLoaderSource($apidata));
        }
        $this->addNewRecords = false;
        $this->preprocessChecks();
        return $this->load(null, $preview);
    }

    /**
     * Update/create many dataobjects with data from the external api
     *
     * @param array $apidata
     * @param boolean $preview Set to true to not write
     * @return BulkLoaderResult
     */
    public function upsertManyRecords(array $apidata, $preview = false)
    {
        if (is_array($apidata)) {
            $this->setSource(new ArrayBulkLoaderSource($apidata));
        }
        $this->preprocessChecks();
        return $this->load(null, $preview);
    }

    /**
     * Delete dataobjects that match to the API data
     *
     * @param array $apidata
     * @param boolean $preview Set to true to not write
     * @return \AntonyThorpe\Consumer\BulkLoaderResult
     */
    public function deleteManyRecords(array $apidata, $preview = false)
    {
        if (is_array($apidata)) {
            $this->setSource(new ArrayBulkLoaderSource($apidata));
        }
        $this->preprocessChecks();
        $this->mappableFields_cache = $this->getMappableColumns();
        $results = new BulkLoaderResult();
        $iterator = $this->getSource()->getIterator();
        foreach ($iterator as $record) {
            //map incoming record according to the standardisation mapping (columnMap)
            $record = $this->columnMapRecord($record);

            // Establish placeholder
            $modelClass = $this->objectClass;
            $placeholder = new $modelClass();

            //populate placeholder object with transformed data
            foreach ($this->mappableFields_cache as $field => $label) {
                //skip empty fields
                if (!isset($record[$field]) || empty($record[$field])) {
                    continue;
                }
                $this->transformField($placeholder, $field, $record[$field]);
            }

            // Obtain data and compare
            $data = $placeholder->getQueriedDatabaseFields();
            $existing = $this->findExistingObject($data);
            if ($existing) {
                $results->addDeleted($existing, 'Record deleted');
                if (!$preview) {
                    $existing->delete();
                }
            } else {
                $results->addSkipped('Record not deleted');
            }
        }
        return $results;
    }

    /**
     * Clear property value if it doesn't exist as a value in the API data
     * Note: only use with full set of API data otherwise values will be cleared in the dataobject that should be left
     * @param  array   $apidata An array of arrays
     * @param  string  $key The key that matches the column within the Object Class
     * @param  string  $property_name The property name of the class that matches to the key from the API data
     * @param  boolean $preview Set to true to not save
     * @return \AntonyThorpe\Consumer\BulkLoaderResult
     */
    public function clearAbsentRecords(array $apidata, $key, $property_name, $preview = false)
    {
        $results = new BulkLoaderResult();
        $modelClass = $this->objectClass;

        foreach ($modelClass::get() as $record) {
            $property_value = $record->{$property_name};

            if (!empty($property_value)) {
                $match = array_filter(
                    $apidata,
                    function ($value) use ($key, $property_value) {
                        return $value[$key] == $property_value;
                    }
                );

                if (empty($match)) { // The property value doesn't exist in the API data (therefore clear)
                    $record->{$property_name} = "";
                    $results->addUpdated($record, _t('Consumer.CLEARABSENT', 'Clear absent'), $this->duplicateChecks);
                    if (!$preview) {
                        $record->write();
                    }
                }
            }
        }
        return $results;
    }
}
