<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/', [MemberController::class, 'page'])->name('home');

    Route::prefix('api/members')->group(function () {
        Route::get('/', [MemberController::class, 'search'])->name('members.search');
        Route::post('/', [MemberController::class, 'store'])->name('members.store');
        Route::get('/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::put('/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    });
});
