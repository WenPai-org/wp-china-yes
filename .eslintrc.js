module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	ignorePatterns: [
		'vendor/',
		'node_modules/',
		'build/',
		'dist/',
		'tests/',
	],
};
