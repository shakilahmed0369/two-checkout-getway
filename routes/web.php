<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /** Payment routes */
    // Route::get('payment', [PaymentController::class, 'index'])->name('payment.index');
    Route::get('payment', [PaymentController::class, 'index'])->name('payment.create');
    Route::post('payment', [PaymentController::class, 'processPayment'])->name('payment.store');

    Route::get('payment/success', [PaymentController::class, 'success'])->name('payment.success');

    Route::get('payment/cancel', function(HttpRequest $request) {
        dd($request->all());
    })->name('payment.cancel');

});

require __DIR__.'/auth.php';
