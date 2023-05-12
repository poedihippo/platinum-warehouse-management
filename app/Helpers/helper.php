<?php

if (!function_exists('tenancy')) {
    function tenancy(): \App\Services\TenancyService
    {
        return app(\App\Services\TenancyService::class);
    }
}

if (!function_exists('activeCompany')) {
    function activeCompany(): ?\App\Models\Company
    {
        return app(\App\Services\TenancyService::class)->getActiveCompany();
    }
}

if (!function_exists('activeTenant')) {
    function activeTenant(): ?\App\Models\Tenant
    {
        return app(\App\Services\TenancyService::class)->getActiveTenant();
    }
}

if (!function_exists('user')) {
    function user(): ?\App\Models\User
    {
        return auth()->user() ?? auth('sanctum')->user();
        // return tenancy()->checkUserLogin();
    }
}

if (!function_exists('arrayFilterAndReindex')) {
    /**
     * remove array where value is null and reindex from 0
     */
    function arrayFilterAndReindex(array $values = []): array
    {
        return array_values(array_filter($values)) ?? [];
    }
}

if (!function_exists('rupiah')) {
    function rupiah(int|string $number = null, $formatCurrency = false): string
    {
        if (is_null($number)) return number_format(0, 0, ',', '.');

        if ($formatCurrency) {
            return "Rp " . number_format((float)$number, 0, ',', '.');
        }

        return number_format((float)$number, 0, ',', '.');
    }
}

if (!function_exists('filterPrice')) {
    function filterPrice($number)
    {
        $number = str_replace('Rp', '', $number);
        $number = str_replace('Rp.', '', $number);
        $number = str_replace(',', '', $number);
        $number = str_replace('.', '', $number);
        return intval($number);
    }
}

if (!function_exists('filterPriceFloat')) {
    function filterPriceFloat($number)
    {
        $number = str_replace('Rp', '', $number);
        $number = str_replace('Rp.', '', $number);
        $number = str_replace(',', '', $number);
        // $number = str_replace('.', '', $number);
        return doubleval($number);
    }
}

if (!function_exists('setTotalAmountExportedBatch')) {
    function setTotalAmountExportedBatch($value)
    {
        return $value;
        // if ($value == 0) return $value;

        // if ($value > 0) return -$value;

        // return abs($value);
    }
}

if (!function_exists('getMonthSelections')) {
    function getMonthSelections(): array
    {
        $startYear = '2023';
        $endYear = date('Y');
        $data = [];

        for ($iYear = $startYear; $iYear <= $endYear; $iYear++) {
            for ($iMonth = 1; $iMonth <= 12; $iMonth++) {
                $data[substr("0{$iMonth}", -2) . '-' . $iYear] = date("F", mktime(0, null, null, $iMonth)) . '-' . $iYear;
            }
        }

        return $data ?? [];
    }
}

if (!function_exists('filterNA')) {
    function filterNA($value)
    {
        if (is_numeric(strpos($value, 'N/A')) || is_numeric(strpos($value, 'n/a'))) return null;
        return trim($value);
    }
}
