<section class="grid-container">
    <header class="grid-x grid-padding-x">
        <div class="cell">
            <h3>$Title</h3>
            $Content
        </div>
    </header>
    <section class="grid-x grid-padding-x small-2 medium-up-3 large-up-4">
        <% loop $Products %>
            <% include XD\\Shopify\\Product %>
        <% end_loop %>
    </section>
    <% with $ChildPages %>
        <footer class="grid-x grid-padding-x">
            <div class="cell">
                <% include XD\\Shopify\\Pagination %>
            </div>
        </footer>
    <% end_with %>
</section>
