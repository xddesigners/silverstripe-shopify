<?php

namespace XD\Shopify\Extension;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\View\Requirements;
use XD\Shopify\Client;
use XD\Shopify\Model\Product;

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
    public function getCartOptions()
    {
        return Convert::array2json(array_merge_recursive(Product::config()->get('options'), [
            'cart' => [
                'text' => [
                    'title' => _t('Shopify.CartTitle', 'Cart'),
                    'empty'=> _t('Shopify.CartEmpty', 'Your cart is empty.'),
                    'button' => _t('Shopify.CartButton', 'Checkout'),
                    'total' => _t('Shopify.CartTotal', 'Subtotal'),
                    'currency' => Product::config()->get('currency'),
                    'notice' => _t('Shopify.CartNotice', 'Shipping and discount codes are added at checkout.')
                ]
            ]
        ]));
    }

    public function onAfterInit()
    {
        if (Client::config()->get('inject_javascript') !== false) {
            $domain = Client::config()->get('shopify_domain');
            $accessToken = Client::config()->get('storefront_access_token');
            $currencySymbol = DBCurrency::config()->get('currency_symbol');
            Requirements::javascript('//sdks.shopifycdn.com/buy-button/latest/buybutton.js');
            Requirements::customScript(<<<JS
            (function () {
                var client = ShopifyBuy.buildClient({
                  domain: '{$domain}',
                  storefrontAccessToken: '{$accessToken}'
                });
                
                window.shopifyClient = ShopifyBuy.UI.init(client);
                window.shopifyClient.createComponent('cart', {
                   node: document.getElementById('shopify-cart'),
                   moneyFormat: '$currencySymbol{{amount}}',
                   options: {$this->getCartOptions()}
                });
            })();
JS
            );
        }
    }
}
