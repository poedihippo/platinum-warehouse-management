<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\ServiceProvider;

class SchemaServiceProvider extends ServiceProvider
{
    /**
     * Laravel 11 rewrote `float()` / `double()` and removed `unsignedFloat()`:
     * the total/places arguments are dropped, so the old migrations would build
     * `float` / `double` columns instead of the `double(T,P)` columns production
     * got from Laravel 10 (MySQL rounds those on write). `legacyDouble()` keeps
     * the Laravel 10 DDL so a schema built from migrations (the test database)
     * still matches production. Laravel 10 defaults: float() = (8, 2).
     */
    public function boot(): void
    {
        Blueprint::macro('legacyDouble', function (string $column, int $total = 8, int $places = 2) {
            /** @var Blueprint $this */
            return $this->addColumn('legacyDouble', $column, compact('total', 'places'));
        });

        MySqlGrammar::macro('typeLegacyDouble', function (Fluent $column) {
            return "double({$column->total}, {$column->places})";
        });
    }
}
