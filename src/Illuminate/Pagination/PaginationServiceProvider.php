<?php

namespace Illuminate\Pagination;

use Illuminate\Support\ServiceProvider;

class PaginationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'pagination');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/resources/views' => $this->app->resourcePath('views/vendor/pagination'),
            ], 'laravel-pagination');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        Paginator::viewFactoryResolver(function () {
            return $this->app['view'];
        });

        Paginator::currentPathResolver(function () {
            return $this->app['request']->getUri();
        });

        Paginator::currentPageResolver(function ($pageName = 'page') {
            $page = $this->app['request']->input($pageName);

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }

            return 1;
        });

        Paginator::perPageResolver(function ($default) {
            $request= $this->app['request'];

            if ($request->hasCookie('Pagination-PerPage')) {
                $perPage = $request->cookie('Pagination-PerPage');

                if (filter_var($perPage, FILTER_VALIDATE_INT) !== false && (int) $perPage >= 1) {
                    return $perPage;
                }
            }

            return $default;
        });
    }
}
