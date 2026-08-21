<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentNumberFormatController;
use App\Http\Controllers\Api\V1\DocumentReferenceController;
use App\Http\Controllers\Api\V1\PegawaiReferenceController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SppdController;
use App\Http\Controllers\Api\V1\SppdVerificationController;
use App\Http\Controllers\Api\V1\SptAssigneeController;
use App\Http\Controllers\Api\V1\SptController;
use App\Http\Controllers\Api\V1\UnitReferenceController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::get('/', static fn () => response()->json([
        'status' => 'ok',
        'version' => 'v1',
        'service' => config('app.name'),
    ]));

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', static fn (Request $request) => response()->json(['data' => $request->user()?->load('role')]));
        Route::get('/references/pegawai', [PegawaiReferenceController::class, 'index']);
        Route::get('/references/units', [UnitReferenceController::class, 'index']);
        Route::get('/references/{referenceType}', [DocumentReferenceController::class, 'index'])
            ->where('referenceType', 'mata-anggaran|transportasi|tingkat-perjalanan|jenis-perjalanan');
        Route::get('/dashboard', DashboardController::class);
        Route::get('/spts', [SptController::class, 'index']);
        Route::post('/spts', [SptController::class, 'store']);
        Route::get('/spts/{spt}', [SptController::class, 'show'])->whereUuid('spt');
        Route::patch('/spts/{spt}', [SptController::class, 'update'])->whereUuid('spt');
        Route::patch('/spts/{spt}/archive', [SptController::class, 'archive'])->whereUuid('spt');
        Route::delete('/spts/{spt}', [SptController::class, 'destroy'])->whereUuid('spt');
        Route::get('/spts/{spt}/print', [DocumentController::class, 'spt'])->whereUuid('spt');
        Route::get('/spts/{spt}/assignees', [SptAssigneeController::class, 'index'])->whereUuid('spt');
        Route::post('/spts/{spt}/assignees', [SptAssigneeController::class, 'store'])->whereUuid('spt');
        Route::post('/spts/{spt}/sppds', [SppdController::class, 'store'])->whereUuid('spt');
        Route::get('/sppds/{sppd}', [SppdController::class, 'show'])->whereUuid('sppd');
        Route::get('/sppds/{sppd}/preview', [DocumentController::class, 'previewSppd'])->whereUuid('sppd');
        Route::get('/sppds/{sppd}/print', [DocumentController::class, 'sppd'])->whereUuid('sppd');
        Route::get('/sppds/{sppd}/visum', [DocumentController::class, 'visum'])->whereUuid('sppd');
        Route::patch('/sppds/{sppd}', [SppdController::class, 'update'])->whereUuid('sppd');
        Route::delete('/sppds/{sppd}', [SppdController::class, 'destroy'])->whereUuid('sppd');
        Route::patch('/sppds/{sppd}/verification', [SppdVerificationController::class, 'update'])
            ->whereUuid('sppd');

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        });

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:users.manage,roles.manage');

        Route::middleware('permission:roles.manage')->group(function () {
            Route::post('/roles', [RoleController::class, 'store']);
            Route::patch('/roles/{role}', [RoleController::class, 'update']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::post('/references/{referenceType}', [DocumentReferenceController::class, 'store'])
                ->where('referenceType', 'mata-anggaran|transportasi|tingkat-perjalanan|jenis-perjalanan');
            Route::patch('/references/{referenceType}/{documentReference}', [DocumentReferenceController::class, 'update'])
                ->where('referenceType', 'mata-anggaran|transportasi|tingkat-perjalanan|jenis-perjalanan');
            Route::delete('/references/{referenceType}/{documentReference}', [DocumentReferenceController::class, 'destroy'])
                ->where('referenceType', 'mata-anggaran|transportasi|tingkat-perjalanan|jenis-perjalanan');
            Route::get('/settings/document-number-formats', [DocumentNumberFormatController::class, 'show']);
            Route::put('/settings/document-number-formats', [DocumentNumberFormatController::class, 'update']);
        });
    });
});
