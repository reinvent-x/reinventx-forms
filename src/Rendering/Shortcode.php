<?php
declare(strict_types=1);

namespace Reinventx\Rendering;

/**
 * [rvtx_form id="…"] — the classic-editor / page-builder embedding path.
 * A thin wrapper: FormEmbed does all the work, shared with the block.
 */
final class Shortcode {

	public const TAG = 'rvtx_form';

	public function __construct( private readonly FormEmbed $embed ) {
	}

	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'id' => '0' ), is_array( $atts ) ? $atts : array(), self::TAG );

		return $this->embed->render( absint( $atts['id'] ) );
	}
}
