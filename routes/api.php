<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectItemCommentController;
use App\Http\Controllers\Api\ProjectItemController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\SectionWorkController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\UserSectionController;
use App\Http\Controllers\Api\WorkItemController;
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

    Route::middleware('can:projects.create')->group(function () {
        Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    });

    Route::middleware('can:project_items.manage')->group(function () {
        Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
        Route::get('material-categories', [MaterialController::class, 'categories'])->name('materials.categories');
    });

    Route::get('projects', [ProjectController::class, 'index'])->middleware('can:projects.view')->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->middleware('can:projects.create')->name('projects.store');

    Route::middleware('can:projects.close')->group(function () {
        Route::post('projects/{project}/close', [ProjectController::class, 'close'])->name('projects.close');
        Route::post('projects/{project}/reopen', [ProjectController::class, 'reopen'])->name('projects.reopen');
    });

    Route::middleware('can:project_items.manage')->group(function () {
        Route::post('projects/{project}/items', [ProjectItemController::class, 'store'])->name('projects.items.store');
        Route::delete('project-items/{projectItem}', [ProjectItemController::class, 'destroy'])->name('project-items.destroy');
    });

    Route::patch('project-items/{projectItem}', [ProjectItemController::class, 'update'])->name('project-items.update');
    Route::post('project-items/{projectItem}/comments', [ProjectItemCommentController::class, 'store'])
        ->middleware('can:comments.create')
        ->name('project-items.comments.store');

    /*
     * Section operators: only their sections' work (admins: every section).
     */
    Route::prefix('work')->name('work.')->middleware('can:project_items.work')->group(function () {
        Route::get('sections', [SectionWorkController::class, 'sections'])->name('sections');
        Route::middleware('can:work,section')->group(function () {
            Route::get('sections/{section}/projects', [SectionWorkController::class, 'projects'])->name('sections.projects');
            Route::get('sections/{section}/projects/{project}/items', [SectionWorkController::class, 'items'])->name('sections.items');
            Route::post('sections/{section}/projects/{project}/items', [WorkItemController::class, 'store'])->name('sections.items.store');
        });
        Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
        Route::get('material-categories', [MaterialController::class, 'categories'])->name('materials.categories');
        Route::patch('items/{projectItem}', [WorkItemController::class, 'update'])->name('items.update');
        Route::post('items/{projectItem}/comments', [WorkItemController::class, 'comment'])
            ->middleware('can:comments.create')
            ->name('items.comments.store');
    });
});
