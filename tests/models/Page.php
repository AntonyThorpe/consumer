<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Page
 *
 * Load javascript and CSS
 */
class Page extends SiteTree implements TestOnly
{
    /**
     * Set font symbol for menus
     * @var array
     */
    private static $db = array(
    );
}
