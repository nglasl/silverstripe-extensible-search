# [extensible-search](https://packagist.org/packages/nglasl/silverstripe-extensible-search)

_The current release is **3.0.3**_

	A module for SilverStripe which will allow user customisation and developer extension of a search page instance, including analytics and suggestions.

	This will allow CMS authors to configure the search page and results without needing to perform code alterations to determine how the search works.

## Requirement

* SilverStripe 3.1.X or **3.2.X**

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

It should also be highlighted that unfortunately full-text does **not** support custom data objects and fields. However, these can be applied to `File` and not just `SiteTree`.

### Custom Search Wrappers

These will need to be created as an extension applied to `ExtensibleSearchPage`, and explicitly defined under the `search_engine_extensions` using YAML. The `ExtensibleSearchPage_Controller` will also require an extension so the search results can be retrieved for your search engine correctly. When the one class has been added to `search_engine_extensions` (pretty titles can be defined using array syntax), and the two extensions applied, your search engine will appear as a selection.

https://github.com/nyeholt/silverstripe-solr

#### Customisation

##### Model Extension

If your search wrapper supports filtering based upon page hierarchy (`ParentID` as opposed to just `SiteID`), the `supports_hierarchy` flag can be set.

It is also possible to define the `getSelectableFields` function if you wish to customise what fields are returned to an end user, such as when selecting a field to sort by.

##### Controller Extension

To process the result set using your new search wrapper, the `getSearchResults` should be implemented on the controller extension, returning the array of data you wish to render into your search template.

### Search Analytics

These may be disabled by configuring the `enable_analytics` flag.

These include the following and may be accessed from your extensible page instance:

* Search **summary**, including the frequency, average time taken, and if the last search made actually contained results.
* Search **history**, including search date/time, time taken, and the search engine used.
* Export to CSV.

### Search Suggestions

These may be disabled by configuring the `enable_suggestions` flag.

The user generated search suggestions will require approval by default, however this may be configured using the `automatic_approval` flag.

To enable autocomplete using approved search suggestions, the following will be required.

```php
Requirements::css('framework/thirdparty/jquery-ui-themes/smoothness/jquery-ui.min.css');
Requirements::javascript('framework/thirdparty/jquery-ui/jquery-ui.min.js');
Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-suggestions.js');
```

### Templating

The templating used is based upon the search engine selection, falling back to `ExtensibleSearch_results`, `ExtensibleSearchPage_results` and `Page_results`.
