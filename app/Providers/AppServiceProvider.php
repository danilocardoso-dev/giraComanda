<?php

namespace App\Providers;

use App\Contracts\DatabaseExporter;
use App\Contracts\DatabaseImporter;
use App\Models\Card;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\EstablishmentSetting;
use App\Models\MonthlyStatementDelivery;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\StockMovement;
use App\Models\Tab;
use App\Models\TabItem;
use App\Models\TabItemShare;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\MySqlDatabaseExporter;
use App\Services\MySqlDatabaseImporter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseExporter::class, MySqlDatabaseExporter::class);
        $this->app->bind(DatabaseImporter::class, MySqlDatabaseImporter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            User::class,
            Customer::class,
            Card::class,
            Product::class,
            StockMovement::class,
            CashSession::class,
            CashMovement::class,
            Tab::class,
            TabItem::class,
            TabItemShare::class,
            Receivable::class,
            Payment::class,
            MonthlyStatementDelivery::class,
            EstablishmentSetting::class,
        ] as $auditedModel) {
            $auditedModel::observe(AuditObserver::class);
        }

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by(Str::transliterate($email.'|'.$request->ip()));
        });

        RateLimiter::for('menu-orders', fn (Request $request): Limit => Limit::perMinute(6)->by('menu-orders|'.$request->route('qrToken')));

        RateLimiter::for('menu-status', fn (Request $request): Limit => Limit::perMinute(20)->by('menu-status|'.$request->route('qrToken')));

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(3)->by(Str::transliterate($email.'|'.$request->ip()));
        });
    }
}
