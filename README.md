# [extensible-search](https://packagist.org/packages/nglasl/silverstripe-extensible-search)

_The current release is **1.1.8**_

A module for SilverStripe which will allow user customisation and developer extension of a search page instance, including analytics and suggestions.

This will allow CMS authors to configure the search page and results without needing to perform code alterations to determine how the search works.

_**NOTE:** This repository has been pulled together using re-factored code from an existing module._

## Requirements

* SilverStripe 3.1.X

### Optional Dependencies

 * [SilverStrip Solr](https://github.com/nyeholt/silverstripe-solr)

## Getting Started

* Place the module under your root project directory.
* Either define the [full-text YAML](#enabling-full-text) or retrieve a [search wrapper](#custom-search-wrappers) module (such as Solr).
* `/dev/build`
* Configure your extensible search page.

## Overview

### Configuring the Search Page

A new page type `ExtensibleSearchPage` should automatically be created for you in the site root.
This should be published before you are able to perform searches using a search engine selection.

### Enabling Full-Text

The extensible search page will work with the MySQL/SQLite full-text search by default, however will require some YAML configuration around the data objects you wish to search against.

```yaml
FulltextSearchable:
  searchable_classes:
    - 'SiteTree'
SiteTree:
  create_table_options:
    MySQLDatabase:
      'ENGINE=MyISAM'
  extensions:
    - "FulltextSearchable('Title, MenuTitle, Content, MetaDescription')"
```

### Searching from other pages

To use the Extensible Search Page, search engine and configuration adding the `ExtensibleSearchExtension` to `Page_Controller` is necessary.

```yaml
Page_Controller:
  extensions:
    - 'ExtensibleSearchExtension'
```
This allows search functionality through template hooks, the search form may now be rendered in a template using `$SearchForm`.

### Custom Search Wrappers

These will need to be created as an extension applied to `ExtensibleSearchPage`, having their class name end with `Search`. The same will need to be done for the controller, however this will be applied to `ExtensibleSearchPage_Controller` and end with `Search_Controller`.

For example Solr is added as a Search Engine using this yml config:

```yaml
---
Name: extensions
---
ExtensibleSearchPage:
  extensions:
    - 'SolrSearch'
ExtensibleSearchPage_Controller:
  extensions:
    - 'SolrSearch_Controller'
```

Currently supported Wrappers:

 - [SilverStripe Solr](https://github.com/nyeholt/silverstripe-solr)

#### Customisation

To process the result set using your new search wrapper, the `getSearchResults` should be implemented on the controller extension, returning the array of data you wish to render into your search template.

DB field support may be defined using the `$support` class variable on your extension, an example being that your search wrapper does not support faceting.

To allow full customisation of your custom search wrapper from the CMS, the `updateSource`, `getQueryBuilders` and `getSelectableFields` methods may need be implemented. Hopefully these will be updated further down the track to remove such a dependency.

### Search Analytics

These may be disabled by configuring the `enable_analytics` flag.

These include the following and may be accessed from your extensible page instance:

* Search **summary**, including the frequency, average time taken, and if the last search made actually contained results.
* Search **history**, including search date/time, time taken, and the search engine used.
* Export to CSV.

### Search Suggestions and Typeahead (Search Overlay)

Extensible Search Page has several Search suggestion/typeahead features built in, these are:

#### Recent Search

A local storage backed list of searches the local user has made *and* clicked a link in the results page.

#### Search Suggestions

A list of searches executed by all users of the site this is currently stored and aggregated in the database.
The user generated search suggestions will require approval by default this is done in the CMS on the Extensible Search Page under the Search Suggestions tab, however this may be configured using the `automatic_approval` yml flag.

#### Search Typeahead

The search typeahead uses ajax to submit the current form input as the user types, it uses the entire current form including any sorting or filtering the user has applied.

#### Configuration

By default these features are disabled to enable them add this YAML snippet to your config and `$SearchPage.SearchOverlay` to your Search page template.

```yaml
ExtensibleSearchSuggestion:
    enable_suggestions: TRUE
    enable_typeahead: TRUE
```

The `$SearchPage.SearchOverlay` uses the `ExtensibleSearch_overlay.ss` template to add in a `div` that `extensible-search-typeahead.js` looks for. If you have multiple search forms on a single page (e.g a Search in the header and in the content), you'll need to add ``$SearchPage.SearchOverlay` twice. On page load `extensible-search-typeahead.js` binds the `$SearchPage.SearchOverlay` to the *nearest* Search form, so it's recommended to add the `$SearchPage.SearchOverlay` immediately after the `$SearchForm` in templates e.g

```
<div class="search-bar">
	$SearchForm
	$SearchPage.SearchOverlay
</div>
```

To remove any of the Search suggestion feature create an `ExtensibleSearch_overlay.ss` in your themes template directory and modify the following code, removed or reordering the 'search-typeahead', 'recent-searches' or 'search-suggestions':
```
<div class="esp-overlay" style="display: none;">
	<div class="search-typeahead">
		<ul class="search-typeahead-list">
			<li class="typeahead list-item" style="display: none;"><a href='#'>Title</a></li>
		</ul>
	</div>
	<div class="recent-searches">
		<div class="list-heading">
			<h3 class="list-title">Recent Searches</h3>
		</div>
		<ul class="recent-searches-list">
			<li class="recentsearch list-item" style="display: none;"><a href='#'>Title</a></li>
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
```

The `extensible-search-typeahead.js`, looks for the `.search-*-list` and then edits the `.list-item` classes inside each list, other classes can be added to style the overlay without effecting the javascript output.

### Templating

Custom templating may be defined through your search wrapper, however the default templating for the full-text search is either `ExtensibleSearchPage_results` or `Page_results`.

#### Listing Template

If you have created a custom listing template for your results, you will need something like the following in your search page template.

```
<% if $ListingTemplateID %>
	$Results
<% else %>
	<% loop $Results %>
		...
	<% end_loop %>
<% end_if %>
```

This listing template will require looping through the `$Items` variable.

## To Do

* Implement additional support for the default full-text search.
* Look into creating alternate search wrapper modules.
* Look into using search wrapper suggestions.
