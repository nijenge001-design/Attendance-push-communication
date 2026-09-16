<?php

use App\Http\Controllers\CdataController;
use App\Http\Controllers\DeviceCmdController;
use App\Http\Controllers\GetRequestController;
use App\Http\Controllers\PingController;
use Illuminate\Support\Facades\Route;

Route::prefix('iclock')
    ->name('iclock.')
    ->middleware('log-request')
    ->group(function () {
// Handshake + data upload (ATTLOG, OPERLOG, BIODATA, ATTPHOTO, USERINFO, …)
        Route::match(['get', 'post'], 'cdata', CdataController::class)
            ->name('iclock.cdata');

        // Device polls for pending commands (+ optional INFO stats)
        Route::get('getrequest', GetRequestController::class)
            ->name('iclock.getrequest');

        // Device reports command execution results
        Route::post('devicecmd', DeviceCmdController::class)
            ->name('iclock.devicecmd');

        // Lightweight heartbeat while busy with large uploads
        Route::match(['get', 'post'], 'ping', PingController::class)
            ->name('iclock.ping');
    });
