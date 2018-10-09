<section class="grid-container">
    <header class="grid-x grid-padding-x">
        <div class="cell">
            <h3>$Title</h3>
            $Content

            <div id="product-component-1543153647675"></div>
            <div id="product-component-aadf09a82d6"></div>
        </div>
    </header>
    <article class="grid-container">

        <% if $Products %>
            <div class="grid-x grid-padding-x medium-up-3 large-up-4">
                <% loop $Products %>
                    <div class="cell">
                        <% include XD\\Shopify\\Product %>
                    </div>
                <% end_loop %>
            </div>
            <% with $Products %>
                <div class="grid-x grid-padding-x">
                    <div class="cell">
                        <% include XD\\Shopify\\Pagination %>
                    </div>
                </div>
            <% end_with %>
        <% end_if %>
    </article>
</section>