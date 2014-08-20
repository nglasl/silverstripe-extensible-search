# [extensible-search](https://github.com/nglasl)

:bust_in_silhouette: _**NOTE:** This repository has been pulled together using re-factored code from an existing module._

	A module for SilverStripe which will allow user customisation and developer extension of a search page instance.

	This will allow CMS authors to configure the search page and results without needing to perform code alterations to determine how the search works.

## Requirement

* SilverStripe 3.1.X
* The multivaluefield module from https://github.com/nyeholt/silverstripe-multivaluefield

## Getting Started

* Place the module under your root project directory.
* Either define the full-text YAML or retrieve a search wrapper module (such as Solr).
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

To allow search functionality through template hooks, make sure the appropriate extension has also been applied.

```yaml
Page_Controller:
  extensions:
    - 'ExtensibleSearchExtension'
```

The search form may now be retrieved from a template using `$SearchForm`.

### Custom Search Wrappers

These will need to be created as an extension applied to `ExtensibleSearchPage`, having their class name end with `Search`. The same will need to be done for the controller, however this will be applied to `ExtensibleSearchPage_Controller` and end with `Search_Controller`.

	https://github.com/nyeholt/silverstripe-solr

#### Customisation

To process the result set using your new search wrapper, the `getSearchResults` should be implemented on the controller extension.

DB field support may be defined using the `$support` class variable on your extension, an example being that your search wrapper does not support faceting.

To allow full customisation of your custom search wrapper from the CMS, the `updateSource`, `getQueryBuilders` and `getSelectableFields` methods may need be implemented. Hopefully these will be updated further down the track to remove such a dependency.

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
