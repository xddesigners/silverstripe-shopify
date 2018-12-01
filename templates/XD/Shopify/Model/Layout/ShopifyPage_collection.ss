<section class="grid-container">
    <header class="grid-x grid-padding-x">
        <% if $Image %>
            <div class="cell medium-6 large-8">
                <img src="$Image.ScaleWidth(500).Link" alt="$Image.Title">
            </div>
        <% end_if %>
        <div class="cell<% if $Image %> medium-3 large-4<% end_if %>">
            <h3>$Title</h3>
            $Content
        </div>
    </header>
    <section class="grid-x grid-padding-x small-2 medium-up-3 large-up-4">
        <% loop $Products %>
            <div class="cell">
                <% include XD\\Shopify\\Product %>
            </div>
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
