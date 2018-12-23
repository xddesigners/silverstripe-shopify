<?php

namespace XD\Shopify\Admin;


use SilverStripe\Admin\ModelAdmin;
use XD\Shopify\Model\Product;
use XD\Shopify\Model\Collection;

/**
 * Class ProductAdmin
 * @package XD\Shopify\Admin
 */
class ProductAdmin extends ModelAdmin
{
    private static $managed_models = [
        Collection::class,
        Product::class
    ];

    private static $url_segment = 'shopify';

    private static $menu_title = 'Shopify';

    //private static $menu_icon = 'xddesigners/silverstripe-shopify:images/shopify_glyph.svg';
    private static $menu_icon_class = 'font-icon-cart';
}
