<?php

namespace AntonyThorpe\Consumer;

use SilverStripe\ORM\DataObject;
use DateTime;
use Exception;

/**
 * Record the LastEdited date provided from external data
 * @property string $Title provides a name against the API call
 * @property string $ExternalLastEditedKey is the key that labels the last date the call was made
 * @property Datetime $ExternalLastEdited is the last modified date from the external API data
 */
class Consumer extends DataObject
{
    /**
     * @config
     */
    private static string $table_name = 'Consumer';

    /**
     * Save the maximum data using setExternalLastEdited method.  Can use it to filter future calls to the API.
     * @config
     */
    private static array $db = [
        'Title' => 'Varchar(250)',
        'ExternalLastEditedKey' => 'Varchar(100)',
        'ExternalLastEdited' => 'Datetime'
    ];

    /**
     * Convert a Unix date to a UTC
     */
    public static function convertUnix2UTC(string $data): string
    {
        $string = preg_replace('/\D/', '', $data);
        $date = new DateTime();

        if (strlen($string) > 10) {
            $date->setTimestamp(intval($string/1000));  // Unix date with milliseconds
        } else {
            $date->setTimestamp(intval($string));
        }

        return $date->format('Y-m-d\TH:i:s.u');
    }

    /**
     * Determine if the string is a Unix Timestamp
     * @link(Stack Overflow, http://stackoverflow.com/questions/2524680/check-whether-the-string-is-a-unix-timestamp)
     */
    public static function isTimestamp(string $string): bool
    {
        if (str_starts_with($string, "/Date")) {
            return true;
        }

        try {
            new DateTime('@' . $string);
        } catch (Exception) {
            return false;
        }
        return true;
    }

    /**
     * Set the ExternalLastEdited to the maximum last edited date
     */
    public function setMaxExternalLastEdited(array $apidata): static
    {
        $external_last_edited_key = $this->ExternalLastEditedKey;

        // Validation
        if (!$external_last_edited_key) {
            user_error(
                _t('Consumer.ExternalLastEditedKeyNeeded', 'Property ExternalLastEditedKey needs to be set before calling setMaxExternalLastEdited method'),
                E_USER_WARNING
            );
        }

        $dates = array_map(fn($item) => $item[$external_last_edited_key], $apidata);
        $max_date = max($dates);

        if (self::isTimestamp($max_date)) {
            $max_date = self::convertUnix2UTC($max_date);
        }

        $this->ExternalLastEdited = $max_date;
        return $this;
    }
}
