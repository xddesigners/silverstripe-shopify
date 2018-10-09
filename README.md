# Simple shopify module for SilverStripe sites
This module is for users that want to implement there shopify products into a SilverStripe storefront. 
It makes use of the [Shopify Buy Button](https://www.shopify.com/buy-button) to create the cart and checkout interface.   
You'll end up with a import job that fetches all the products and variants and stores them as Product DataObject in your site.

 
## Installation
Install the module trough composer and configure the api keys.  
`composer require xddesigners/silverstripe-shopify`

Get up your api keys by creating a new Private App in your shopify admin interface.

### Config
```yaml
XD\Shopify\Client:
  api_key: 'YOUR_API_KEY'
  api_password: 'YOUR_API_PASSWORD'
  shopify_domain: 'YOUR_SHOPIFY_DOMAIN' # mydomain.myshopify.com
  shared_secret: 'YOUR_API_SHARED_SECRET'
```

### Set up import script
You can run the import script manually trough the dev/tasks interface or set up up to run as a cron task. 
`http://example.com/dev/tasks/XD-Shopify-Task-Import` or `sake dev/tasks/XD-Shopify-Task-Import`