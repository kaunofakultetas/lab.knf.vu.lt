let path = require('path');
let mix = require('laravel-mix');
 
mix.options({manifest: '../mix-manifest.json'});
 
mix.combine([
    'resources/views/assets/vendor/choices.js/public/assets/styles/choices.min.css', 
    'resources/views/assets/css/jquery.toast.css', 
    'resources/views/assets/css/jquery.multiselect.css',
    'resources/views/assets/css/style.default.css',
    'resources/views/assets/css/custom.css'
], 'public/assets/css/app.css');

mix.combine([
    'resources/views/assets/vendor/choices.js/public/assets/styles/choices.min.css',
    'resources/views/assets/css/daterangepicker.css', 
	'node_modules/select2/dist/css/select2.min.css', 
    'resources/views/assets/css/summernote.min.css', 
    'resources/views/assets/css/jquery.toast.css',
    'resources/views/assets/css/style.default.css',
    'resources/views/assets/css/custom.css'
], 'public/assets/css/app_admin.css');
 
mix.scripts([
	'resources/views/assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
	'resources/views/assets/js/jquery-3.6.3.min.js',
	'resources/views/assets/js/moment.min.js',
	'resources/views/assets/js/moment-timezone.min.js', 
	'resources/views/assets/js/jquery.countdown.min.js',
	'resources/views/assets/js/jquery.matchHeight.js',
	'resources/views/assets/js/summernote.min.js',
	'resources/views/assets/js/daterangepicker.js', 
	'resources/views/assets/vendor/just-validate/js/just-validate.min.js',
	'resources/views/assets/vendor/choices.js/public/assets/scripts/choices.min.js',   
	'resources/views/assets/js/jquery.toast.js',
	'resources/views/assets/js/front.js',
	'resources/views/assets/js/dialogs.js',
	'resources/views/assets/js/custom_admin.js'
], 'public/assets/js/app_admin.js');

mix.scripts([
	'resources/views/assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
	'resources/views/assets/js/jquery-3.6.3.min.js',
	'resources/views/assets/js/moment.min.js',
	'resources/views/assets/js/moment-timezone.min.js',
	'resources/views/assets/js/jquery.countdown.min.js',
	'resources/views/assets/js/jquery.matchHeight.js', 
	'resources/views/assets/vendor/just-validate/js/just-validate.min.js',
	'resources/views/assets/vendor/choices.js/public/assets/scripts/choices.min.js',  
	'resources/views/assets/js/jquery.toast.js',     
	'node_modules/isotope-layout/dist/isotope.pkgd.min.js',  
	'resources/views/assets/js/jquery.multiselect.js', 
	'node_modules/js-cookie/dist/js.cookie.min.js', 
	'resources/views/assets/js/front.js', 
	'resources/views/assets/js/custom.js'
], 'public/assets/js/app.js');
 
mix.copyDirectory('resources/views/assets/img', 'public/assets/img');

mix.copyDirectory('resources/views/assets/font', 'public/assets/css/font');

mix.minify(['public/assets/js/app.js', 'public/assets/js/app_admin.js', 'public/assets/css/app.css', 'public/assets/css/app_admin.css']);
 
 