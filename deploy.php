<?php
/*
 * This file has been generated automatically.
 * Please change the configuration for correct use deploy.
 */

require 'recipe/composer.php';

// Set configurations
set('repository', 'git@github.com:lislon/lfs-admin.git');
set('shared_files', ['src/settings.php']);
set('shared_dirs', ['logs']);
set('writable_dirs', ['logs']);

serverList('deploy.yml');


/**
* Restart php-fpm on success deploy.
*/
task('php-fpm:restart', function () {
   // Attention: The user must have rights for restart service
   // Attention: the command "sudo /bin/systemctl restart php-fpm.service" used only on CentOS system
   // /etc/sudoers: username ALL=NOPASSWD:/bin/systemctl restart php-fpm.service
   run('sudo /bin/systemctl restart php-fpm.service');
})->desc('Restart PHP-FPM service');

task('pwd', function () {
    $result = run('pwd');
    writeln("Current dir: $result");
});

task('test', function () {
    writeln('Hello world');
});


after('success', 'php-fpm:restart');

after('deploy:update_code', 'deploy:shared');