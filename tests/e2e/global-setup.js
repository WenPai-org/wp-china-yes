const { requireV4Kernel } = require( './helpers' );

/**
 * Abort the whole suite when WPCY_KERNEL is not v4.
 *
 * @return {void}
 */
module.exports = async function globalSetup() {
	requireV4Kernel();
};
