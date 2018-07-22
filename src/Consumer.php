<?php

namespace AntonyThorpe\Consumer;

use SilverStripe\ORM\DataObject;
use DateTime;
use Exception;

/**
 * Consumer
 *
 * Record the LastEdited provided from external data
 */
class Consumer extends DataObject
{
    private static $table_name = 'Consumer';

    /**
     * ExternalLastEdited is the last modified date from the external API data.
     * Save the maximum data using setExternalLastEdited method.  Can use it to filter future calls to the API.
     * @var array
     */
    private static $db = array(
        'Title' => 'Varchar(250)',
        'ExternalLastEditedKey' => 'Varchar(100)',
        'ExternalLastEdited' => 'Datetime'
    );

    public static function convertUnix2UTC($data)
    {
        $string = preg_replace('/\D/', '', $data);
        $date = new DateTime();

        if (strlen($string) > 10) {
            $date->setTimestamp($string/1000);  // Unix date with milliseconds
        } else {
            $date->setTimestamp($string);
        }

        return $date->format('Y-m-d\TH:i:s.u');
    }

    /**
     * Determine if the string is a Unix Timestamp
     *
     * @link(Stack Overflow, http://stackoverflow.com/questions/2524680/check-whether-the-string-is-a-unix-timestamp)
     * @param  string  $string
     * @return boolean
     */
    public static function isTimestamp($string)
    {
        if (substr($string, 0, 5) == "/Date") {
            return true;
        }

        try {
            new DateTime('@' . $string);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Set the ExternalLastEdited to the maximum last edited date
     *
     * @param array $apidata
     * @return $this
     */
    public function setMaxExternalLastEdited(array $apidata)
    {
        $external_last_edited_key = $this->ExternalLastEditedKey;

        // Validation
        if (!$external_last_edited_key) {
            user_error(
                "Property ExternalLastEditedKey needs to be set before calling setMaxExternalLastEdited method",
                E_USER_WARNING
            );
        }

        $dates = array_map(function ($item) use ($external_last_edited_key) {
            return $item[$external_last_edited_key];
        }, $apidata);
        $max_date = max($dates);

        if (self::isTimestamp($max_date)) {
            $max_date = self::convertUnix2UTC($max_date);
        }

        $this->ExternalLastEdited = $max_date;
        return $this;
    }
}
