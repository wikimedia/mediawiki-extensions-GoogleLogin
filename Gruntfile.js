/* eslint-env node */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		banana: {
			all: 'i18n/'
		},
		eslint: {
			options: {
				cache: true
			},
			all: [
				'**/*.js',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		stylelint: {
			all: [
				'**/*.css',
				'**/*.less',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
