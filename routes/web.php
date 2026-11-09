<?php

use Apachish\WpRepo\Livewire\Connections;
use Apachish\WpRepo\Livewire\Logs;
use Apachish\WpRepo\Livewire\PackageDetail;
use Apachish\WpRepo\Livewire\Packages;
use Apachish\WpRepo\Livewire\Sites;
use Illuminate\Support\Facades\Route;

Route::get('/', Packages::class)->name('packages');
Route::get('packages/{package}', PackageDetail::class)->name('package');
Route::get('connections', Connections::class)->name('connections');
Route::get('sites', Sites::class)->name('sites');
Route::get('logs', Logs::class)->name('logs');
