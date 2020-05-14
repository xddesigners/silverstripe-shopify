<?php

namespace XD\Shopify\Model;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\TagField\TagField;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\FieldType\DBCurrency;
use XD\Shopify\Task\Import;

/**
 * Class Collection
 *
 * @author Bram de Leeuw
 * @package XD\Shopify
 * @subpackage Model
 *
 * @mixin Versioned
 *
 * @property string Title
 * @property string URLSegment
 * @property string ShopifyID
 * @property string Content
 *
 * @property int ImageID
 * @method Image Image()
 *
 * @method ManyManyList Products()
 */
class Collection extends DataObject
{
    private static $table_name = 'ShopifyCollection';

    private static $db = [
        'Title' => 'Varchar',
        'URLSegment' => 'Varchar',
        'ShopifyID' => 'Varchar',
        'Content' => 'HTMLText'
    ];

    private static $data_map = [
        'id' => 'ShopifyID',
        'handle' => 'URLSegment',
        'title' => 'Title',
        'body_html' => 'Content',
        'updated_at' => 'LastEdited',
        'created_at' => 'Created',
    ];

    private static $has_one = [
        'Image' => Image::class
    ];

    private static $many_many = [
        'Products' => Product::class,
    ];


    private static $many_many_extraFields = [
        'Products' => [
            'SortValue' => 'Varchar',
            'Position' => 'Int',
            'Featured' => 'Boolean',
            'Imported' => 'Boolean'
        ],
    ];

    private static $owns = [
        'Image'
    ];

    private static $indexes = [
        'ShopifyID' => true,
        'URLSegment' => true
    ];

    private static $summary_fields = [
        'Image.CMSThumbnail' => 'Image',
        'Title',
        'ShopifyID'
    ];

    private static $extensions = [
        Versioned::class
    ];

    public function getCMSFields()
    {
        $self =& $this;
        $this->beforeUpdateCMSFields(function (FieldList $fields) use ($self) {
            $fields->addFieldsToTab('Root.Main', [
                ReadonlyField::create('Title'),
                ReadonlyField::create('URLSegment'),
                ReadonlyField::create('ShopifyID'),
                ReadonlyField::create('Content'),
                UploadField::create('Image')->performReadonlyTransformation(),
            ]);

            $fields->addFieldsToTab('Root.Products', [
                GridField::create('Products', 'Products', $this->Products(), GridFieldConfig_RecordViewer::create())
            ]);
        });
        
        $fields = parent::getCMSFields();
        $fields->removeByName(['LinkTracking', 'FileTracking']);
        return $fields;
    }

    public function Link($action = null)
    {
        $shopifyPage = ShopifyPage::inst();
        return Controller::join_links($shopifyPage->Link('collection'), $this->URLSegment, $action);
    }

    public function AbsoluteLink($action = null) {
        return Director::absoluteURL($this->Link($action));
    }

    /**
     * Creates a new Shopify Collection from the given data
     * but does not publish it
     *
     * @param $shopifyCollection
     * @return Collection
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($shopifyCollection)
    {
        if (!$collection = self::getByShopifyID($shopifyCollection->id)) {
            $collection = self::create();
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $collection, $shopifyCollection);

        if ($collection->isChanged()) {
            $collection->write();
        }
        
        return $collection;
    }

    /**
     * @param $shopifyId
     *
     * @return Collection
     */
    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }

    public static function getByURLSegment($urlSegment)
    {
        return DataObject::get_one(self::class, ['URLSegment' => $urlSegment]);
    }
}
