<?php

namespace XD\Shopify\Model;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataObject;
use XD\Shopify\Control\ShopifyPageController;

/**
 * Class ShopifyPage
 *
 * @author Bram de Leeuw
 * @package XD\Shopify\Model
 *
 * @property int PageLimit
 * @property string ChildrenClass
 */
class ShopifyPage extends \Page
{
    private static $table_name = 'ShopifyPage';

    private static $children_classes = [
        Collection::class => 'Collections',
        Product::class => 'Products'
    ];

    private static $db = [
        'PageLimit' => 'Int',
        'ChildrenClass' => 'Varchar'
    ];

    private static $defaults = [
        'PageLimit' => 10,
        'ChildrenClass' => Collection::class
    ];

    private static $controller_name = ShopifyPageController::class;
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getSettingsFields()
    {
        $fields = parent::getSettingsFields();
        $fields->addFieldsToTab('Root.Settings', [
            DropdownField::create('ChildrenClass', 'Display child pages', self::config()->get('children_classes')),
            NumericField::create('PageLimit')
        ]);

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
