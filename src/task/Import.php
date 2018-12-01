<?php

namespace XD\Shopify\Task;

use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;
use XD\Shopify\Client;
use XD\Shopify\Model\Collection;
use XD\Shopify\Model\Image;
use XD\Shopify\Model\Product;
use XD\Shopify\Model\ProductVariant;

/**
 * Class Import
 *
 * @author Bram de Leeuw
 */
class Import extends BuildTask
{
    const NOTICE = 0;
    const SUCCESS = 1;
    const WARN = 2;
    const ERROR = 3;

    protected $title = 'Import shopify products';

    protected $description = 'Import shopify products from the configured store';

    protected $enabled = true;

    public function run($request)
    {
        if (!Director::is_cli()) echo "<pre>";

        try {
            $client = new Client();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        $this->importCollects($client);
        $this->importCollections($client);
        $this->importProducts($client);

        if (!Director::is_cli()) echo "</pre>";
        exit('Done');
    }

    /**
     * Import the shopify products
     * @param Client $client
     *
     * @throws \Exception
     */
    public function importProducts(Client $client)
    {
        try {
            $products = $client->products();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        if (($products = $products->getBody()->getContents()) && $products = Convert::json2obj($products)) {
            foreach ($products->products as $shopifyProduct) {
                // Create the product
                if ($product = $this->importObject(Product::class, $shopifyProduct)) {
                    // Create the images
                    if (!empty($shopifyProduct->images)) {
                        foreach ($shopifyProduct->images as $shopifyImage) {
                            if ($image = $this->importObject(Image::class, $shopifyImage)) {
                                $product->Images()->add($image);
                            }
                        }
                    }

                    // attach the featured image
                    if (($image = $shopifyProduct->image) && ($imageID = $image->id) && ($image = Image::getByShopifyID($imageID))) {
                        try {
                            $product->ImageID = $image->ID;
                            $product->write();
                        } catch (\Exception $e) {
                            self::log($e->getMessage(), self::ERROR);
                        }
                    }

                    // Create the variants
                    if (!empty($shopifyProduct->variants)) {
                        foreach ($shopifyProduct->variants as $shopifyVariant) {
                            if ($variant = $this->importObject(ProductVariant::class, $shopifyVariant)) {
                                $product->Variants()->add($variant);
                            }
                        }
                    }

                    // Publish the product and it's connections
                    $product->publishRecursive();
                    self::log("[{$product->ID}] Published product {$product->Title} and it's connections", self::SUCCESS);
                } else {
                    self::log("[{$shopifyProduct->id}] Could not create product", self::ERROR);
                }
            }
        }
    }

    /**
     * Import the SHopify Collections
     * @param Client $client
     *
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function importCollections(Client $client)
    {
        try {
            $collections = $client->collections();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        if (($collections = $collections->getBody()->getContents()) && $collections = Convert::json2obj($collections)) {
            foreach ($collections->custom_collections as $shopifyCollection) {
                // Create the collection
                if ($collection = $this->importObject(Collection::class, $shopifyCollection)) {
                    // Create the images
                    if (!empty($shopifyCollection->image)) {
                        // The collection image does not have an id so set it from the scr to prevent double importing the image
                        $image = $shopifyCollection->image;
                        $image->id = $image->src;
                        if ($image = $this->importObject(Image::class, $image)) {
                            $collection->ImageID = $image->ID;
                            $collection->write();
                        }
                    }

                    // Publish the product and it's connections
                    $collection->publishRecursive();
                    self::log("[{$collection->ID}] Published collection {$collection->Title} and it's connections", self::SUCCESS);
                } else {
                    self::log("[{$shopifyCollection->id}] Could not create collection", self::ERROR);
                }
            }
        }
    }

    /**
     * Import the Shopify Collects
     * @param Client $client
     *
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function importCollects(Client $client)
    {
        try {
            $collects = $client->collects();
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        if (($collects = $collects->getBody()->getContents()) && $collects = Convert::json2obj($collects)) {
            foreach ($collects->collects as $shopifyCollect) {
                if (
                    ($collection = Collection::getByShopifyID($shopifyCollect->collection_id))
                    && ($product = Product::getByShopifyID($shopifyCollect->product_id))
                ) {
                    
                    echo "<pre>";
                    print_r($collection->Title);
                    print_r($collection->Products()->toArray());
                    echo "</pre>";
                    exit();
                    
                    $collection->Products()->add($product, [
                        'ShopifyID' => $shopifyCollect->id,
                        'SortValue' => $shopifyCollect->sort_value,
                        'Position' => $shopifyCollect->position,
                        'Featured' => $shopifyCollect->featured
                    ]);
                    self::log("[{$shopifyCollect->id}] Created collect between Product[{$product->ID}] and Collection[{$collection->ID}]", self::SUCCESS);
                }
            }
        }
    }

    /**
     * Import the base product
     *
     * @param Product|ProductVariant|Image|string $class
     * @param $shopifyData
     * @return null|Product|ProductVariant|Image
     */
    private function importObject($class, $shopifyData)
    {
        $object = null;
        try {
            $object = $class::findOrMakeFromShopifyData($shopifyData);
            self::log("[{$object->ID}] Created {$class} {$object->Title}", self::SUCCESS);
        } catch (\Exception $e) {
            self::log($e->getMessage(), self::ERROR);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            self::log("[Guzzle error] {$e->getMessage()}", self::ERROR);
        }

        return $object;
    }

    /**
     * Loop the given data map and possible sub maps
     *
     * @param array $map
     * @param $object
     * @param $data
     */
    public static function loop_map($map, &$object, $data)
    {
        foreach ($map as $from => $to) {
            if (is_array($to) && is_object($data->{$from})) {
                self::loop_map($to, $object, $data->{$from});
            } elseif (isset($data->{$from}) && $value = $data->{$from}) {
                $object->{$to} = $value;
            }
        }
    }

    /**
     * Log messages to the console or cron log
     *
     * @param $message
     * @param $code
     */
    protected static function log($message, $code = self::NOTICE)
    {
        switch ($code) {
            case self::ERROR:
                echo "[ ERROR ] {$message}\n";
                break;
            case self::WARN:
                echo "[WARNING] {$message}\n";
                break;
            case self::SUCCESS:
                echo "[SUCCESS] {$message}\n";
                break;
            case self::NOTICE:
            default:
                echo "[NOTICE ] {$message}\n";
                break;
        }
    }
}