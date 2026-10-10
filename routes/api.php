<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\UserSectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth.login')->name('login');
    Route::post('verify', [AuthController::class, 'verify'])->middleware('throttle:auth.challenge')->name('verify');
    Route::post('resend', [AuthController::class, 'resend'])->middleware('throttle:auth.challenge')->name('resend');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::patch('me/password', [AuthController::class, 'updatePassword'])->name('password');
    });

    Route::get('users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');

    Route::middleware('can:users.manage')->group(function () {
        Route::apiResource('users', UserController::class)->except('index');
        Route::put('users/{user}/sections', [UserSectionController::class, 'update'])->name('users.sections.update');
        Route::put('users/{user}/role', [UserRoleController::class, 'update'])->name('users.role.update');
    });

    Route::middleware('can:roles.manage')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    });

    Route::get('sections', [SectionController::class, 'index'])->middleware('can:sections.view')->name('sections.index');
});
