<?php
/**
 * The bulk loader allows large-scale uploads to SilverStripe via the ORM.
 *
 * Data comes from a given BulkLoaderSource, providing an iterator of records.
 *
 * Incoming data can be mapped to fields, based on a given mapping,
 * and it can be used to find or create related objects to be linked to.
 *
 * Failed record imports will be marked as skipped
 */
class ConsumerBulkLoader extends BetterBulkLoader
{
    /**
     * Determines whether pages should be published during loading
     * @var boolean
     */
    protected $publishPages = true;

    /**
     * Add new records while importing
     * @var Boolean
     */
    protected $addNewRecords = true;

    /**
     * Start loading of data
     * @param  string  $filepath
     * @param  boolean $preview  Create results but don't write
     * @return ConsumerBulkLoaderResult
     */
    public function load($filepath = null, $preview = false)
    {
        $this->mappableFields_cache = $this->getMappableColumns();
        return $this->processAll($filepath, $preview);
    }

    /**
     * Import all records from the source
     * Overrides BetterBulkLoader to add a call to ConsumerBulkLoaderResult
     *
     * @param  string  $filepath
     * @param  boolean $preview
     * @return ConsumerBulkLoaderResult
     */
    protected function processAll($filepath, $preview = false)
    {
        if (!$this->source) {
            user_error(
                "No source has been configured for the bulk loader",
                E_USER_WARNING
            );
        }
        $results = new ConsumerBulkLoaderResult();
        $iterator = $this->getSource()->getIterator();
        foreach ($iterator as $record) {
            $this->processRecord($record, $this->columnMap, $results, $preview);
        }

        return $results;
    }


    /**
     * Import all records from the source
     *
     * @param  object $record
     * @param array $columnMap
     * @param  boolean $preview
     * @param ConsumerBulkLoaderResult $results
     * @param string $preview set to true to not write
     * @return ConsumerBulkLoaderResult
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
        //find existing duplicate of placeholder data
        $existing = null;
        if (!$placeholder->ID && !empty($this->duplicateChecks)) {
            $data = $placeholder->getQueriedDatabaseFields();
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
        unset($existingObj);
        unset($obj);

        return $objID;
    }


    /**
     * Check that the class has the required settings
     */
    public function preprocessChecks()
    {
        // Checks
        if (!$this->objectClass) {
            user_error(
                "No objectClass set in the subclass",
                E_USER_WARNING
            );
        }
        if (!is_array($this->columnMap)) {
            user_error(
                "No columnMap set in the subclass",
                E_USER_WARNING
            );
        }
        if (!is_array($this->duplicateChecks)) {
            user_error(
                "No duplicateChecks set in subclass",
                E_USER_WARNING
            );
        }
    }


    /**
     * Update the dataobject with data from the external API
     *
     * @param array $apidata An array of arrays
     * @param boolean $preview Set to true to not write
     * @return ConsumerBulkLoaderResult
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
     * @return ConsumerBulkLoaderResult
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
     * @return ConsumerBulkLoaderResult
     */
    public function deleteManyRecords(array $apidata, $preview = false)
    {
        if (is_array($apidata)) {
            $this->setSource(new ArrayBulkLoaderSource($apidata));
        }
        $this->preprocessChecks();
        $this->mappableFields_cache = $this->getMappableColumns();

        $results = new ConsumerBulkLoaderResult();
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
     *
     * Note: only use with full set of API data otherwise values will be cleared in the dataobject that should be left
     * @param  array   $apidata      An array of arrays
     * @param  string  $key          The key that matches the column within the Object Class
     * @param  string  $property_name The property name of the class that matches to the key from the API data
     * @param  boolean $preview      Set to true to not save
     * @return ConsumerBulkLoaderResult
     */
    public function clearAbsentRecords(array $apidata, $key, $property_name, $preview = false)
    {
        $results = new ConsumerBulkLoaderResult();
        $modelClass = $this->objectClass;

        foreach ($modelClass::get() as $record) {
            $property_value = $record->{$property_name};

            if (!empty($property_value)) {
                $match = array_filter(
                    $apidata,
                    function ($value) use ($record, $key, $property_value) {
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
