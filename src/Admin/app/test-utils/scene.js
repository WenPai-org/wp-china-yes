/**
 * Test helper: server-side scene switch emulation.
 */

import { sceneDefaults } from '../ui/profiles';

// Config\Profile::apply_to() as the server runs it on PUT /settings {profile}:
// a different scene resets the D2 connectivity keys; the same scene is a no-op.
export function applyScene( doc, scene ) {
	if ( ( doc.profile || 'domestic' ) === scene ) {
		return doc;
	}
	const def = sceneDefaults( scene );
	const conn = doc.connectivity || {};
	return {
		...doc,
		profile: scene,
		connectivity: {
			...conn,
			wordpress_org: def.wordpress_org,
			public_assets: def.public_assets,
			avatar: def.avatar,
			heartbeat: def.heartbeat,
			dashboard_feeds: def.dashboard_feeds,
			icon_photos: {
				...( conn.icon_photos || {} ),
				enabled: def.icon_photos.enabled,
			},
		},
	};
}
