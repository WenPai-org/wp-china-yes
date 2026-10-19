const { requireCoreKernel, wpEval } = require( './helpers' );

/**
 * Abort the whole suite when the 4.0 kernel is not loaded.
 *
 * @return {void}
 */
module.exports = async function globalSetup() {
	requireCoreKernel();
	// The first wpcy page visit after activation is sent to the wizard once;
	// mark it as done so specs land on the page they open.
	wpEval( "update_option( 'wpcy_onboarding_redirected', '1', false );" );
};
