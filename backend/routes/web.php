<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Fields of Deception API',
        'version' => '1.0.0',
    ]);
});
