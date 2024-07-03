<?php

namespace OnePilot\Client;

use Route;

Route::group([
    'prefix' => 'onepilot',
    'namespace' => 'OnePilot\Client\Controllers',
    'middleware' => [Middlewares\Authentication::class],
], function () {
    Route::post('core/update', 'Core@update');
    Route::post('plugin/install', 'Extensions@install');
    Route::post('plugin/update', 'Plugins@update');

    Route::post('composer/update', 'Composer@update');
    Route::post('composer/core-update', 'Composer@coreUpdate');

    Route::post('validate', 'Validate@validate');
    Route::post('errors', 'Errors@browse');
    Route::post('mail-tester', 'MailTester@send');
    Route::post('ping', 'Ping@ping');

    Route::post('backups/check', 'Backups@check');
    Route::post('backups/browse', 'Backups@browse');
});
