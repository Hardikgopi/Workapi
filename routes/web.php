<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/r2-test', function () {
    Storage::disk('r2')->put('r2-test1.txt', 'R2 is not working');
    return 'R2 connection successful';
});
Route::get('/', function () {
    return view('welcome');
});
