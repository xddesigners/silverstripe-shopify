<?php

namespace XD\Shopify\Task;

use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DB;
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

        $this->importCollections($client);
        // Import products listed to our app or import all products
        // Import listings need a special authentication so fallback to everything
        $importedListingIds = $this->getProductListingIds($client);
        $this->importProducts($client, $importedListingIds);
        // todo: migrate to
        $this->beforeImportCollects();
        $this->importCollects($client);
        $this->afterImportCollects();

        if (!Director::is_cli()) echo "</pre>";
        exit('Done');
    }

    /**
     * Get an array of available product ids
     *
     * @param Client $client
     * @return array
     */
    public function getProductListingIds(Client $client)
    {
        try {
            $listings = $client->productListingIds([
                'query' => [
                    'limit' => 250
                ]
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        if (($listings = $listings->getBody()->getContents()) && $listings = Convert::json2obj($listings)) {
            return $listings->product_ids;
        }

        return [];
    }

    /**
     * Import the shopify products
     * @param Client $client
     * @param array $ids
     *
     * @throws \Exception
     */
    public function importProducts(Client $client, array $ids = [], $sinceId = 0)
    {
        $query = [
            'limit' => 250,
            'published_scope' => 'global',
            'since_id' => $sinceId
        ];

        // if we have a list of id's use it as a filter
        if (count($ids)) {
            $query['ids'] = implode(',', $ids);
        }

        try {
            $products = $client->products([
                'query' => $query
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        if (($products = $products->getBody()->getContents()) && $products = Convert::json2obj($products)) {
            $lastId = $sinceId;
            foreach ($products->products as $shopifyProduct) {
                // Create the product
                if ($product = $this->importObject(Product::class, $shopifyProduct)) {
                    // Create the images
                    $images = new ArrayList($shopifyProduct->images);
                    if ($images->exists()) {
                        foreach ($shopifyProduct->images as $shopifyImage) {
                            if ($image = $this->importObject(Image::class, $shopifyImage)) {
                                $product->Images()->add($image);
                            }
                        }

                        // Cleanup old images
                        $current = $product->Images()->column('ShopifyID');
                        $new = $images->column('id');
                        $delete = array_diff($current, $new);
                        foreach ($delete as $shopifyId) {
                            if ($image = Image::getByShopifyID($shopifyId)) {
                                $image->deleteFile();
                                $image->doUnpublish();
                                $image->delete();
                                self::log("[$shopifyId] Deleted image", self::SUCCESS);
                            }
                        }
                    }

                    // attach the featured image
                    if (($image = $shopifyProduct->image) && ($imageID = $image->id) && ($image = Image::getByShopifyID($imageID))) {
                        try {
                            $product->ImageID = $image->ID;
                        } catch (\Exception $e) {
                            self::log($e->getMessage(), self::ERROR);
                        }
                    }

                    // Create the variants
                    if (!empty($shopifyProduct->variants)) {
                        $keepVariants = [];
                        foreach ($shopifyProduct->variants as $shopifyVariant) {
                            if ($variant = $this->importObject(ProductVariant::class, $shopifyVariant)) {
                                $keepVariants[] = $variant->ID;
                                $product->Variants()->add($variant);
                                if (!$variant->isLiveVersion()) {
                                    $variant->publishSingle();
                                    self::log("[{$variant->ID}] Published Variant {$product->Title}", self::SUCCESS);
                                } else {
                                    self::log("[{$variant->ID}] Variant {$variant->Title} is alreaddy published", self::SUCCESS);
                                }
                            }
                        }

                        foreach ($product->Variants()->exclude(['ID' => $keepVariants]) as $variant) {
                            /** @var ProductVariant $variant */
                            $variantId = $variant->ID;
                            $variantShopifyId = $variant->ShopifyID;
                            $variant->doUnpublish();
                            $variant->delete();
                            self::log("[{$variantId}][{$variantShopifyId}] Deleted old variant connected to product", self::SUCCESS);
                        }
                    }
                    if ($product->isChanged()) {
                        $product->write();
                        self::log("[{$product->ID}] Saved changes in product {$product->Title}", self::SUCCESS);
                    } else {
                        self::log("[{$product->ID}] Product {$product->Title} has no changes", self::SUCCESS);
                    }

                    // Publish the product and it's connections
                    if (!$product->isLiveVersion()) {
                        $product->publishSingle();
                        self::log("[{$product->ID}] Published product {$product->Title}", self::SUCCESS);
                    } else {
                        self::log("[{$product->ID}] Product {$product->Title} is alreaddy published", self::SUCCESS);
                    }

                    $product->extend('onAfterImport');
                    $lastId = $product->ShopifyID;
                } else {
                    self::log("[{$shopifyProduct->id}] Could not create product", self::ERROR);
                }
            }

            // todo make pagination work with products
            //if ($lastId !== $sinceId) {
            //    self::log("[{$sinceId}] Try to import the next page of products since last id", self::SUCCESS);
            //    $this->importProducts($client, $ids, $lastId);
            //}

            // Cleanup old products
            $newProducts = new ArrayList($products->products);
            $current = Product::get()->column('ShopifyID');
            $new = $newProducts->column('id');
            $delete = array_diff($current, $new);
            foreach ($delete as $shopifyId) {
                /** @var Product $product */
                if ($product = Product::getByShopifyID($shopifyId)) {
                    foreach ($product->Images() as $image) {
                        /** @var Image $image */
                        $imageId = $image->ShopifyID;
                        $image->doUnpublish();
                        $image->deleteFile();
                        $image->delete();
                        self::log("[$shopifyId][$imageId] Deleted image connected to product", self::SUCCESS);
                    }

                    foreach ($product->Variants() as $variant) {
                        /** @var ProductVariant $variant */
                        $variantId = $variant->ShopifyID;
                        $variant->doUnpublish();
                        $variant->delete();
                        self::log("[$shopifyId][$variantId] Deleted variant connected to product", self::SUCCESS);
                    }

                    $product->doUnpublish();
                    $product->delete();
                    self::log("[$shopifyId] Deleted product and it's connections", self::SUCCESS);
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
    public function importCollections(Client $client, $sinceId = 0)
    {
        try {
            $collections = $client->collections([
                'query' => [
                    'limit' => 250,
                    'since_id' => $sinceId
                ]
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            exit($e->getMessage());
        }

        if (($collections = $collections->getBody()->getContents()) && $collections = Convert::json2obj($collections)) {
            $lastId = $sinceId;
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
                            if ($collection->isChanged()) {
                                $collection->write();
                            } else {
                                self::log("[{$collection->ID}] Collection {$collection->Title} has no change", self::SUCCESS);
                            }
                        }
                    }

                    if (!$collection->isLiveVersion()) {
                        $collection->publishSingle();
                        self::log("[{$collection->ID}] Published collection {$collection->Title} and it's connections", self::SUCCESS);
                    } else {
                        self::log("[{$collection->ID}] Collection {$collection->Title} is alreaddy published", self::SUCCESS);
                    }
                    $lastId = $collection->ShopifyID;
                } else {
                    self::log("[{$shopifyCollection->id}] Could not create collection", self::ERROR);
                }
            }

            if ($lastId !== $sinceId) {
                self::log("[{$sinceId}] Try to import the next page of collections since last id", self::SUCCESS);
                $this->importCollections($client, $lastId);
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
        $collections = Collection::get();
        if (!$collections->count()) {
            self::log("[collection] No collections to parse");
            return;
        }

        foreach ($collections as $collection) {
            /** @var Collection $collection */
            $collects = null;
            try {
                $collects = $client->collectionProducts($collection->ShopifyID, [
                    'query' => [
                        'limit' => 250,
                    ]
                ]);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                self::log($e->getMessage(), self::ERROR);
            }
            
            if ($collects && ($collects = $collects->getBody()->getContents()) && $collects = Convert::json2obj($collects)) {
                foreach ($collects->products as $index => $shopifProduct) {
                    if (($product = Product::getByShopifyID($shopifProduct->id)) && $product->exists()) {
                        $collection->Products()->add($product, [
                            'SortValue' => $index,
                            'Imported' => true
                            // $shopifyCollect->sort_value,
                            // 'ShopifyID' => $shopifyCollect->id,
                            // 'Position' => $shopifyCollect->position,
                        ]);

//                        $lastId = $shopifyCollect->id;
                        self::log("[collection] Created collect between Product[{$product->ID}] and Collection[{$collection->ID}]", self::SUCCESS);
                    }
                }
            }
        }
    }

    // todo make this flexible so it's also usable for Products, Variants, Images, Collections.
    // if made flexible should also handle versions.
    public function beforeImportCollects()
    {
        // Set all imported values to 0
        $schema = DataObject::getSchema()->manyManyComponent(Collection::class, 'Products');
        if (isset($schema['join']) && $join = $schema['join']) {
            DB::query("UPDATE `$join` SET `Imported` = 0 WHERE 1");
        }
    }

    public function afterImportCollects()
    {
        // Delete all collects that where not given during importe
        $schema = DataObject::getSchema()->manyManyComponent(Collection::class, 'Products');
        if (isset($schema['join']) && $join = $schema['join']) {
            DB::query("DELETE FROM `$join` WHERE `Imported` = 0");
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
            } elseif (property_exists($data, $from)) {
                $object->{$to} = $data->{$from};
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
