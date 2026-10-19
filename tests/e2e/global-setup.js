const { requireCoreKernel } = require( './helpers' );

/**
 * Abort the whole suite when the 4.0 kernel is not loaded.
 *
 * @return {void}
 */
module.exports = async function globalSetup() {
	requireCoreKernel();
};
