# [extensible-search](https://packagist.org/packages/nglasl/silverstripe-extensible-search)

_The current release is **3.1.2**_

	A module for SilverStripe which will allow user customisation and developer extension of a search page instance, including analytics and suggestions.

	This will allow CMS authors to configure the search page and results without needing to perform code alterations to determine how the search works.

## Requirement

* SilverStripe 3.1.X or **3.2.X**

## Getting Started

* Place the module under your root project directory.
* Either define the full-text YAML or use a search engine extension.
* `/dev/build`
* Configure your extensible search page.

## Overview

### Configuring the Search Page

A new page type `ExtensibleSearchPage` should automatically be created for you in the site root.
This should be published before you are able to perform searches using a search engine selection. Here you can configure how your search page behaves.

<screenshot>

### The Search Form

To allow search functionality through template hooks, make sure the appropriate extension has been applied.

```yaml
Page_Controller:
  extensions:
    - 'ExtensibleSearchExtension'
```

The search form may now be retrieved from a template using `$SearchForm`

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

It should also be highlighted that unfortunately full-text does **not** support custom data objects and fields. However, these can be applied to `File` and not just `SiteTree`.

### Search Engine Extensions

These will need to be created as an extension applied to `ExtensibleSearchPage`, and explicitly defined under the `search_engine_extensions` using YAML. The `ExtensibleSearchPage_Controller` will also require an extension so the search results can be retrieved for your search engine correctly. When the one class has been added to `search_engine_extensions` (pretty titles can be defined using array syntax), and the two extensions applied, your search engine will appear as a selection.

<replace this with a solr example>

https://github.com/nyeholt/silverstripe-solr

#### Customisation

##### Model Extension

If your search wrapper supports filtering based upon page hierarchy (`ParentID` as opposed to just `SiteID`), the `supports_hierarchy` flag can be set.

It is also possible to define the `getSelectableFields` function if you wish to customise what fields are returned to an end user, such as when selecting a field to sort by.

##### Controller Extension

To process the result set using your new search wrapper, the `getSearchResults` should be implemented on the controller extension, returning the array of data you wish to render into your search template.

### Search Analytics

These may be disabled by configuring the `enable_analytics` flag.

<screenshot>

### Archiving

`/dev/tasks/ExtensibleSearchArchiveTask` creates an archived collection of analytics for each search page. Depending on your search traffic, the queued jobs module may be recommended to trigger this on a schedule.

`number_to_archive`

<screenshots>

### Search Suggestions

These require the appropriate permissions.

These may be disabled by configuring the `enable_suggestions` flag.

The user generated search suggestions will require approval by default, however this may be configured using the `automatic_approval` flag.

<screenshot>

To enable autocomplete using approved search suggestions, the following will be required.

```php
Requirements::javascript(EXTENSIBLE_SEARCH_PATH . '/javascript/extensible-search-suggestions.js');

// OPTIONAL.

Requirements::css('framework/thirdparty/jquery-ui-themes/smoothness/jquery-ui.min.css');
Requirements::javascript('framework/thirdparty/jquery-ui/jquery-ui.min.js');
```

### Smart Templating

Custom search engine templates may be defined for your results. These are just two examples:

`{$engine}_results` or `Page_results`

<replace this with a solr example>

<maintainer>
