<?php

namespace AntonyThorpe\Consumer;

use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

/**
 * Store result information about a BulkLoader import
 */
class BulkLoaderResult extends \SilverStripe\Dev\BulkLoader_Result
{
    /**
     * Keep track of skipped records.
     * @var array
     */
    protected $skipped = array();

    /**
     * @return int
     */
    public function SkippedCount()
    {
        return count($this->skipped);
    }


    /**
     * @param string $message Reason for skipping
     */
    public function addSkipped($message = null)
    {
        $this->skipped[] = array(
            'Message' => $message
        );
    }

    /**
     * Get an array of messages describing the result.
     * @return array messages
     */
    public function getMessageList()
    {
        $output =  array();
        if ($this->CreatedCount()) {
            $output['created'] = _t(
                'SilverStripe\\Dev\\BulkLoader.IMPORTEDRECORDS',
                "Imported {count} new records.",
                array('count' => $this->CreatedCount())
            );
        }
        if ($this->UpdatedCount()) {
            $output['updated'] = _t(
                'SilverStripe\\Dev\\BulkLoader.UPDATEDRECORDS',
                "Updated {count} records.",
                array('count' => $this->UpdatedCount())
            );
        }
        if ($this->DeletedCount()) {
            $output['deleted'] =  _t(
                'SilverStripe\\Dev\\BulkLoader.DELETEDRECORDS',
                "Deleted {count} records.",
                array('count' => $this->DeletedCount())
            );
        }
        if ($this->SkippedCount()) {
            $output['skipped'] =  _t(
                'SilverStripe\\Dev\\BulkLoader.SKIPPEDRECORDS',
                "Skipped {count} bad records.",
                array('count' => $this->SkippedCount())
            );
        }

        if (!$this->CreatedCount() && !$this->UpdatedCount()) {
            $output['empty'] = _t('SilverStripe\\Dev\\BulkLoader.NOIMPORT', "Nothing to import");
        }

        return $output;
    }

    /**
     * Genenrate a human-readable result message.
     *
     * @return string
     */
    public function getMessage()
    {
        return implode("\n", $this->getMessageList());
    }

    /**
     * Provide a useful message type, based on result.
     * @return string
     */
    public function getMessageType()
    {
        $type = "bad";
        if ($this->Count()) {
            $type = "good";
        }
        if ($this->SkippedCount()) {
            $type= "warning";
        }

        return $type;
    }

    /**
     * Returns all created objects. Each object might
     * contain specific importer feedback in the "_BulkLoaderMessage" property.
     *
     * @return ArrayList
     */
    public function getCreated()
    {
        return $this->mapToArrayList($this->created);
    }

    /**
     * Return all updated objects
     *
     * @return ArrayList
     */
    public function getUpdated()
    {
        $set = new ArrayList();
        foreach ($this->updated as $arrItem) {
            $set->push(ArrayData::create($arrItem));
        }
        return $set;
    }

    /**
     * Return all deleted objects
     *
     * @return ArrayList
     */
    public function getDeleted()
    {
        $set = new ArrayList();
        foreach ($this->deleted as $arrItem) {
            $set->push(ArrayData::create($arrItem));
        }
        return $set;
    }

    /**
     * Prepare the boby for an email or build task
     *
     * @return array
     */
    public function getData()
    {
        $data = new ArrayList();
        if ($this->CreatedCount()) {
            $data->push(ArrayData::create(array("Title" => _t('Consumer.CREATED', 'Created'))));
            $data->merge($this->getCreated());
        }
        if ($this->UpdatedCount()) {
            $data->push(ArrayData::create(array("Title" => _t('Consumer.UPDATED', 'Updated'))));
            $data->merge($this->getUpdated());
        }
        if ($this->DeletedCount()) {
            $data->push(ArrayData::create(array("Title" => _t('Consumer.DELETED', 'Deleted'))));
            $data->merge($this->$this->getDeleted());
        }
        return $data;
    }

    /**
     * @param $obj DataObject
     * @param $message string
     */
    public function addCreated($obj, $message = null)
    {
        $this->created[] = $this->lastChange = $this->createResult($obj, $message);
        $this->lastChange['ChangeType'] = 'created';
    }

    /**
     * @param $obj DataObject
     * @param $message string
     */
    public function addUpdated($obj, $message = null, $additionalFields = null)
    {
        if ($changedFields = $this->getChangedFields($obj)) {
            // create additional fields to include with results
            $extra_data = array();
            foreach ($additionalFields as $field) {
                $extra_data[$field] = $obj->{$field};
            }
            $extra_data['_ChangedFields'] = $changedFields;
            $base = array(
                'ID' => $obj->ID,
                'ClassName' => $obj->class
            );
            if ($message) {
                $base['Message'] = $message;
            }
            $this->updated[] = $this->lastChange = array_merge(
                $base,
                $extra_data
            );
            $this->lastChange['ChangeType'] = 'updated';
        }
    }

    /**
     * Modelled on the getChangedFields of DataObject, with the addition of the variable's type
     * @param  Dataobject $obj
     * @return array The before/after changes of each field
     */
    protected function getChangedFields($obj)
    {
        $changedFields = $obj->getChangedFields(true, 2);
        foreach ($changedFields as $key => $value) {
            $changedFields[$key]['before'] = '(' . gettype($value['before']) . ') ' . $value['before'];
            $changedFields[$key]['after'] = '(' . gettype($value['after']) . ') ' . $value['after'];
            unset($changedFields[$key]['level']);
        }
        return $changedFields;
    }

    /**
     * @param $obj DataObject
     * @param $message string
     */
    public function addDeleted($obj, $message = null)
    {
        $this->deleted[] = $this->lastChange = $this->createResult($obj, $message);
        $this->lastChange['ChangeType'] = 'deleted';
    }

    /**
     * Create the Result for Deleted and Created items
     * @param  Dataobject $obj
     * @param  string $message
     * @return array
     */
    protected function createResult($obj, $message)
    {
        $data = $obj->toMap();
        $data['_BulkLoaderMessage'] = $message;
        return $data;
    }

    /**
     * @param $arr array Either the created, updated or deleted items
     * @return ArrayList
     */
    protected function mapToArrayList($arr)
    {
        $set = new ArrayList();
        foreach ($arr as $arrItem) {
            $set->push(ArrayData::create($arrItem));
        }
        return $set;
    }
}
