<?php

use Illuminate\Support\Facades\Route;



#only guest accessible routes
Route::middleware('guest')->group(function () {
    Route::get('login', [\App\Http\Controllers\UserController::class, 'loginindex'])->name('login');
    Route::post('login', [\App\Http\Controllers\UserController::class, 'login'])->name('login.post');

});

    #only authenticated accessible routes
Route::middleware('auth')->group(function () {
    Route::post('logout', [\App\Http\Controllers\UserController::class, 'logout'])->name('logout');

    // route auth role = admin
    Route::middleware('role:admin')->group(function () {
        Route::get('users/data', [\App\Http\Controllers\UserController::class, 'tabledata'])->name('users.data');
        Route::match(['GET', 'POST'], 'data/data', [\App\Http\Controllers\DataController::class, 'tabledata'])->name('data.data');
        Route::match(['GET', 'POST'], 'data/all-ids', [\App\Http\Controllers\DataController::class, 'allIds'])->name('data.allIds');
        Route::get('categories/data', [\App\Http\Controllers\CategoryController::class, 'tabledata'])->name('categories.data');
        Route::get('packages/data', [\App\Http\Controllers\PackageController::class, 'tabledata'])->name('packages.data');
        Route::post('data/addbycsv', [\App\Http\Controllers\DataController::class, 'addByCsv'])->name('data.addbycsv');
        Route::get('packages/{package}/assign-data', [\App\Http\Controllers\PackageController::class, 'assignedData'])->name('packages.assignData.show');
        Route::post('packages/{package}/assign-data', [\App\Http\Controllers\PackageController::class, 'assignData'])->name('packages.assignData');
        Route::post('packages/{package}/unassign-data', [\App\Http\Controllers\PackageController::class, 'unassignData'])->name('packages.unassignData');
        Route::post('packages/{package}/assign-users', [\App\Http\Controllers\PackageController::class, 'assignUsers'])->name('packages.assignUsers');
        Route::get('annotations/manage', [\App\Http\Controllers\AnnotationController::class, 'manage'])->name('annotations.manage');
        Route::get('annotations/manage/table', [\App\Http\Controllers\AnnotationController::class, 'managementTable'])->name('annotations.manage.table');
        Route::get('annotations/manage/no-category-ids', [\App\Http\Controllers\AnnotationController::class, 'noCategoryAnnotationIds'])->name('annotations.manage.noCategoryIds');
        Route::post('annotations/manage/requeue', [\App\Http\Controllers\AnnotationController::class, 'requeue'])->name('annotations.manage.requeue');

        Route::resource('data', \App\Http\Controllers\DataController::class);
        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::resource('categories', \App\Http\Controllers\CategoryController::class);
        Route::resource('packages', \App\Http\Controllers\PackageController::class);
        Route::resource('package_data', \App\Http\Controllers\PackageDataController::class);
        Route::resource('user_packages', \App\Http\Controllers\UserPackageController::class);
    });



    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');


    Route::post('notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    #route resource for all models
    Route::resource('notifications', \App\Http\Controllers\NotificationController::class);

    Route::get('packages/{package}/annotations/work-item', [\App\Http\Controllers\AnnotationController::class, 'workItem'])->name('packages.annotations.workItem');
    Route::post('packages/{package}/annotations/save', [\App\Http\Controllers\AnnotationController::class, 'saveSelection'])->name('packages.annotations.save');
    Route::get('packages/{package}/annotations/session-map', [\App\Http\Controllers\AnnotationController::class, 'sessionMap'])->name('packages.annotations.sessionMap');
    Route::post('session-logs/end', [\App\Http\Controllers\SessionLogController::class, 'end'])->name('session-logs.end');
    Route::get('session-logs/history', [\App\Http\Controllers\SessionLogController::class, 'history'])->name('session-logs.history');
    Route::resource('session-logs', \App\Http\Controllers\SessionLogController::class)->only(['index', 'show']);
    Route::resource('annotations', \App\Http\Controllers\AnnotationController::class);
    Route::resource('validations', \App\Http\Controllers\ValidationController::class);

});

