# [extensible search](https://packagist.org/packages/nglasl/silverstripe-extensible-search)

_The current release is **4.0.1**_

> This module allows user customisation and developer extension of a search page instance, including analytics and suggestions.

## Requirement

* SilverStripe 3.1 → **4.0**

## Getting Started

* [Place the module under your root project directory.](https://packagist.org/packages/nglasl/silverstripe-extensible-search)
* Configure the search engine and search form YAML.
* `/dev/build`
* Configure the extensible search page.

## Overview

### Extensible Search Page

This is automatically created, and allows configuration for search based on a search engine (more below).

![page](https://raw.githubusercontent.com/nglasl/silverstripe-extensible-search/master/client/images/extensible-search-page.png)

### Search Engine

The extensible search page is designed to use full-text search out of the box, while providing support for custom search engine implementations (elastic search for example).

#### Full-Text

```yaml
SilverStripe\ORM\Search\FulltextSearchable:
  searchable_classes:
    - 'SilverStripe\CMS\Model\SiteTree'
SilverStripe\CMS\Model\SiteTree:
  create_table_options:
    MySQLDatabase: 'ENGINE=MyISAM'
  extensions:
    - "SilverStripe\\ORM\\Search\\FulltextSearchable('Title', 'MenuTitle', 'Content', 'MetaDescription')"
```

When considering the search engine to use, full-text has some important limitations. This configuration can also be applied to `File`, however, unfortunately it does not support further customisation.

#### Custom Search Engine

The following is an example configuration, where `ElasticSearch` extends the abstract `CustomSearchEngine` class:

```yaml
nglasl\extensible\ExtensibleSearchPage:
  custom_search_engines:
    nglasl\extensible\ElasticSearch: 'Elastic'
```

### Search Form

```yaml
PageController:
  extensions:
    - 'nglasl\extensible\ExtensibleSearchExtension'
```

Using this, to display the search form that users interact with (from your template):

```php
$SearchForm
```

### Search Analytics

These are important to help determine either popular content on your site, or whether content is difficult for users to locate. They're automatically enabled out of the box, however, can be disabled using the following:

```yaml
nglasl\extensible\ExtensibleSearch:
  enable_analytics: false
```

![analytics](https://raw.githubusercontent.com/nglasl/silverstripe-extensible-search/master/client/images/extensible-search-analytics.png)

When triggering a search, appending `?analytics=false` to the URL will bypass the search analytics. This is fantastic for debugging.

#### Archiving

Depending on your search traffic, `/dev/tasks/ExtensibleSearchArchiveTask` may be used to archive past search analytics, for each search page. It would be recommended to trigger this on a schedule where possible.

![archives](https://raw.githubusercontent.com/nglasl/silverstripe-extensible-search/master/client/images/extensible-search-archives.png)

![archive](https://raw.githubusercontent.com/nglasl/silverstripe-extensible-search/master/client/images/extensible-search-archive.png)

### Search Suggestions

These are most effective alongside the search analytics (in which case they're automatically populated), and can be used to display either popular searches on your site, or search form autocomplete options. They're automatically enabled out of the box, however, can be disabled using the following:

```yaml
nglasl\extensible\ExtensibleSearchSuggestion:
  enable_suggestions: false
```

![suggestions](https://raw.githubusercontent.com/nglasl/silverstripe-extensible-search/master/client/images/extensible-search-suggestions.png)

To enable autocomplete using the **approved** search suggestions..

```php
Requirements::javascript('nglasl/silverstripe-extensible-search: client/javascript/extensible-search-suggestions.js');

// OPTIONAL.

Requirements::css('jquery-ui.min.css');
Requirements::javascript('jquery-ui.min.js');
```

### Smart Templating

Custom search engine specific templates may be defined for your search results. These are just two examples:

`ElasticSearch_results.ss` or `Page_results.ss`

## SS4 Changes

- The custom search engine implementation has changed, and no longer uses extensions (see above).

## Maintainer Contact

	Nathan Glasl, nathan@symbiote.com.au
