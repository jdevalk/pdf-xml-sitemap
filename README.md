![One XML sitemap for all your uploaded PDFs](https://repository-images.githubusercontent.com/575400437/66a1764f-ab7d-4b73-b002-841293f1bfa2)

[![CS](https://github.com/jdevalk/pdf-xml-sitemap/actions/workflows/cs.yml/badge.svg)](https://github.com/jdevalk/pdf-xml-sitemap/actions/workflows/cs.yml)
[![Lint](https://github.com/jdevalk/pdf-xml-sitemap/actions/workflows/lint.yml/badge.svg)](https://github.com/jdevalk/pdf-xml-sitemap/actions/workflows/lint.yml)
[![Release Version](https://img.shields.io/github/release/jdevalk/pdf-xml-sitemap.svg)](https://github.com/jdevalk/pdf-xml-sitemap/releases/latest) 
[![MIT License](https://img.shields.io/github/license/jdevalk/pdf-xml-sitemap.svg)](https://github.com/devalk/pdf-xml-sitemap/blob/main/LICENSE)

# XML Sitemap for PDFs for Yoast SEO

This WordPress plugin adds an XML sitemap for PDFs. It adds this XML sitemap to the `sitemap_index.xml` that [Yoast SEO](https://yoast.com/wordpress/plugins/seo/) generates.
It has no settings.

## Installation

1. Upload the files to the `/wp-content/plugins/pdf-sitemap/` directory or install through WordPress directly.
2. Activate the 'XML Sitemap for PDFs for Yoast SEO' plugin through the 'Plugins' menu in WordPress
3. Go to your site's `sitemap_index.xml` file and click on from there.

## Frequently Asked Questions

### Will this include PDFs uploaded through forms?

The plugin only scans folders with numeric names, so it won't add files that have been uploaded through forms.

### Does this plugin cache its output?

Yes, the plugin scans the uploads folder once per day and saves that data to a transient. When you upload a new PDF file
that cache is cleared automatically.

### Does this plugin work on multisite?

This plugin has **not** been tested on multisite.

### Does this plugin have filters?

Yes, it does. It has the following filters:

| Filter                                | Description                                                                                                                                                                           |
|:--------------------------------------|:--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `JoostBlog\WP\pdf-sitemap\filetypes`  | Allows filtering which filetypes we're adding to the XML sitemap. Just `pdf` by default.                                                                                              |
| `JoostBlog\WP\pdf-sitemap\cache_time` | Allows filtering how long the plugin caches the scanned PDFs for. Value needs to be in seconds. Defaults to one day. Note: setting to 0 will mean cache indefinitely, not disable it. |
| `JoostBlog\WP\pdf-sitemap\pdfs`       | Allows filtering the PDFs being output.                                                                                                                                               |

## Changelog

### 1.0.1

* Fixed a potential notice for an unset variable.
* Renamed some filters for consistency.


### 1.0

* First version.
