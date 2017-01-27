# [extensible-search](https://packagist.org/packages/nglasl/silverstripe-extensible-search)

_The current release is **3.1.2**_

> A module for SilverStripe which will allow user customisation and developer extension of a search page instance, including analytics and suggestions.

## Requirement

* SilverStripe 3.1.X or **3.2.X**

## Getting Started

* Place the module under your root project directory.
* Configure the search engine and search form YAML.
* `/dev/build`
* Configure the extensible search page.

## Overview

### Extensible Search Page

This is automatically created, and allows configuration for search based on a search engine (more below).

### Search Engine

The extensible search page is designed to use full-text search out of the box, while providing support for custom search engine implementations (elastic search for example).

#### Full-Text

```yaml
FulltextSearchable:
  searchable_classes:
    - 'SiteTree'
SiteTree:
  create_table_options:
    MySQLDatabase: 'ENGINE=MyISAM'
  extensions:
    - "FulltextSearchable('Title, MenuTitle, Content, MetaDescription')"
```

When considering the search engine to use, full-text has some important limitations. This configuration can also be applied to `File`, however, unfortunately it does not support any further customisation.

#### Custom Search Engine

The following is an example:

```yaml
ExtensibleSearchPage:
  search_engine_extensions:
    SolrSearch: 'Solr'
  extensions:
    - 'SolrSearch'
ExtensibleSearchPage_Controller:
  extensions:
    - 'SolrSearch_Controller'
```

When implementing a custom search engine, these are required:

`getSelectableFields` and `getSearchResults` (this one under the controller)

Depending on whether the search engine supports filtering based on parent ID, this may also be configured:

```php
public static $supports_hierarchy = true;
```

### Search Form

```yaml
Page_Controller:
  extensions:
    - 'ExtensibleSearchExtension'
```

The search form can be rendered in templates using:

```php
$SearchForm
```

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
