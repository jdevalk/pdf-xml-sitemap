<?php
/*
Plugin Name: XML Sitemap for PDFs for Yoast SEO
Plugin URI: https://joost.blog/plugins/pdf-xml-sitemap/
Description: Creates an XML sitemap for PDFs, requires Yoast SEO.
Version: 1.0
Requires PHP: 7.4
Author: Joost de Valk
Author URI: https://joost.blog/
License: GPLv3
License URI: http://www.opensource.org/licenses/GPL-3.0
*/
class Joost_PDF_Sitemap {
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
		$this->filetypes = apply_filters( 'joost/pdf-sitemap/filetypes', $this->filetypes );

		add_action( 'init', [ $this, 'register_hooks' ] );
	}

	/**
	 * Registers the hooks for our little plugin.
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function maybe_clear_cache( $metadata, $attachment_id ): void {
		$mime_type = get_post_mime_type( $attachment_id );
		if ( stripos( $mime_type, 'pdf' ) !== false ) {
			delete_transient( 'joost-pdf-sitemap' );
		}
	}

	/**
	 * Adds the sitemap index link.
	 *
	 * @param array $links The existing index links.
	 *
	 * @return array $links The index links.
	 */
	public function add_index_link( array $links ): array {
		$transient = get_transient( 'joost-pdf-sitemap' );
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
	 *
	 * @return void
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
	 * Getter for stylesheet url
	 *
	 * @return string
	 */
	public function get_stylesheet_line(): string {
		return PHP_EOL . '<?xml-stylesheet type="text/xsl" href="' . esc_url( $this->get_xsl_url() ) . '"?>';
	}

	/**
	 * Retrieves the XSL URL that should be used in the current environment
	 *
	 * When home_url and site_url are not the same, the home_url should be used.
	 * This is because the XSL needs to be served from the same domain, protocol and port
	 * as the XML file that is loading it.
	 *
	 * @return string The XSL URL that needs to be used.
	 */
	protected function get_xsl_url(): string {
		return plugin_dir_url( __FILE__ ) . 'pdf-sitemap.xsl';
	}

	/**
	 * Retrieves from cache the generated sitemap or generates a sitemap if needed.
	 *
	 * @return void
	 */
	public function retrieve_from_cache_or_build(): void {
		$transient = get_transient( 'joost-pdf-sitemap' );
		if ( $transient ) {
			$this->output = '<!-- Served from cache -->' . PHP_EOL;
		}
		if ( empty( $this->pdfs ) ) {
			$this->read_dir();
			set_transient(
				'joost-pdf-sitemap',
				[
					'pdfs'     => $this->pdfs,
					'last_mod' => $this->last_mod,
				],
				apply_filters( 'joost/pdf-sitemap/cache-time', DAY_IN_SECONDS )
			);
		}
		$this->output .= $this->generate_output();
	}

	/**
	 * Kick off the script reading the dir provided in config.
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	private function parse_dir( string $dir, string $url ): void {
		$dir    = trailingslashit( $dir );
		$url    = trailingslashit( $url );
		$handle = opendir( $dir );

		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( in_array( utf8_encode( $file ), [ '.', '..' ] ) ) {
				continue;
			}
			// Only parse through numeric folders. Others will not have been created by WordPress.
			if ( is_dir( $dir . $file ) && is_numeric( $file ) ) {
				$this->parse_dir( $dir . $file . '/', $url . $file . '/' );
			}

			// Check whether the file has on of the extensions allowed for this XML sitemap.
			$extension = pathinfo( $dir . $file, PATHINFO_EXTENSION );
			if ( empty( $extension ) || ! in_array( $extension, $this->filetypes ) ) {
				continue;
			}

			// Create a W3C valid date for use in the XML sitemap based on the file modification time.
			$file_mod_time = filemtime( $dir . $file );
			if ( ! $file_mod_time ) {
				$file_mod_time = filectime( $dir . $file );
			}

			$mod = date( 'c', $file_mod_time );
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
	 *
	 * @return string
	 */
	private function generate_output(): string {
		usort( $this->pdfs, fn( $a, $b ) => $b['mod'] <=> $a['mod'] );

		$this->pdfs = apply_filters( 'joost/pdf-sitemap/pdfs', $this->pdfs );

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

$joost_pdf = new Joost_PDF_Sitemap();
