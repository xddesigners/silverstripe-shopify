<?php

namespace XD\Shopify;

use Exception;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;

/**
 * Class Client
 * @package XD\Shopify
 *
 * @author Bram de Leeuw
 */
class Client
{
    use Configurable;

    const EXCEPTION_NO_API_KEY = 0;
    const EXCEPTION_NO_API_PASSWORD = 1;
    const EXCEPTION_NO_DOMAIN = 2;

    /**
     * Configures the version of the api that you want to use
     *
     * @config string
     */
    private static $api_version = '2020-04';

    /**
     * @config null|string
     */
    private static $api_key = null;

    /**
     * @config null|string
     */
    private static $api_password = null;

    /**
     * @config null|string
     */
    private static $storefront_access_token = null;

    /**
     * @config null|string
     */
    private static $shopify_domain = null;

    /**
     * @config null|string
     */
    private static $shared_secret = null;

    /**
     * Set this to false when creating your own custom shopify buy js
     * @config null|string
     */
    private static $inject_javascript = true;

    /**
     * @var \GuzzleHttp\Client|null
     */
    protected $client = null;

    /**
     * Get a list of available products
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function products(array $options = [])
    {
        return $this->client->request('GET', 'products.json', $options);
    }

    /**
     * Get information about a specific product
     *
     * @param string $productId
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function product($productId, array $options = [])
    {
        return $this->client->request('GET', "products/$productId.json", $options);
    }

    /**
     * Get available product listing ids
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function productListingIds(array  $options = [])
    {
        return $this->client->request('GET', "product_listings/product_ids.json", $options);
    }

    /**
     * Get the available Collections
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collections(array $options = [])
    {
        return $this->client->request('GET', 'custom_collections.json', $options);
    }

    /**
     * Get the connections between Products and Collections
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collects(array $options = [])
    {
        return $this->client->request('GET', 'collects.json', $options);
    }

    /**
     * Get the configured Guzzle client
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (!$key = self::config()->get('api_key')) {
            throw new Exception('No api key is set.', self::EXCEPTION_NO_API_KEY);
        }

        if (!$password = self::config()->get('api_password')) {
            throw new Exception('No api password is set.', self::EXCEPTION_NO_API_PASSWORD);
        }

        if (!$domain = self::config()->get('shopify_domain')) {
            throw new Exception('No shopify domain is set.', self::EXCEPTION_NO_DOMAIN);
        }

        $version = self::config()->get('api_version');
        
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => Controller::join_links(["https://$domain", 'admin/api', $version, '/']),
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Basic ' . base64_encode("$key:$password")
            ]
        ]);
    }
}
