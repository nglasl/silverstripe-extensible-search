<div class="esp-overlay">
	<div class="search-typeahead">
		<ul class="search-typeahead-list">
			<li class="typeahead list-item"><a href='#'>Title</a></li>
		</ul>
	</div>
	<div class="recent-searches">
		<div class="list-heading">
			<h3 class="list-title">Recent Searches</h3>
		</div>
		<ul class="recent-searches-list">
			<li class="recentsearch list-item"><a href='#'>Title</a></li>
		</ul>
	</div>
	<% if $Count %>
	<div class="search-suggestions">
	<div class="list-heading">
		<h3 class="list-title">Search Suggestions</h3>
	</div>
	<ul class="search-suggestions-list">
		<% loop $Me %>
		<li class="list-item">
			<a href="#">$Term</a>
		</li>
		<% end_loop %>
	</ul>
	</div>
	<% end_if %>
</div>