<?php

namespace XD\Shopify\Model;

use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataObject;

/**
 * Class ShopifyPage
 *
 * @author Bram de Leeuw
 * @package XD\Shopify\Model
 *
 * @property int PageLimit
 */
class ShopifyPage extends \Page
{
    private static $table_name = 'ShopifyPage';

    private static $db = [
        'PageLimit' => 'Int'
    ];

    private static $defaults = [
        'PageLimit' => 10
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getSettingsFields()
    {
        $fields = parent::getSettingsFields();
        $fields->add(
            NumericField::create('PageLimit')
        );

        return $fields;
    }

    /**
     * Return instance of self
     *
     * @return null|DataObject|ShopifyPage
     */
    public static function inst()
    {
        return DataObject::get_one(self::class);
    }

    /**
     * Can only create one of self
     *
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = array())
    {
        return !self::inst() && parent::canCreate($member = null, $context = array());
    }
}
