/**
 * Webpack loader: points the email editor's placeholder images at this plugin.
 *
 * @kodezen/editor gives a new Image or Video block a default picture from a
 * third-party image host. A plugin on WordPress.org may not load images from
 * another server, and that host no longer serves them anyway, so the defaults
 * are swapped at build time for two images shipped in assets/images/.
 *
 * The replacement is an expression rather than a string because the plugin's
 * URL is only known on the site: it reads ZaplaneGlobal.plugin_root_url, which
 * wp_localize_script() prints before the bundle runs.
 */
const REMOTE_DEFAULT = /"https:\/\/via\.placeholder\.com\/600x(300|340)[^"]*"/g;

const LOCAL = {
	300: 'assets/images/email-image-placeholder.png',
	340: 'assets/images/email-video-placeholder.png',
};

module.exports = function editorPlaceholders( source ) {
	return source.replace(
		REMOTE_DEFAULT,
		( match, height ) =>
			'((window.ZaplaneGlobal&&window.ZaplaneGlobal.plugin_root_url)||"")+' +
			JSON.stringify( LOCAL[ height ] )
	);
};
