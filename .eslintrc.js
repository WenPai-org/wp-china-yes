module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	ignorePatterns: [
		'vendor/',
		'node_modules/',
		'build/',
		'dist/',
		'framework/',
		'client/',
		'assets/',
		'tests/',
		'Service/',
	],
};
