<?php

namespace XD\Shopify\Control;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\Requirements;
use XD\Shopify\Client;
use XD\Shopify\Model\Product;
use XD\Shopify\Model\Collection;

/**
 * Class ShopifyPageController
 * @mixin ShopifyPage
 */
class ShopifyPageController extends \PageController
{
    private static $allowed_actions = [
        'product',
        'collection'
    ];

    /**
     * @var Product
     */
    public $product;

    /**
     * @var Collection
     */
    public $collection;

    /**
     * Get the Child pages as a paginated list
     *
     * @return PaginatedList
     */
    public function ChildPages()
    {
        $type = $this->ChildrenClass;
        return PaginatedList::create(
            $type::get(),
            $this->getRequest()
        )->setPageLength($this->PageLimit);
    }

    public function collection(HTTPRequest $request)
    {
        if (!$urlSegment = $request->param('ID')) {
            $this->httpError(404);
        }

        /** @var Collection $collection */
        if (!$collection = DataObject::get_one(Collection::class, ['URLSegment' => $urlSegment])) {
            $this->httpError(404);
        }

        $this->collection = $product;
        return $this->render($collection);
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

        $this->product = $product;
        return $this->render($product);
    }
}
