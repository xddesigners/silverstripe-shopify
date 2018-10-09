<?php

namespace XD\Shopify\Admin;


use SilverStripe\Admin\ModelAdmin;
use XD\Shopify\Model\Product;

/**
 * Class ProductAdmin
 * @package XD\Shopify\Admin
 */
class ProductAdmin extends ModelAdmin
{
    private static $managed_models = [
        Product::class
    ];

    private static $url_segment = 'shopify';

    private static $menu_title = 'Shopify';

    private static $menu_icon = '/shopify/images/shopify_glyph.svg';
    private static $menu_icon_class = null;
}
