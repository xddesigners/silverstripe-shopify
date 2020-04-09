<?php

namespace XD\Shopify\Model;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\HasManyList;
use SilverStripe\TagField\TagField;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\FieldType\DBCurrency;
use XD\Shopify\Task\Import;

/**
 * Class Product
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
 * @property string Vendor
 * @property string ProductType
 * @property string Tags
 *
 * @property int ImageID
 * @method Image Image()
 *
 * @method HasManyList Variants()
 * @method HasManyList Images()
 */
class Product extends DataObject
{
    private static $table_name = 'ShopifyProduct';

    private static $currency = 'EUR';

    private static $options = [
        'product' => [
            'contents' => [
                'title' => false,
                'variantTitle' => false,
                'price' => false,
                'description' => false,
                'quantity' => false,
                'img' => false,
            ]
        ]
    ];

    private static $db = [
        'Title' => 'Varchar',
        'URLSegment' => 'Varchar',
        'ShopifyID' => 'Varchar',
        'Content' => 'HTMLText',
        'Vendor' => 'Varchar',
        'ProductType' => 'Varchar',
        'Tags' => 'Varchar'
    ];

    private static $default_sort = 'Created DESC';

    private static $searchable_fields = [
        'Title',
        'URLSegment',
        'ShopifyID',
        'Content',
        'Vendor',
        'ProductType',
        'Tags'
    ];

    private static $data_map = [
        'id' => 'ShopifyID',
        'title' => 'Title',
        'body_html' => 'Content',
        'vendor' => 'Vendor',
        'product_type' => 'ProductType',
        'created_at' => 'Created',
        'handle' => 'URLSegment',
        'updated_at' => 'LastEdited',
        'tags' => 'Tags',
    ];

    private static $has_one = [
        'Image' => Image::class
    ];

    private static $has_many = [
        'Variants' => ProductVariant::class,
        'Images' => Image::class
    ];

    private static $belongs_many_many = [
        'Collections' => Collection::class
    ];

    private static $owns = [
        'Variants',
        'Images',
        'Image'
    ];

    private static $indexes = [
        'ShopifyID' => true,
        'URLSegment' => true
    ];

    private static $summary_fields = [
        'Image.CMSThumbnail' => 'Image',
        'Title',
        'Vendor',
        'ProductType',
        'ShopifyID'
    ];

    private static $extensions = [
        Versioned::class
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab('Root.Main', [
            ReadonlyField::create('Title'),
            ReadonlyField::create('URLSegment'),
            ReadonlyField::create('ShopifyID'),
            ReadonlyField::create('Content'),
            ReadonlyField::create('Vendor'),
            ReadonlyField::create('ProductType'),
            ReadonlyField::create('Tags'),
            UploadField::create('Image')->performReadonlyTransformation(),
        ]);

        $fields->addFieldsToTab('Root.Variants', [
            GridField::create('Variants', 'Variants', $this->Variants(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->addFieldsToTab('Root.Images', [
            GridField::create('Images', 'Images', $this->Images(), GridFieldConfig_RecordViewer::create())
        ]);

        $fields->removeByName(['LinkTracking','FileTracking']);
        return $fields;
    }

    public function getVariantWithLowestPrice()
    {
        return DataObject::get_one(ProductVariant::class, ['ProductID' => $this->ID], true, 'Price ASC');
    }

    /**
     * @return DBCurrency|null
     */
    public function getPrice()
    {
        if ($product = $this->getVariantWithLowestPrice()) {
            return $product->dbObject('Price');
        }

        return null;
    }

    /**
     * @return DBCurrency|null
     */
    public function getCompareAtPrice()
    {
        if ($product = $this->getVariantWithLowestPrice()) {
            return $product->dbObject('CompareAtPrice');
        }

        return null;
    }

    /**
     * Merge in the configured button options
     *
     * @return string
     */
    public function getButtonOptions()
    {
        return Convert::array2json(array_merge_recursive(self::config()->get('options'), [
            'product' => [
                'text' => [
                    'button' => _t('Shopify.ProductButton', 'Add to cart'),
                    'outOfStock' => _t('Shopify.ProductOutOfStock', 'Out of stock'),
                    'unavailable' => _t('Shopify.ProductUnavailable', 'Unavailable'),
                ]
            ]
        ]));
    }

    public function getButtonScript()
    {
        $currencySymbol = DBCurrency::config()->get('currency_symbol');
        Requirements::customScript(<<<JS
            (function () {
                if (window.shopifyClient) {
                    window.shopifyClient.createComponent('product', {
                        id: {$this->ShopifyID},
                        node: document.getElementById('product-component-{$this->ShopifyID}'),
                        moneyFormat: '$currencySymbol{{amount}}',
                        options: {$this->ButtonOptions}
                    });
                }
            })();
JS
        );
    }
    
    public function Link($action = null)
    {
        $shopifyPage = ShopifyPage::inst();
        return Controller::join_links($shopifyPage->Link('product'), $this->URLSegment, $action);
    }

    public function AbsoluteLink($action = null) {
        return Director::absoluteURL($this->Link($action));
    }

    /**
     * Creates a new Shopify Product from the given data
     * but does not publish it
     *
     * @param $shopifyProduct
     * @return Product
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function findOrMakeFromShopifyData($shopifyProduct)
    {
        if (!$product = self::getByShopifyID($shopifyProduct->id)) {
            $product = self::create();
        }

        $map = self::config()->get('data_map');
        Import::loop_map($map, $product, $shopifyProduct);

        if ($product->isChanged()) {
            $product->write();
        }
        
        return $product;
    }

    public static function getByShopifyID($shopifyId)
    {
        return DataObject::get_one(self::class, ['ShopifyID' => $shopifyId]);
    }

    public static function getByURLSegment($urlSegment)
    {
        return DataObject::get_one(self::class, ['URLSegment' => $urlSegment]);
    }
}
