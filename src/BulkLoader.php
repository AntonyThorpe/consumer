<?php

namespace AntonyThorpe\Consumer;

use SilverStripe\ORM\DataList;
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
    public $mappableFields = [];

    /**
     * Transformation and relation handling
     * @var array
     */
    public $transforms = [];

    /**
     * Specify a colsure to be run on every imported record.
     * @var callable
     */
    public $recordCallback;

    /**
     * Bulk loading source
     */
    protected BulkLoaderSource $source;

    /**
     * Add new records while importing
     */
    protected bool $addNewRecords = true;

    /**
     * The default behaviour for linking relations
     */
    protected bool $relationLinkDefault = true;

    /**
     * The default behaviour creating relations
     */
    protected bool $relationCreateDefault = true;

    /**
     * Determines whether pages should be published during loading
     */
    protected bool $publishPages = true;

    /**
     * Cache the result of getMappableColumns
     * @var array
     */
    protected $mappableFields_cache;

    /**
     * Set the BulkLoaderSource for this BulkLoader.
     */
    public function setSource(BulkLoaderSource $source) :static
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Get the BulkLoaderSource for this BulkLoader
     */
    public function getSource(): BulkLoaderSource
    {
        return $this->source;
    }

    /**
     * Set the default behaviour for linking existing relation objects.
     */
    public function setRelationLinkDefault(bool $default): self
    {
        $this->relationLinkDefault = $default;
        return $this;
    }

    /**
     * Set the default behaviour for creating new relation objects.
     */
    public function setRelationCreateDefault(bool $default): self
    {
        $this->relationCreateDefault = $default;
        return $this;
    }

    /**
     * Set pages to published upon upload
     */
    public function setPublishPages(bool $dopubilsh): self
    {
        $this->publishPages = $dopubilsh;
        return $this;
    }

    /**
     * Delete all existing records
     */
    public function deleteExistingRecords(): void
    {
        DataObject::get($this->objectClass)->removeAll();
    }

    /**
     * Get the DataList of objects this loader applies to.
     */
    public function getDataList(): DataList
    {
        $class = $this->objectClass;
        return $class::get();
    }

    /**
     * Preview a file import (don't write anything to the database).
     * Useful to analyze the input and give the users a chance to influence
     * it through a UI.
     * @param string $filepath Absolute path to the file we're importing
     * See {@link self::processAll()}
     */
    public function preview($filepath): array
    {
        return [];
    }

    /**
     * Start loading of data
     * @param  string  $filepath
     * @param  bool $preview  Create results but don't write
     * @return BulkLoaderResult
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
     * @param  string $filepath
     * @param  bool $preview
     */
    protected function processAll($filepath, $preview = false): BulkLoaderResult
    {
        if (!$this->source) {
            user_error(
                _t('Consumer.NoSourceForBulkLoader', 'No source has been configured for the bulk loader'),
                E_USER_WARNING
            );
        }
        $results = BulkLoaderResult::create();
        $iterator = $this->getSource()->getIterator();
        foreach ($iterator as $record) {
            $this->processRecord($record, $this->columnMap, $results, $preview);
        }

        return $results;
    }

    /**
     * Process a single record from source
     * @param array $record An map of the data, keyed by the header field defined in {@link self::$columnMap}
     * @param array $columnMap
     * @param BulkLoaderResult $results
     * @param bool $preview set to true to prevent writing to the dataobject
     * @return BulkLoaderResult|null
     */
    protected function processRecord($record, $columnMap, &$results, $preview = false)
    {
        if (!is_array($record) || $record === [] || !array_filter($record)) {
            $results->addSkipped("Empty/invalid record data.");
            return null;
        }

        //map incoming record according to the standardisation mapping (columnMap)
        $record = $this->columnMapRecord($record);
        //skip if required data is not present
        if (!$this->hasRequiredData($record)) {
            $results->addSkipped("Required data is missing.");
            return null;
        }
        $modelClass = $this->objectClass;
        $placeholder = new $modelClass();

        //populate placeholder object with transformed data
        foreach (array_keys($this->mappableFields_cache) as $field) {
            //skip empty fields
            if (!isset($record[$field]) || empty($record[$field])) {
                continue;
            }
            $this->transformField($placeholder, $field, $record[$field]);
        }

        //Next find existing duplicate of placeholder data
        $existing = null;
        $data = $placeholder->getQueriedDatabaseFields();

        if (!$placeholder->ID && $this->duplicateChecks !== []) {
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
                return null;
            }
            $obj = $placeholder;
        }

        //callback access to every object
        if ($this->recordCallback && method_exists($this, $this->recordCallback)) {
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

                if ($obj instanceof SiteTree && ($obj->isPublished() || $this->publishPages)) {
                    $obj->copyVersionToStage('Stage', 'Live');
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
     */
    protected function columnMapRecord(array $record): array
    {
        $adjustedmap = $this->columnMap;
        $newrecord = [];
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
     */
    protected function hasRequiredData(array $mappedrecord): bool
    {
        if (!is_array($mappedrecord) || $mappedrecord === [] || !array_filter($mappedrecord)) {
            return false;
        }
        foreach ($this->transforms as $field => $t) {
            if (is_array($t) &&
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
     */
    protected function transformField(DataObject $placeholder, string $field, mixed $value): void
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
            if (str_contains($field, '.')) {
                [$relationName, $columnName] = explode('.', $field);
            } else {
                $relationName = $field;
            }
            //get the list that relation is added to/checked on
            $relationlist = $this->transforms[$field]['list'] ?? null;

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
            } catch (ValidationException) {
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
            $placeholder->update([
                $field => $value
            ]);
        }
    }

    /**
     * Detect if a given record field is a relation field.
     */
    protected function isRelation(string $field): bool
    {
        //get relation name from dot notation
        if (str_contains($field, '.')) {
            [$field, $columnName] = explode('.', $field);
        }
        $has_ones = DataObject::singleton($this->objectClass)->hasOne();
        //check if relation is present in has ones
        return isset($has_ones[$field]);
    }

    /**
     * Given a record field name, find out if this is a relation name
     * and return the name
     */
    protected function getRelationName(string $recordField): string
    {
        $relationName = null;
        if (isset($this->relationCallbacks[$recordField])) {
            $relationName = $this->relationCallbacks[$recordField]['relationname'];
        }
        if (str_contains((string) $recordField, '.')) {
            [$relationName, $columnName] = explode('.', (string) $recordField);
        }

        return $relationName;
    }

    /**
     * Find an existing objects based on one or more uniqueness columns
     * specified via {@link self::$duplicateChecks}
     */
    public function findExistingObject(array $record): mixed
    {
        // checking for existing records (only if not already found)
        foreach ($this->duplicateChecks as $fieldName => $duplicateCheck) {
            //plain duplicate checks on fields and relations
            if (is_string($duplicateCheck)) {
                $fieldName = $duplicateCheck;
                //If the dupilcate check is a dot notation, then convert to ID relation
                if (str_contains($duplicateCheck, '.')) {
                    [$relationName, $columnName] = explode('.', $duplicateCheck);
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
     */
    public function getMappableColumns(): array
    {
        if ($this->mappableFields !== []) {
            return $this->mappableFields;
        }
        $scaffolded = $this->scaffoldMappableFields();
        if ($this->transforms !== []) {
            $transformables = array_keys($this->transforms);
            $transformables = array_combine($transformables, $transformables);
            $scaffolded = array_merge($transformables, $scaffolded);
        }
        natcasesort($scaffolded);

        return $scaffolded;
    }

    /**
     * Generate a field-label list of fields that data can be mapped into.
     */
    public function scaffoldMappableFields(bool $includerelations = true): array
    {
        $map = $this->getMappableFieldsForClass($this->objectClass);
        //set up 'dot notation' (Relation.Field) style mappings
        if ($includerelations && ($has_ones = DataObject::singleton($this->objectClass)->hasOne())) {
            foreach ($has_ones as $relationship => $type) {
                $fields = $this->getMappableFieldsForClass($type);
                foreach ($fields as $field => $title) {
                    $map[$relationship.".".$field] =
                        $this->formatMappingFieldLabel($relationship, $title);
                }
            }
        }

        return $map;
    }

    /**
     * Get the fields and labels for a given class
     */
    protected function getMappableFieldsForClass(string $class): array
    {
        $singleton = DataObject::singleton($class);
        $fields = (array)$singleton->fieldLabels(false);
        foreach (array_keys($fields) as $field) {
            if (!$singleton->hasField($field)) {
                unset($fields[$field]);
            }
        }
        return $fields;
    }

    /**
     * Format mapping field laabel
     */
    protected function formatMappingFieldLabel(string $relationship, string $title): string
    {
        return sprintf("%s: %s", $relationship, $title);
    }

    /**
     * Check that the class has the required settings
     */
    public function preprocessChecks(): void
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
     */
    public function updateRecords(array $apidata, bool $preview = false): BulkLoaderResult
    {
        $this->setSource(new ArrayBulkLoaderSource($apidata));
        $this->addNewRecords = false;
        $this->preprocessChecks();
        return $this->load(null, $preview);
    }

    /**
     * Update/create many dataobjects with data from the external api
     */
    public function upsertManyRecords(array $apidata, bool $preview = false): BulkLoaderResult
    {
        $this->setSource(new ArrayBulkLoaderSource($apidata));
        $this->preprocessChecks();
        return $this->load(null, $preview);
    }

    /**
     * Delete dataobjects that match to the API data
     */
    public function deleteManyRecords(array $apidata, bool $preview = false): BulkLoaderResult
    {
        $this->setSource(new ArrayBulkLoaderSource($apidata));
        $this->preprocessChecks();
        $this->mappableFields_cache = $this->getMappableColumns();
        $results = BulkLoaderResult::create();
        $iterator = $this->getSource()->getIterator();
        foreach ($iterator as $record) {
            //map incoming record according to the standardisation mapping (columnMap)
            $record = $this->columnMapRecord($record);

            // Establish placeholder
            $modelClass = $this->objectClass;
            $placeholder = new $modelClass();

            //populate placeholder object with transformed data
            foreach (array_keys($this->mappableFields_cache) as $field) {
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
     * @param  bool $preview Set to true to skip saving
     * @return BulkLoaderResult
     */
    public function clearAbsentRecords(array $apidata, string $key, string $property_name, bool $preview = false)
    {
        $results = BulkLoaderResult::create();
        $modelClass = $this->objectClass;

        foreach ($modelClass::get() as $record) {
            $property_value = $record->{$property_name};
            if (!empty($property_value)) {
                $match = array_filter(
                    $apidata,
                    fn($value): bool => $value[$key] == $property_value
                );
                if ($match === []) { // The property value doesn't exist in the API data (therefore clear)
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
