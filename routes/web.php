<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/', [MemberController::class, 'page'])->name('home');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/members/export', [SettingsController::class, 'exportMembers'])->name('members.export');
    Route::post('/members/import', [SettingsController::class, 'importMembers'])->name('members.import');

    Route::prefix('api/members')->group(function () {
        Route::get('/', [MemberController::class, 'search'])->name('members.search');
        Route::get('/exists', [MemberController::class, 'exists'])->name('members.exists');
        Route::post('/', [MemberController::class, 'store'])->name('members.store');
        Route::get('/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::put('/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    });
});
