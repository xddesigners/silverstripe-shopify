<?php

$googleSiteMap = 'Wilr\GoogleSitemaps\GoogleSitemap';
if (class_exists($googleSiteMap)) {
    $googleSiteMap::register_dataobject(XD\Shopify\Model\Product::class);
    $googleSiteMap::register_dataobject(XD\Shopify\Model\Collection::class);
}
