<?php

use Apachish\WpRepo\Http\Controllers\DownloadController;
use Apachish\WpRepo\Http\Controllers\SiteRegisterController;
use Apachish\WpRepo\Http\Controllers\UpdateController;
use Apachish\WpRepo\Http\Controllers\WebhookController;
use Apachish\WpRepo\Http\Middleware\AuthenticateWpSite;
use Illuminate\Support\Facades\Route;

Route::post('webhook/{provider}', WebhookController::class)->name('wp-repo.webhook');

Route::get('check', [UpdateController::class, 'check'])
    ->middleware(AuthenticateWpSite::class)
    ->name('wp-repo.check');

Route::get('download/{version}', DownloadController::class)
    ->middleware('signed')
    ->name('wp-repo.download');

Route::post('sites/register', SiteRegisterController::class)->name('wp-repo.sites.register');
