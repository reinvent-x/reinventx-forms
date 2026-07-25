/**
 * Every Radix portal must mount inside the app container: our styles and
 * theme tokens are scoped to #rvtx-admin, so anything portalled to
 * document.body would render unstyled (ARCHITECTURE §4).
 */
export function portalContainer(): HTMLElement | undefined {
	return document.getElementById( 'rvtx-admin' ) ?? undefined;
}
