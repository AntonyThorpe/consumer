<?php

namespace AntonyThorpe\Consumer;

use SilverStripe\Dev\BulkLoader_Result;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

/**
 * Store result information about a BulkLoader import
 */
class BulkLoaderResult extends BulkLoader_Result
{
    /**
     * Keep track of skipped records.
     */
    protected array $skipped = [];

    public function SkippedCount(): int
    {
        return count($this->skipped);
    }

    /**
     * Reason for skipping
     */
    public function addSkipped(string $message = ''): void
    {
        $this->skipped[] = [
            'Message' => $message
        ];
    }

    /**
     * Get an array of messages describing the result
     */
    public function getMessageList(): array
    {
        $output =  [];
        if ($this->CreatedCount()) {
            $output['created'] = _t(
                'SilverStripe\\Dev\\BulkLoader.IMPORTEDRECORDS',
                "Imported {count} new records.",
                ['count' => $this->CreatedCount()]
            );
        }
        if ($this->UpdatedCount()) {
            $output['updated'] = _t(
                'SilverStripe\\Dev\\BulkLoader.UPDATEDRECORDS',
                "Updated {count} records.",
                ['count' => $this->UpdatedCount()]
            );
        }
        if ($this->DeletedCount()) {
            $output['deleted'] =  _t(
                'SilverStripe\\Dev\\BulkLoader.DELETEDRECORDS',
                "Deleted {count} records.",
                ['count' => $this->DeletedCount()]
            );
        }
        if ($this->SkippedCount() !== 0) {
            $output['skipped'] =  _t(
                'SilverStripe\\Dev\\BulkLoader.SKIPPEDRECORDS',
                "Skipped {count} bad records.",
                ['count' => $this->SkippedCount()]
            );
        }

        if (!$this->CreatedCount() && !$this->UpdatedCount()) {
            $output['empty'] = _t('SilverStripe\\Dev\\BulkLoader.NOIMPORT', "Nothing to import");
        }

        return $output;
    }

    /**
     * Genenrate a human-readable result message
     */
    public function getMessage(): string
    {
        return implode("\n", $this->getMessageList());
    }

    /**
     * Provide a useful message type, based on result
     */
    public function getMessageType(): string
    {
        $type = "bad";
        if ($this->Count() !== 0) {
            $type = "good";
        }
        if ($this->SkippedCount() !== 0) {
            $type= "warning";
        }

        return $type;
    }

    /**
     * Returns all created objects. Each object might
     * contain specific importer feedback in the "_BulkLoaderMessage" property.
     */
    public function getCreated(): ArrayList
    {
        return $this->mapToArrayList($this->created);
    }

    /**
     * Return all updated objects
     */
    public function getUpdated(): ArrayList
    {
        $set = ArrayList::create();
        foreach ($this->updated as $arrItem) {
            $set->push(ArrayData::create($arrItem));
        }
        return $set;
    }

    /**
     * Return all deleted objects
     */
    public function getDeleted(): ArrayList
    {
        $set = ArrayList::create();
        foreach ($this->deleted as $arrItem) {
            $set->push(ArrayData::create($arrItem));
        }
        return $set;
    }

    /**
     * Prepare the boby for an email or build task
     */
    public function getData(): ArrayList
    {
        $data = ArrayList::create();
        if ($this->CreatedCount()) {
            $data->push(ArrayData::create(["Title" => _t('Consumer.CREATED', 'Created')]));
            $data->merge($this->getCreated());
        }
        if ($this->UpdatedCount()) {
            $data->push(ArrayData::create(["Title" => _t('Consumer.UPDATED', 'Updated')]));
            $data->merge($this->getUpdated());
        }
        if ($this->DeletedCount()) {
            $data->push(ArrayData::create(["Title" => _t('Consumer.DELETED', 'Deleted')]));
            $data->merge($this->$this->getDeleted());
        }
        return $data;
    }

    /**
     * @param DataObject $obj
     * @param string $message
     */
    public function addCreated($obj, $message = ''): void
    {
        $this->created[] = $this->lastChange = $this->createResult($obj, $message);
        $this->lastChange['ChangeType'] = 'created';
    }

    /**
     * @param DataObject $obj
     * @param string $message
     */
    public function addUpdated($obj, $message = '', $additionalFields = null): void
    {
        if ($changedFields = $this->getChangedFields($obj)) {
            // create additional fields to include with results
            $extra_data = [];
            foreach ($additionalFields as $field) {
                $extra_data[$field] = $obj->{$field};
            }
            $extra_data['_ChangedFields'] = $changedFields;
            $base = [
                'ID' => $obj->ID,
                'ClassName' => $obj->class
            ];
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
     */
    protected function getChangedFields(DataObject $obj): array
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
     * @param DataObject $obj
     * @param string $message
     */
    public function addDeleted($obj, $message = ''): void
    {
        $this->deleted[] = $this->lastChange = $this->createResult($obj, $message);
        $this->lastChange['ChangeType'] = 'deleted';
    }

    /**
     * Create the Result for Deleted and Created items
     */
    protected function createResult(DataObject $obj, string $message = ''): array
    {
        $data = $obj->toMap();
        $data['_BulkLoaderMessage'] = $message;
        return $data;
    }

    /**
     * @param $arr array Either the created, updated or deleted items
     */
    protected function mapToArrayList($arr): ArrayList
    {
        $set = ArrayList::create();
        foreach ($arr as $arrItem) {
            $set->push(ArrayData::create($arrItem));
        }
        return $set;
    }
}
