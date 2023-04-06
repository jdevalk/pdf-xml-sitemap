=== XML Sitemap for PDFs for Yoast SEO ===
Contributors: joostdevalk
Tags: xml-sitemaps, seo, pdfs
Requires at least: 6.0
Tested up to: 6.1
Requires PHP: 7.4
Stable Tag: 1.0.1
License: GPLv3
License URI: http://www.opensource.org/licenses/GPL-3.0

This plugin creates an XML sitemap for the PDFs in your uploads folder. It requires Yoast SEO.

== Description ==

This plugin adds an XML sitemap for PDFs. It adds this XML sitemap to the `sitemap_index.xml` that Yoast SEO generates.
It has no settings.

=== Have you found an issue? ===

If you have bugs to report, please go to [the plugin's GitHub repository](https://github.com/jdevalk/pdf-xml-sitemap). For security issues, please use our [vulnerability disclosure program](https://patchstack.com/database/vdp/pdf-sitemap), which is managed by PatchStack. They will assist you with verification, CVE assignment, and, of course, notify us.

== Installation ==

1. Upload the files to the `/wp-content/plugins/pdf-sitemap/` directory or install through WordPress directly.
2. Activate the 'XML Sitemap for PDFs for Yoast SEO' plugin through the 'Plugins' menu in WordPress
3. Go to your site's `sitemap_index.xml` file and click on from there.

== Frequently Asked Questions ==

= Will this include PDFs uploaded through forms? =

The plugin only scans folders with numeric names, so it won't add files that have been uploaded through forms.

= Does this plugin cache its output? =

Yes, the plugin scans the uploads folder once per day and saves that data to a transient. When you upload a new PDF file
that cache is cleared automatically.

= Does this plugin work on multisite? =

This plugin has **not** been tested on multisite.

= Where can I report issues? =

On this plugin's [GitHub repository](https://github.com/jdevalk/pdf-xml-sitemap).

== Changelog ==

= 1.0.1 =

* Fixed a potential notice for an unset variable.
* Renamed some filters for consistency.

= 1.0 =

* First version.

== Screenshots ==

1. Example PDF XML sitemap.
2. Example of PDF XML sitemap linked in Index Sitemap.
