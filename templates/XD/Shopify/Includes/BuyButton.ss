<div id="product-component-{$ShopifyID}"></div>
<script>
    (function () {
        if (window.shopifyClient) {
            window.shopifyClient.createComponent('product', {
                id: $ShopifyID,
                node: document.getElementById('product-component-{$ShopifyID}')
            });
        }
    })();
</script>