<?php
/**
 * Webfonts API class.
 *
 * @package Gutenberg
 */

/**
 * Class WP_Webfonts
 */
class WP_Webfonts {

	/**
	 * An array of registered webfonts.
	 *
	 * @static
	 * @access private
	 * @var array
	 */
	private static $webfonts = array();

	/**
	 * An array of actually used webfonts by the front-end.
	 * This gets populated in several `register_*` methods
	 * inside this class.
	 *
	 * @static
	 * @access private
	 * @var array
	 */
	private static $used_webfonts = array();

	/**
	 * The name of the webfont cache option name.
	 *
	 * @static
	 * @access private
	 * @var string
	 */
	private static $webfont_cache_option = 'gutenberg_used_webfonts';

	/**
	 * The name of the globally used webfont cache option name.
	 *
	 * @static
	 * @access private
	 * @var string
	 */
	private static $global_webfont_cache_option = 'gutenberg_globally_used_webfonts';

	/**
	 * An array of registered providers.
	 *
	 * @static
	 * @access private
	 * @var array
	 */
	private static $providers = array();

	/**
	 * Stylesheet handle.
	 *
	 * @var string
	 */
	private $stylesheet_handle = '';

	/**
	 * Init.
	 */
	public function init() {

		// Register default providers.
		$this->register_provider( 'local', 'WP_Webfonts_Provider_Local' );

		// Register callback to generate and enqueue styles.
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			$this->stylesheet_handle = 'webfonts-footer';
			$hook                    = 'wp_print_footer_scripts';
		} else {
			$this->stylesheet_handle = 'webfonts';
			$hook                    = 'wp_enqueue_scripts';
		}

		add_action( 'init', array( $this, 'register_current_template_filter' ) );
		add_action( 'init', array( $this, 'register_globally_used_webfonts' ) );

		add_action( 'switch_theme', array( $this, 'invalidate_all_used_webfonts_cache' ) );
		add_action( 'save_post_wp_template', array( $this, 'invalidate_used_webfonts_cache' ) );
		add_action( 'save_post_wp_template_part', array( $this, 'invalidate_used_webfonts_cache' ) );

		add_filter( 'the_content', array( $this, 'register_webfonts_used_in_content' ) );

		add_filter( 'rest_request_after_callbacks', array( $this, 'maybe_invalidate_globally_used_webfonts_cache' ), 10, 3 );

		add_action( $hook, array( $this, 'generate_and_enqueue_styles' ) );

		// Enqueue webfonts in the block editor.
		add_action( 'admin_init', array( $this, 'generate_and_enqueue_editor_styles' ) );
	}

	/**
	 * Invalidates cache on global styles update.
	 *
	 * @param WP_REST_Response $response The response class.
	 * @param WP_REST_Server   $handler The request handler.
	 * @param WP_REST_Request  $request The request class.
	 *
	 * @return WP_REST_Response
	 */
	public function maybe_invalidate_globally_used_webfonts_cache( $response, $handler, $request ) {
		if ( 'GET' === $request->get_method() || ! is_a( $handler['callback'][0], 'WP_REST_Global_Styles_Controller' ) ) {
			// We only want to intercept requests that update global styles!
			return $response;
		}

		$this->invalidate_globally_used_webfonts_cache();
		update_option( self::$global_webfont_cache_option, $this->get_globally_used_webfonts() );

		return $response;
	}

	/**
	 * Register globally used webfonts.
	 *
	 * @return void
	 */
	public function register_globally_used_webfonts() {
		$globally_used_webfonts = get_option( self::$global_webfont_cache_option );

		if ( $globally_used_webfonts ) {
			self::$used_webfonts = array_merge( self::$used_webfonts, $globally_used_webfonts );
			return;
		}

		$globally_used_webfonts = $this->get_globally_used_webfonts();
		update_option( self::$global_webfont_cache_option, $globally_used_webfonts );

		self::$used_webfonts = array_merge( self::$used_webfonts, $globally_used_webfonts );
	}

	/**
	 * Hook into every possible template so we can get the full template object on page load.
	 *
	 * @return void
	 */
	public function register_current_template_filter() {
		$templates = get_block_templates( array(), 'wp_template' );

		foreach ( $templates as $template ) {
			add_filter(
				$template->slug . '_template',
				function() use ( $template ) {
					$this->register_used_webfonts_by_template( $template );
				}
			);
		}
	}

	/**
	 * Look up used webfonts cache for the template that triggered the hook
	 * registered on `register_current_template_filter`.
	 *
	 * If the webfonts array is not found there, parse the template, extract the webfonts
	 * and register it in the option.
	 *
	 * @param WP_Template $template The template that is about to be rendered.
	 * @return void
	 */
	public function register_used_webfonts_by_template( $template ) {
		$used_webfonts_cache = get_option( self::$webfont_cache_option, array() );

		if ( isset( $used_webfonts_cache[ $template->slug ] ) ) {
			self::$used_webfonts = array_merge( self::$used_webfonts, $used_webfonts_cache[ $template->slug ] );
			return;
		}

		$used_webfonts                          = $this->get_fonts_from_template( $template->content );
		$used_webfonts_cache[ $template->slug ] = $used_webfonts;

		update_option( self::$webfont_cache_option, $used_webfonts_cache );
		self::$used_webfonts = array_merge( self::$used_webfonts, $used_webfonts_cache[ $template->slug ] );
	}

	/**
	 * Register webfonts inside post content as used.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function register_webfonts_used_in_content( $content ) {
		self::$used_webfonts = array_merge( self::$used_webfonts, $this->get_fonts_from_content( $content ) );

		return $content;
	}

	/**
	 * Get used webfonts inside post content.
	 *
	 * @param string $content The post content.
	 * @return array
	 */
	private function get_fonts_from_content( $content ) {
		$used_webfonts = array();

		$blocks = parse_blocks( $content );
		$blocks = _flatten_blocks( $blocks );

		foreach ( $blocks as $block ) {
			if ( isset( $block['innerHTML'] ) ) {
				preg_match( '/class\=\".*has-(?P<slug>.+)-font-family/', $block['innerHTML'], $matches );

				if ( isset( $matches['slug'] ) ) {
					$used_webfonts[ $matches['slug'] ] = 1;
				}
			}
		}

		return $used_webfonts;
	}

	/**
	 * Get globally used webfonts.
	 *
	 * @return array
	 */
	private function get_globally_used_webfonts() {
		$global_styles          = gutenberg_get_global_styles();
		$globally_used_webfonts = array();

		// Register used fonts from blocks.
		foreach ( $global_styles['blocks'] as $setting ) {
			$font_family_slug = $this->get_font_family_from_setting( $setting );

			if ( $font_family_slug ) {
				$globally_used_webfonts[ $font_family_slug ] = 1;
			}
		}

		// Register used fonts from elements.
		foreach ( $global_styles['elements'] as $setting ) {
			$font_family_slug = $this->get_font_family_from_setting( $setting );

			if ( $font_family_slug ) {
				$globally_used_webfonts[ $font_family_slug ] = 1;
			}
		}

		// Get global font.
		$font_family_slug = $this->get_font_family_from_setting( $global_styles );

		if ( $font_family_slug ) {
			$globally_used_webfonts[ $font_family_slug ] = 1;
		}

		return $globally_used_webfonts;
	}

	/**
	 * Get font family from global setting.
	 *
	 * @param mixed $setting The global setting.
	 * @return string|false
	 */
	private function get_font_family_from_setting( $setting ) {
		if ( isset( $setting['typography'] ) && isset( $setting['typography']['fontFamily'] ) ) {
			$font_family = $setting['typography']['fontFamily'];

			preg_match( '/var\(--wp--(?:preset|custom)--font-family--([^\\\]+)\)/', $font_family, $matches );

			if ( isset( $matches[1] ) ) {
				return _wp_to_kebab_case( $matches[1] );
			}

			preg_match( '/var:(?:preset|custom)\|font-family\|(.+)/', $font_family, $matches );

			if ( isset( $matches[1] ) ) {
				return _wp_to_kebab_case( $matches[1] );
			}
		}

		return false;
	}

	/**
	 * We're changing the theme, so let's throw all the used webfonts cache away!
	 *
	 * @return void
	 */
	public function invalidate_all_used_webfonts_cache() {
		$this->invalidate_used_webfonts_cache();
		$this->invalidate_globally_used_webfonts_cache();
	}

	/**
	 * Invalidate the globally used webfonts cache.
	 *
	 * @return void
	 */
	private function invalidate_globally_used_webfonts_cache() {
		delete_option( self::$global_webfont_cache_option );
	}

	/**
	 * Invalidate used webfonts cache.
	 * We need to do that because there's no indication on which templates uses which template parts,
	 * so we're throwing everything away and reconstructing the cache.
	 *
	 * @return void
	 */
	public function invalidate_used_webfonts_cache() {
		delete_option( self::$webfont_cache_option );
	}

	/**
	 * Saves the fonts used by the saved template.

	 * @param integer $post_id The template ID.
	 * @param WP_Post $post The template post object.
	 * @return void
	 */
	public function save_used_webfonts_for_template( $post_id, $post ) {
		$used_webfonts = get_option( self::$webfont_cache_option, array() );

		$used_webfonts[ $post->post_name ] = $this->get_fonts_from_template( $post->post_content );

		update_option( self::$webfont_cache_option, $used_webfonts );
	}

	/**
	 * Get the list of fonts used in the template.
	 * Recursively gets the fonts used in the template parts.

	 * @param string $template_content The template content.
	 * @return array
	 */
	private function get_fonts_from_template( $template_content ) {
		$used_webfonts = array();

		$blocks = parse_blocks( $template_content );
		$blocks = _flatten_blocks( $blocks );

		foreach ( $blocks as $block ) {
			if ( 'core/template-part' === $block['blockName'] ) {
				$template_part           = get_block_template( get_stylesheet() . '//' . $block['attrs']['slug'], 'wp_template_part' );
				$fonts_for_template_part = $this->get_fonts_from_template( $template_part->content );

				$used_webfonts = array_merge(
					$used_webfonts,
					$fonts_for_template_part
				);
			}

			if ( isset( $block['attrs']['fontFamily'] ) ) {
				$used_webfonts[ $block['attrs']['fontFamily'] ] = 1;
			}
		}

		return $used_webfonts;
	}

	/**
	 * Get the list of fonts.
	 *
	 * @return array
	 */
	public function get_fonts() {
		return self::$webfonts;
	}

	/**
	 * Get the list of providers.
	 *
	 * @return array
	 */
	public function get_providers() {
		return self::$providers;
	}

	/**
	 * Register a webfont.
	 *
	 * @param array $font The font arguments.
	 */
	public function register_font( $font ) {
		$font = $this->validate_font( $font );
		if ( $font ) {
			$id                    = $this->get_font_id( $font );
			self::$webfonts[ $id ] = $font;
		}
	}

	/**
	 * Get the font ID.
	 *
	 * @param array $font The font arguments.
	 * @return string
	 */
	public function get_font_id( $font ) {
		return sanitize_title( "{$font['font-family']}-{$font['font-weight']}-{$font['font-style']}-{$font['provider']}" );
	}

	/**
	 * Validate a font.
	 *
	 * @param array $font The font arguments.
	 *
	 * @return array|false The validated font arguments, or false if the font is invalid.
	 */
	public function validate_font( $font ) {
		$font = wp_parse_args(
			$font,
			array(
				'provider'     => 'local',
				'font-family'  => '',
				'font-style'   => 'normal',
				'font-weight'  => '400',
				'font-display' => 'fallback',
			)
		);

		// Check the font-family.
		if ( empty( $font['font-family'] ) || ! is_string( $font['font-family'] ) ) {
			trigger_error( __( 'Webfont font family must be a non-empty string.', 'gutenberg' ) );
			return false;
		}

		// Local fonts need a "src".
		if ( 'local' === $font['provider'] ) {
			// Make sure that local fonts have 'src' defined.
			if ( empty( $font['src'] ) || ( ! is_string( $font['src'] ) && ! is_array( $font['src'] ) ) ) {
				trigger_error( __( 'Webfont src must be a non-empty string or an array of strings.', 'gutenberg' ) );
				return false;
			}
		}

		// Validate the 'src' property.
		if ( ! empty( $font['src'] ) ) {
			foreach ( (array) $font['src'] as $src ) {
				if ( empty( $src ) || ! is_string( $src ) ) {
					trigger_error( __( 'Each webfont src must be a non-empty string.', 'gutenberg' ) );
					return false;
				}
			}
		}

		// Check the font-weight.
		if ( ! is_string( $font['font-weight'] ) && ! is_int( $font['font-weight'] ) ) {
			trigger_error( __( 'Webfont font weight must be a properly formatted string or integer.', 'gutenberg' ) );
			return false;
		}

		// Check the font-display.
		if ( ! in_array( $font['font-display'], array( 'auto', 'block', 'fallback', 'swap' ), true ) ) {
			$font['font-display'] = 'fallback';
		}

		$valid_props = array(
			'ascend-override',
			'descend-override',
			'font-display',
			'font-family',
			'font-stretch',
			'font-style',
			'font-weight',
			'font-variant',
			'font-feature-settings',
			'font-variation-settings',
			'line-gap-override',
			'size-adjust',
			'src',
			'unicode-range',

			// Exceptions.
			'provider',
		);

		foreach ( $font as $prop => $value ) {
			if ( ! in_array( $prop, $valid_props, true ) ) {
				unset( $font[ $prop ] );
			}
		}

		return $font;
	}

	/**
	 * Register a provider.
	 *
	 * @param string $provider The provider name.
	 * @param string $class    The provider class name.
	 *
	 * @return bool Whether the provider was registered successfully.
	 */
	public function register_provider( $provider, $class ) {
		if ( empty( $provider ) || empty( $class ) ) {
			return false;
		}
		self::$providers[ $provider ] = $class;
		return true;
	}

	/**
	 * Filter unused webfonts based off self::$used_webfonts.
	 *
	 * @return void
	 */
	private function filter_unused_webfonts_from_providers() {
		$registered_webfonts = $this->get_fonts();

		self::$used_webfonts = apply_filters( 'gutenberg_used_webfonts', self::$used_webfonts );

		foreach ( $registered_webfonts as $id => $webfont ) {
			$font_name = _wp_to_kebab_case( $webfont['font-family'] );

			if ( ! isset( self::$used_webfonts[ $font_name ] ) ) {
				unset( $registered_webfonts[ $id ] );
			}
		}

		self::$webfonts = $registered_webfonts;
	}

	/**
	 * Generate and enqueue webfonts styles.
	 */
	public function generate_and_enqueue_styles() {
		$this->filter_unused_webfonts_from_providers();

		// Generate the styles.
		$styles = $this->generate_styles();

		// Bail out if there are no styles to enqueue.
		if ( '' === $styles ) {
			return;
		}

		// Enqueue the stylesheet.
		wp_register_style( $this->stylesheet_handle, '' );
		wp_enqueue_style( $this->stylesheet_handle );

		// Add the styles to the stylesheet.
		wp_add_inline_style( $this->stylesheet_handle, $styles );
	}

	/**
	 * Generate and enqueue editor styles.
	 */
	public function generate_and_enqueue_editor_styles() {
		// Generate the styles.
		$styles = $this->generate_styles();

		// Bail out if there are no styles to enqueue.
		if ( '' === $styles ) {
			return;
		}

		wp_add_inline_style( 'wp-block-library', $styles );
	}

	/**
	 * Generate styles for webfonts.
	 *
	 * @since 6.0.0
	 *
	 * @return string $styles Generated styles.
	 */
	public function generate_styles() {
		$styles    = '';
		$providers = $this->get_providers();

		// Group webfonts by provider.
		$webfonts_by_provider = array();
		$registered_webfonts  = $this->get_fonts();
		foreach ( $registered_webfonts as $id => $webfont ) {
			$provider = $webfont['provider'];
			if ( ! isset( $providers[ $provider ] ) ) {
				/* translators: %s is the provider name. */
				error_log( sprintf( __( 'Webfont provider "%s" is not registered.', 'gutenberg' ), $provider ) );
				continue;
			}
			$webfonts_by_provider[ $provider ]        = isset( $webfonts_by_provider[ $provider ] ) ? $webfonts_by_provider[ $provider ] : array();
			$webfonts_by_provider[ $provider ][ $id ] = $webfont;
		}

		/*
		 * Loop through each of the providers to get the CSS for their respective webfonts
		 * to incrementally generate the collective styles for all of them.
		 */
		foreach ( $providers as $provider_id => $provider_class ) {

			// Bail out if the provider class does not exist.
			if ( ! class_exists( $provider_class ) ) {
				/* translators: %s is the provider name. */
				error_log( sprintf( __( 'Webfont provider "%s" is not registered.', 'gutenberg' ), $provider_id ) );
				continue;
			}

			$provider_webfonts = isset( $webfonts_by_provider[ $provider_id ] )
				? $webfonts_by_provider[ $provider_id ]
				: array();

			// If there are no registered webfonts for this provider, skip it.
			if ( empty( $provider_webfonts ) ) {
				continue;
			}

			/*
			 * Process the webfonts by first passing them to the provider via `set_webfonts()`
			 * and then getting the CSS from the provider.
			 */
			$provider = new $provider_class();
			$provider->set_webfonts( $provider_webfonts );
			$styles .= $provider->get_css();
		}

		return $styles;
	}
}
