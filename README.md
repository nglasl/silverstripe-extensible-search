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

When considering the search engine to use, full-text has some important limitations. This configuration can also be applied to `File`, however, unfortunately it does not support further customisation.

#### Custom Search Engine

The following is an example configuration:

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

`getSelectableFields` and `getSearchResults` (this one under the controller).

Depending on whether the search engine supports hierarchy filtering based on parent ID, this may also be configured.

```php
public static $supports_hierarchy = true;
```

### Search Form

```yaml
Page_Controller:
  extensions:
    - 'ExtensibleSearchExtension'
```

Using this, to display the search form that users interact with (from your template):

```php
$SearchForm
```

### Search Analytics

These are important to help determine either popular content on your site, or whether content is difficult for users to locate. They're automatically enabled out of the box, however, can be disabled using the following:

```yaml
ExtensibleSearch:
  enable_analytics: false
```

When triggering a search, appending `?analytics=false` to the URL will bypass the search analytics. This is fantastic for debugging.

#### Archiving

Depending on your search traffic, `/dev/tasks/ExtensibleSearchArchiveTask` may be used to archive past search analytics, for each search page. It would be recommended to trigger this on a schedule where possible.

### Search Suggestions

These are most effective alongside the search analytics (in which case they're automatically populated), and can be used to display either popular searches on your site, or search form autocomplete options. They're automatically enabled out of the box, however, can be disabled using the following:

```yaml
ExtensibleSearchSuggestion:
  enable_suggestions: false
```

To enable autocomplete using the **approved** search suggestions.

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
