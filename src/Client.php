<?php

namespace XD\Shopify;

use Exception;
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
    public function products()
    {
        return $this->client->request('GET', 'admin/products.json');
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
    public function product($productId)
    {
        return $this->client->request('GET', "admin/products/$productId.json");
    }

    /**
     * Get the available Collections
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collections()
    {
        return $this->client->request('GET', 'admin/custom_collections.json');
    }

    /**
     * Get the connections between Products and Collections
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collects()
    {
        return $this->client->request('GET', 'admin/collects.json');
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

        $this->client = new \GuzzleHttp\Client([
            'base_uri' => "https://$domain",
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Basic ' . base64_encode("$key:$password")
            ]
        ]);
    }
}