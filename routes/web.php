<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password/{token}', function ($token) {
    $email = request('email');
    $frontend = env('FRONTEND_URL');
    if ($frontend) {
        $query = http_build_query(['token' => $token, 'email' => $email]);
        return redirect()->away(rtrim($frontend, '/') . '/reset-password?' . $query);
    }
    return response()->json(['token' => $token, 'email' => $email]);
})->name('password.reset');
