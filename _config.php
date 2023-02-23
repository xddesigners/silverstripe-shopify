<?php

use SilverStripe\ORM\DataObject;
use XD\Shopify\Model\Collection;
use XD\Shopify\Model\Product;
use XD\Shopify\Model\ShopifyPage;

$googleSiteMap = 'Wilr\GoogleSitemaps\GoogleSitemap';
if (class_exists($googleSiteMap) && DataObject::get_one(ShopifyPage::class)) {
    $googleSiteMap::register_dataobject(Product::class);
    $googleSiteMap::register_dataobject(Collection::class);
}
