module.exports = function(grunt) {

    grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

    uglify: {
      my_target: {
        files: {
          '_scripts/scripts.js': ['components/scripts/scripts.js'],
          '_scripts/auth-scripts.js': ['components/scripts/auth-scripts.js']
        } //files
      } //my_target
    }, //uglify   

		/* Sass */
		sass: {
		  dist: {
		  	options: {
		  		style: 'compressed',
		  		sourcemap: 'none'
		  	},
		  	files: {
          'style.css': 'components/sass/style.scss'
		  	}
		  }
		},
		/* Autoprefixer */
		autoprefixer: {
			options: {
				browsers: ['last 5 versions']
			},
			// prefix all files
			multiple_files: {
				expanded: true, 
				flatten: true,
				src: '*.css',
				dist: ''
			}
		},

	  	/* Watch */
		watch: {		
      options: { livereload: true },

      scripts: {
        files: ['components/scripts/*.js'],
        tasks: ['uglify']
      }, //scripts

			css: {
				files: '**/*.scss',
				tasks: ['sass','autoprefixer']
			}, // css

      hypertext: {
        files: ['*.php','*.htm','_includes/*.php']
      } //hypertext

		}, //watch

	});
	grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-sass');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-autoprefixer');
	grunt.registerTask('default',['watch']);
}