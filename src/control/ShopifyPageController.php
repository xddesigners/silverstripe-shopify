<?php

namespace XD\Shopify\Model;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\Requirements;
use XD\Shopify\Client;

/**
 * Class ShopifyPageController
 * @mixin ShopifyPage
 */
class ShopifyPageController extends \PageController
{
    private static $allowed_actions = [
        'product'
    ];

    /**
     * Get the Child pages as a paginated list
     *
     * @return PaginatedList
     */
    public function Products()
    {
        return PaginatedList::create(
            Product::get(),
            $this->getRequest()
        )->setPageLength($this->PageLimit);
    }

    public function product(HTTPRequest $request)
    {
        if (!$urlSegment = $request->param('ID')) {
            $this->httpError(404);
        }

        /** @var Product $product */
        if (!$product = DataObject::get_one(Product::class, ['URLSegment' => $urlSegment])) {
            $this->httpError(404);
        }

        return $this->render($product);
    }
}
