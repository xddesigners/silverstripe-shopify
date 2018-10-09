<?php

namespace XD\Shopify\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;
use XD\Shopify\Client;

/**
 * Class ShopifyExtension
 *
 * @author Bram de Leeuw
 * @package XD\Shopify\Extension
 *
 * @property ShopifyExtension|\PageController $owner
 */
class ShopifyExtension extends Extension
{
    public function onAfterInit()
    {
        $domain = Client::config()->get('shopify_domain'); // 'broarm.myshopify.com';
        $accessToken = Client::config()->get('storefront_access_token'); // '8c6dc7f2a4c332ab69f896ba5e62783a';
        Requirements::javascript('//sdks.shopifycdn.com/buy-button/latest/buybutton.js');
        Requirements::customScript(<<<JS
        (function () {
            var client = ShopifyBuy.buildClient({
              domain: '{$domain}',
              storefrontAccessToken: '{$accessToken}'
            });
            
            window.shopifyClient = ShopifyBuy.UI.init(client);
            window.shopifyClient.createComponent('cart', {
               node: document.getElementById('shopify-cart')
            });
        })();
JS
        );
    }
}
