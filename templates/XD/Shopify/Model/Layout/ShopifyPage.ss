<section class="grid-container">
    <header class="grid-x grid-padding-x">
        <div class="cell">
            <h3>$Title</h3>
            $Content
        </div>
    </header>
    <article class="grid-container">

        <% if $ChildPages %>
            <div class="grid-x grid-padding-x medium-up-3 large-up-4">
                <% loop $ChildPages %>
                    <div class="cell">
                        <% if $ClassName == 'XD\Shopify\Model\Product' %>
                            <% include XD\\Shopify\\Product %>
                        <% else_if $ClassName == 'XD\Shopify\Model\Collection' %>
                            <a href="$Link">Collection: $Title</a>
                        <% end_if %>
                    </div>
                <% end_loop %>
            </div>
            <% with $ChildPages %>
                <div class="grid-x grid-padding-x">
                    <div class="cell">
                        <% include XD\\Shopify\\Pagination %>
                    </div>
                </div>
            <% end_with %>
        <% end_if %>
    </article>
</section>
