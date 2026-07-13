<?php

namespace Database\Seeders;

use App\Models\ProductUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class LoyaltyEligibleProductUnitSeeder extends Seeder
{
    /**
     * The curated set of product_units.code allowed to ever earn loyalty
     * points (203 codes). Matched by code, not id: the source spreadsheet's
     * id column was independently sorted and does not line up with its own
     * rows, but name/code stayed aligned. Idempotent and authoritative:
     * every run sets loyalty_eligible = true for units whose code is in
     * this list and false for every other unit, so re-running after the
     * curated list changes correctly un-flags whatever fell off it. Only
     * touches loyalty_eligible — never description, price, or any other
     * column.
     */
    private const ELIGIBLE_CODES = [
        'CZF010', 'CZF009', 'CZF008', 'CZM021', 'CZM015', 'CZM007', 'CZM009',
        'CZM010', 'CZM014', 'CZB001', 'CEX500', 'CZM028', 'CZM001', 'CZM002',
        'CZG002', 'CZG001', 'CZG003', 'CZM020', 'CZA001', 'CZM023', 'CZM016',
        'CZM017', 'CZM011', 'CZM012', 'CZM025', 'CZM018', 'CZM036', 'CZM035',
        'CZM032', 'CZM031', 'CZM034', 'CZM033', 'CZF005', 'CZF001', 'CZF002',
        'CZF003', 'CZF004', 'HEL002', 'HS0201', 'HS0202', 'HS0205', 'HS0204',
        'HS0203', 'PAO032', 'J01L01', 'J01L03', 'J01L12', 'J01M01', 'J01M03',
        'J01M12', 'J01S01', 'J01S02', 'J10K13', 'J10K14', 'J14L14', 'J14L13',
        'J14M03', 'J14M13', 'J16B12', 'J16B11', 'J06L04', 'J06L06', 'J06M04',
        'J06M06', 'J03L01', 'J03L03', 'J03L12', 'J03L02', 'J03M01', 'J03M03',
        'J03M12', 'J03M02', 'J03S02', 'J01S06', 'J01S07', 'J02S17', 'J02S18',
        'PAO018', 'PAO019', 'J13001', 'J13002', 'J16B10', 'J8ML09', 'J8ML11',
        'J09MG9', 'J09MG6', 'J8ML03', 'J8ML01', 'J8MM03', 'J8MM01', 'J11MB1',
        'J01L13', 'J01L14', 'J01M15', 'J01M14', 'J01S04', 'J01S05', 'J02L13',
        'J02L14', 'J02M14', 'J02M13', 'J02S16', 'J02S15', 'J07L02', 'J07M02',
        'J12L01', 'J12L03', 'J12L14', 'J12L02', 'J12M01', 'J12M03', 'J12M14',
        'J12M02', 'J12S01', 'J12S02', 'J02L01', 'J02L12', 'J02L04', 'J02L03',
        'J02M01', 'J02M07', 'J02M12', 'J02M03', 'J02S01', 'J02S02', 'J02S12*',
        'PAO022', 'J05L01', 'J05L06', 'J05L05', 'J05M02', 'J05M07', 'J05M08',
        'J15Y03', 'J15Y05', 'J15Y02', 'J15Y04', 'J10K08', 'PAO014', 'PAO031',
        'MAT01B', 'MAT004', 'MAT003', 'MAT002', 'MAT001', 'MATD02', 'MATD03',
        'MICL02', 'MICS02', 'MIGF27', 'MIGF26', 'MIGF30', 'MIGF15', 'MIGF17',
        'MIGF16', 'MIGF47', 'MIGF43', 'MIGF44', 'MIGF35', 'MIGF34', 'MIGF38',
        'MIGF02', 'MIHL02', 'MIHM02', 'MIHS02', 'MISL02', 'MISM02', 'MISS02',
        'MIWL02', 'MIWM02', 'MIWS02', 'N-ST07', 'N-ST08', 'N-ST01', 'N-ST02',
        'N-ST03', 'N-ST05', 'N-ST04', 'N-ST06', 'N-XP01', 'N-XP02', 'N-XP03',
        'N-XP04', 'N-XP05', 'N-S302', 'N-S301', 'SRGN01', 'SRGN02', 'SRGN03',
        'TACB02', 'TACA02', 'TACD02', 'TACF01', 'TACF02', 'TACM02', 'TACM03',
        'TACB03', 'TACT02', 'TACM06', 'UV Immersion 25 watt', 'UVL001',
        'UVL003', 'UVL005',
    ];

    public function run(): void
    {
        $matches = ProductUnit::whereIn('code', self::ELIGIBLE_CODES)
            ->get(['id', 'code'])
            ->groupBy('code');

        $matchedCodes = $matches->keys()->all();
        $unmatchedCodes = array_values(array_diff(self::ELIGIBLE_CODES, $matchedCodes));
        $duplicateCodes = $matches->filter(fn ($rows) => $rows->count() > 1)
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        if ($unmatchedCodes) {
            $message = sprintf(
                '%d of %d curated codes matched no product_unit and were skipped: %s',
                count($unmatchedCodes),
                count(self::ELIGIBLE_CODES),
                implode(',', $unmatchedCodes)
            );

            Log::warning('loyalty.eligible_product_units.unmatched_codes', [
                'unmatched_codes' => $unmatchedCodes,
                'unmatched_count' => count($unmatchedCodes),
                'curated_count' => count(self::ELIGIBLE_CODES),
            ]);

            $this->command?->warn($message);
        }

        if ($duplicateCodes) {
            $message = sprintf(
                '%d curated codes matched more than one product_unit row: %s',
                count($duplicateCodes),
                json_encode($duplicateCodes)
            );

            Log::warning('loyalty.eligible_product_units.duplicate_codes', [
                'duplicate_codes' => $duplicateCodes,
            ]);

            $this->command?->warn($message);
        }

        $eligibleIds = $matches->flatten()->pluck('id')->all();

        ProductUnit::whereIn('id', $eligibleIds)->update(['loyalty_eligible' => true]);
        ProductUnit::whereNotIn('id', $eligibleIds)->update(['loyalty_eligible' => false]);

        $this->command?->info(sprintf(
            '%d of %d curated codes matched (%d product_unit rows) marked loyalty_eligible = true.',
            count($matchedCodes),
            count(self::ELIGIBLE_CODES),
            count($eligibleIds)
        ));
    }
}
