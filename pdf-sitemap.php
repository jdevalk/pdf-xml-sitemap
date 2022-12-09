<?php
/*
Plugin Name: XML Sitemap for PDFs for Yoast SEO
Plugin URI: https://joost.blog/plugins/pdf-xml-sitemap/
Description: Creates an XML sitemap for PDFs, requires Yoast SEO.
Version: 1.0.1
Requires PHP: 7.4
Author: Joost de Valk
Author URI: https://joost.blog/
License: GPLv3
License URI: http://www.opensource.org/licenses/GPL-3.0
*/

/**
 * Main plugin class.
 */
class JoostBlog_PDF_Sitemap {

	const TRANSIENT = 'joost-blog-pdf-sitemap';

	/**
	 * Holds the output.
	 */
	private string $output = '';

	/**
	 * Array of filetypes we're adding to the XML sitemap.
	 */
	private array $filetypes = [ 'pdf' ];

	/**
	 * Holds the newest last_mod date we can find.
	 */
	private string $last_mod = '';

	/**
	 * Holds the PDFs we're going to output.
	 */
	private array $pdfs = [];

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->filetypes = apply_filters( 'JoostBlog\WP\pdf_sitemap\filetypes', $this->filetypes );

		add_action( 'init', [ $this, 'register_hooks' ] );
	}

	/**
	 * Registers the hooks for our little plugin.
	 */
	public function register_hooks(): void {
		if ( isset( $GLOBALS['wpseo_sitemaps'] ) && is_object( $GLOBALS['wpseo_sitemaps'] ) && method_exists( 'WPSEO_Sitemaps', 'register_sitemap' ) ) {
			$GLOBALS['wpseo_sitemaps']->register_sitemap( 'pdf_files', [ $this, 'build_sitemap' ] );
		}

		add_filter( 'wpseo_sitemap_index_links', [ $this, 'add_index_link' ] );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'maybe_clear_cache' ], 10, 2 );
	}

	/**
	 * Makes sure we clear the PDF sitemap cache when a new PDF is uploaded.
	 *
	 * @param array $metadata      Attachment metadata. Unused.
	 * @param int   $attachment_id The attachment that was created.
	 */
	public function maybe_clear_cache( $metadata, $attachment_id ): void {
		$mime_type = get_post_mime_type( $attachment_id );
		if ( stripos( $mime_type, 'pdf' ) !== false ) {
			delete_transient( self::TRANSIENT );
		}
	}

	/**
	 * Adds the sitemap index link.
	 *
	 * @param array $links The existing index links.
	 */
	public function add_index_link( array $links ): array {
		$transient = get_transient( self::TRANSIENT );
		$last_mod  = '';

		if ( $transient ) {
			$last_mod = $transient['last_mod'];
		}

		$links[] = [
			'loc'     => trailingslashit( get_site_url() ) . 'pdf_files-sitemap.xml',
			'lastmod' => $last_mod,
		];

		return $links;
	}

	/**
	 * Retrieves the sitemap and assigns it to output.
	 */
	public function build_sitemap(): void {
		$this->retrieve_from_cache_or_build();

		if ( isset( $GLOBALS['wpseo_sitemaps'] ) && is_object( $GLOBALS['wpseo_sitemaps'] ) && method_exists( 'WPSEO_Sitemaps', 'set_sitemap' ) ) {
			$GLOBALS['wpseo_sitemaps']->set_sitemap( $this->output );
		}
		if ( method_exists( 'WPSEO_Sitemaps_Renderer', 'set_stylesheet' ) && property_exists( $GLOBALS['wpseo_sitemaps'], 'renderer' ) && ( $GLOBALS['wpseo_sitemaps']->renderer instanceof WPSEO_Sitemaps_Renderer ) ) {
			$GLOBALS['wpseo_sitemaps']->renderer->set_stylesheet( $this->get_stylesheet_line() );
		}
	}

	/**
	 * Getter for stylesheet url.
	 */
	public function get_stylesheet_line(): string {
		return PHP_EOL . '<?xml-stylesheet type="text/xsl" href="' . esc_url( $this->get_xsl_url() ) . '"?>';
	}

	/**
	 * Retrieves the XSL URL that should be used in the current environment.
	 *
	 * When home_url and site_url are not the same, the home_url should be used.
	 * This is because the XSL needs to be served from the same domain, protocol and port
	 * as the XML file that is loading it.
	 */
	protected function get_xsl_url(): string {
		return plugin_dir_url( __FILE__ ) . 'pdf-sitemap.xsl';
	}

	/**
	 * Retrieves from cache the generated sitemap or generates a sitemap if needed.
	 */
	public function retrieve_from_cache_or_build(): void {
		$transient = get_transient( self::TRANSIENT );
		if ( $transient ) {
			$this->output = '<!-- Served from cache -->' . PHP_EOL;
		}
		if ( empty( $this->pdfs ) ) {
			$this->read_dir();
			set_transient(
				self::TRANSIENT,
				[
					'pdfs'     => $this->pdfs,
					'last_mod' => $this->last_mod,
				],
				apply_filters( 'JoostBlog\WP\pdf_sitemap\cache_time', DAY_IN_SECONDS )
			);
		}
		$this->output .= $this->generate_output();
	}

	/**
	 * Kick off the script reading the dir provided in config.
	 */
	private function read_dir(): void {
		$dir = wp_get_upload_dir();

		$this->parse_dir( $dir['basedir'], $dir['baseurl'] );
	}

	/**
	 * Reads a directory and adds files found to the output.
	 *
	 * @param string $dir The directory to read.
	 * @param string $url The base URL.
	 */
	private function parse_dir( string $dir, string $url ): void {
		$dir    = trailingslashit( $dir );
		$url    = trailingslashit( $url );
		$handle = opendir( $dir );

		while ( ( $file = readdir( $handle ) ) !== false ) {
			if ( in_array( utf8_encode( $file ), [ '.', '..' ], true ) ) {
				continue;
			}
			// Only parse through numeric folders. Others will not have been created by WordPress.
			if ( is_dir( $dir . $file ) && is_numeric( $file ) ) {
				$this->parse_dir( $dir . $file . '/', $url . $file . '/' );
			}

			// Check whether the file has on of the extensions allowed for this XML sitemap.
			$extension = pathinfo( $dir . $file, PATHINFO_EXTENSION );
			if ( empty( $extension ) || ! in_array( $extension, $this->filetypes, true ) ) {
				continue;
			}

			// Create a W3C valid date for use in the XML sitemap based on the file modification time.
			$file_mod_time = filemtime( $dir . $file );
			if ( ! $file_mod_time ) {
				$file_mod_time = filectime( $dir . $file );
			}

			$mod = gmdate( 'c', $file_mod_time );
			if ( $mod > $this->last_mod ) {
				$this->last_mod = $mod;
			}

			$this->pdfs[] = [
				'url' => $url . rawurlencode( $file ),
				'mod' => $mod,
			];
		}

		closedir( $handle );
	}

	/**
	 * Output our XML sitemap.
	 */
	private function generate_output(): string {
		usort( $this->pdfs, fn( $a, $b ) => ( $b['mod'] <=> $a['mod'] ) );

		$this->pdfs = apply_filters( 'JoostBlog\WP\pdf_sitemap\pdfs', $this->pdfs );

		// Make sure the XMLNS below matches the one in the XSL or you'll find yourself debugging for a long time.
		$output = '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

		foreach ( $this->pdfs as $pdf ) {
			// Start creating the output.
			$output .= '<url>' . PHP_EOL;
			$output .= "\t" . '<loc>' . $pdf['url'] . '</loc>' . PHP_EOL;
			$output .= "\t" . '<lastmod>' . $pdf['mod'] . '</lastmod>' . PHP_EOL;
			$output .= '</url>' . PHP_EOL;
		}

		$output .= '</urlset>';

		return $output;
	}
}

$joost_blog_pdf = new JoostBlog_PDF_Sitemap();
