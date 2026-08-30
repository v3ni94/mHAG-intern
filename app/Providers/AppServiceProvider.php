<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Storage\DocumentStorageInterface::class,
            \App\Services\Storage\FlysystemDocumentStorage::class,
        );

        $this->app->bind(
            \App\Services\Signature\SignatureServiceInterface::class,
            \App\Services\Signature\ManualSignatureAdapter::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // HTTPS erzwingen: alle generierten Links laufen über APP_URL (Abschnitt 1).
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Model::preventLazyLoading(! $this->app->isProduction());

        // Betragsausgabe im Organisationsformat: @money($betrag)
        Blade::directive('money', function (string $expression) {
            return "<?php echo e(format_money($expression)); ?>";
        });

        Blade::directive('date', function (string $expression) {
            return "<?php echo e(format_date($expression)); ?>";
        });

        \Illuminate\Pagination\Paginator::useBootstrapFive();
    }
}
