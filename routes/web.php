<?php

use App\Filament\Client\Pages\Auth\AcceptInvitation;
use App\Filament\Client\Pages\Auth\Invitation;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\Home;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PayrollDownloadController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ExtendedAuthenticate;
use Illuminate\Support\Facades\Route;
use App\Models\EmailTemplate;

Route::name('payment.')->prefix('pay')->withoutMiddleware([\App\Http\Middleware\VerifyBillableIsSubscribed::class])->group(function () {
    Route::get('{trx}/cancel', [PaymentController::class, 'cancel'])->name('cancel');
    Route::post('initiate', [PaymentController::class, 'initiate'])->name('initiate');
    Route::get('info', [PaymentController::class, 'info'])->name('info');
});

Route::any('pay/callback/{gateway}', [PaymentController::class, 'verify'])->name('payments.callback');
Route::any('/success', [PaymentController::class, 'success'])->name('payments.success');
/**
 *
 *  Payment Intent Routes (Stripe)
 *
 */
Route::get('/payments/subscription/{gateway}/callback/', [PaymentController::class, 'verify'])->name('subscription.verify');
Route::get('/', [Home::class, 'index'])->name('home.index');
Route::get('/sync', [SyncController::class, 'index'])->name('sync.index');
Route::get('/privacy-policy', [Home::class, 'privacy']);
Route::get('/terms-conditions', [Home::class, 'terms']);
Route::get('/guide', [Home::class, 'guide']);

Route::get('auth/register/google', [GoogleAuthController::class, 'redirect'])->name('auth.register.google');
Route::get('auth/login/google', [GoogleAuthController::class, 'redirect'])->name('auth.login.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::get('/payroll/{payroll}/download', [PayrollDownloadController::class, 'download'])->name('payroll.download');
Route::get('/payslip/{payroll}', [PayslipController::class, 'show'])->name('payslip.show');

//Route::get('/offcycle-payroll/{offCyclePayroll}/download-pdf', [PayrollDownloadController::class, 'downloadOffCycle'])->name('offcycle-payroll.download_pdf');
Route::get('/admin/preview/{id}', function ($id) {
    $record = EmailTemplate::findOrFail($id);
    return view('filament.pages.emails.preview', [
        'content' => html_entity_decode($record->body),
    ]);
})->name('template.preview');

Route::post('/paypal/subscription/approved', [PaymentController::class, 'paypalSubscriptionApproved'])->name('paypal.subscription.approved');
