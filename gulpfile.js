const gulp = require('gulp');
const cleanCSS = require('gulp-clean-css');
const rename = require('gulp-rename');

gulp.task('minify-css', () => {
  return gulp.src('assets/css/*.css')
    .pipe(cleanCSS())
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest('assets/css/min'));
});

// Tüm CSS dosyalarını izle ve değişikliklerde otomatik minify et
gulp.task('watch', () => {
  gulp.watch('assets/css/*.css', gulp.series('minify-css'));
});

// Varsayılan görevi çalıştır
gulp.task('default', gulp.series('minify-css', 'watch'));