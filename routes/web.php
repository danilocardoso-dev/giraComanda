<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\BulkCardPrintController;
use App\Http\Controllers\CancelledTabController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardPrintController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CashMovementController;
use App\Http\Controllers\CashPaymentDetailController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\ClosedCashSessionController;
use App\Http\Controllers\ClosedTabController;
use App\Http\Controllers\CompanyBranchController;
use App\Http\Controllers\CompanyCustomerSharingController;
use App\Http\Controllers\CompanyLogoController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerStatementController;
use App\Http\Controllers\CustomerSummaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\EstablishmentSettingController;
use App\Http\Controllers\ManagementReportController;
use App\Http\Controllers\MonthlyClosingController;
use App\Http\Controllers\MonthlyStatementDeliveryController;
use App\Http\Controllers\PlatformCompanyController;
use App\Http\Controllers\PlatformCompanyShowController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\PlatformInvoiceController;
use App\Http\Controllers\PlatformInvoicePaymentController;
use App\Http\Controllers\PlatformOwnerPasswordController;
use App\Http\Controllers\PlatformSubscriptionController;
use App\Http\Controllers\PlatformSupportSessionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReceivablePaymentController;
use App\Http\Controllers\RequiredPasswordController;
use App\Http\Controllers\ScannedTabController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\TabController;
use App\Http\Controllers\TabItemController;
use App\Http\Controllers\TabItemShareController;
use App\Http\Controllers\TabReceiptController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::view('/privacidade', 'legal.privacy')->name('privacy');
Route::view('/termos-de-uso', 'legal.terms')->name('terms');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::get('/esqueci-minha-senha', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/esqueci-minha-senha', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/senha-temporaria', [RequiredPasswordController::class, 'edit'])->name('password.required.edit');
    Route::put('/senha-temporaria', [RequiredPasswordController::class, 'update'])->name('password.required.update');
    Route::delete('/plataforma/suporte', [PlatformSupportSessionController::class, 'destroy'])
        ->middleware('platform.admin')
        ->name('platform.support.destroy');
    Route::get('/filiais/selecionar', [BranchContextController::class, 'index'])->middleware('password.changed')->name('branches.index');
    Route::post('/filiais/{branch}/ativar', [BranchContextController::class, 'update'])->middleware('password.changed')->name('branches.activate');

    Route::prefix('plataforma')->name('platform.')->middleware(['platform.admin', 'password.changed'])->group(function (): void {
        Route::get('/', PlatformDashboardController::class)->name('dashboard');
        Route::get('/empresas', [PlatformCompanyController::class, 'index'])->name('companies.index');
        Route::post('/empresas', [PlatformCompanyController::class, 'store'])->name('companies.store');
        Route::get('/empresas/{company}', PlatformCompanyShowController::class)->name('companies.show');
        Route::patch('/empresas/{company}', [PlatformCompanyController::class, 'update'])->name('companies.update');
        Route::put('/empresas/{company}/assinatura', [PlatformSubscriptionController::class, 'update'])->name('subscriptions.update');
        Route::post('/empresas/{company}/mensalidades', [PlatformInvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/empresas/{company}/mensalidades/{invoice}/pagamento', [PlatformInvoicePaymentController::class, 'store'])->name('invoices.payments.store');
        Route::put('/empresas/{company}/proprietarios/{user}/senha', [PlatformOwnerPasswordController::class, 'update'])->name('owners.password.update');
        Route::post('/empresas/{company}/suporte', [PlatformSupportSessionController::class, 'store'])->name('support.store');
        Route::get('/backups', [DatabaseBackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [DatabaseBackupController::class, 'store'])->middleware('throttle:3,1')->name('backups.store');
        Route::get('/backups/{filename}', [DatabaseBackupController::class, 'download'])
            ->where('filename', '[A-Za-z0-9._-]+')
            ->name('backups.download');
    });

    Route::middleware(['password.changed', 'branch.context'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/branding/logo', CompanyLogoController::class)->name('branding.logo');

        Route::middleware('role:admin,cashier,attendant')->group(function (): void {
            Route::post('/tabs/scan', ScannedTabController::class)->name('tabs.scan');
            Route::resource('tabs', TabController::class)->only(['index', 'store', 'show']);
            Route::post('/tabs/{tab}/items', [TabItemController::class, 'store'])->name('tabs.items.store');
            Route::delete('/tabs/{tab}/items/{item}', [TabItemController::class, 'destroy'])->name('tabs.items.destroy');
            Route::put('/tabs/{tab}/items/{item}/shares', [TabItemShareController::class, 'store'])->name('tabs.items.shares.store');
            Route::delete('/tabs/{tab}/items/{item}/shares', [TabItemShareController::class, 'destroy'])->name('tabs.items.shares.destroy');
        });

        Route::middleware('role:admin,cashier')->group(function (): void {
            Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);
            Route::get('/customers/{customer}/summary', CustomerSummaryController::class)->name('customers.summary');
            Route::get('/cards/print', BulkCardPrintController::class)->name('cards.print-bulk');
            Route::get('/cards/{card}/print', CardPrintController::class)->name('cards.print');
            Route::resource('cards', CardController::class)->only(['index', 'store', 'update']);
            Route::get('/cash', CashController::class)->name('cash.index');
            Route::get('/cash/payments/{payment}', CashPaymentDetailController::class)->name('cash.payments.show');
            Route::post('/cash/sessions', [CashSessionController::class, 'store'])->name('cash.sessions.store');
            Route::post('/cash/sessions/{cashSession}/movements', [CashMovementController::class, 'store'])->name('cash.movements.store');
            Route::post('/cash/sessions/{cashSession}/close', ClosedCashSessionController::class)->name('cash.sessions.close');
            Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
            Route::get('/monthly-closing', MonthlyClosingController::class)->name('monthly-closing.index');
            Route::post('/monthly-closing/customers/{customer}/sent', MonthlyStatementDeliveryController::class)->name('monthly-closing.sent.store');
            Route::get('/receivables/customers/{customer}', [ReceivableController::class, 'show'])->name('receivables.show');
            Route::get('/receivables/customers/{customer}/statement', CustomerStatementController::class)->name('receivables.statement');
            Route::post('/receivables/customers/{customer}/payments', ReceivablePaymentController::class)->name('receivables.payments.store');
            Route::post('/tabs/{tab}/close', ClosedTabController::class)->name('tabs.close');
            Route::get('/tabs/{tab}/receipt', TabReceiptController::class)->name('tabs.receipt');
        });

        Route::middleware('role:admin')->group(function (): void {
            Route::post('/tabs/{tab}/cancellations', CancelledTabController::class)->name('tabs.cancellations.store');
            Route::get('/audit', AuditLogController::class)->name('audit.index');
            Route::middleware('company.admin')->group(function (): void {
                Route::get('/administracao/filiais', [CompanyBranchController::class, 'index'])->name('company-branches.index');
                Route::post('/administracao/filiais', [CompanyBranchController::class, 'store'])->name('company-branches.store');
                Route::put('/administracao/filiais/{branch}', [CompanyBranchController::class, 'update'])->name('company-branches.update');
                Route::put('/administracao/compartilhamento-clientes', CompanyCustomerSharingController::class)->name('company-customer-sharing.update');
            });
            Route::get('/reports', ManagementReportController::class)->name('reports.index');
            Route::get('/settings', [EstablishmentSettingController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [EstablishmentSettingController::class, 'update'])->name('settings.update');
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::resource('products', ProductController::class)->except(['show', 'destroy']);
            Route::get('/products/{product}/stock', ProductStockController::class)->name('products.stock.show');
            Route::post('/products/{product}/stock-movements', [StockMovementController::class, 'store'])->name('products.stock-movements.store');
        });
    });
});
