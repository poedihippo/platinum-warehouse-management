<?php

namespace Database\Seeders;

use App\Models\ProductUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class LoyaltyEligibleProductUnitSeeder extends Seeder
{
    /**
     * The curated set of product_units.id allowed to ever earn loyalty
     * points (203 of 629). Idempotent and authoritative: every run sets
     * loyalty_eligible = true for exactly these IDs and false for every
     * other unit, so re-running after the curated list changes correctly
     * un-flags whatever fell off it. Only touches loyalty_eligible —
     * never description, price, or any other column.
     */
    private const ELIGIBLE_IDS = [
        91, 92, 93, 94, 97, 99, 101, 102, 103, 104, 108, 111, 114, 115, 120,
        121, 122, 123, 124, 125, 127, 131, 133, 134, 139, 140, 141, 142, 143,
        144, 145, 146, 178, 182, 184, 185, 189, 226, 228, 237, 275, 276, 277,
        284, 287, 291, 292, 311, 314, 316, 319, 321, 323, 325, 331, 342, 347,
        354, 367, 368, 390, 391, 402, 405, 406, 416, 418, 422, 430, 432, 437,
        498, 504, 506, 511, 517, 520, 523, 525, 531, 534, 546, 550, 552, 555,
        557, 561, 565, 569, 571, 580, 594, 596, 602, 604, 606, 607, 609, 611,
        614, 616, 617, 621, 627, 628, 629, 694, 696, 698, 699, 701, 703, 705,
        706, 731, 737, 738, 740, 743, 745, 747, 749, 751, 752, 754, 756, 757,
        762, 763, 764, 769, 770, 772, 777, 779, 780, 781, 783, 785, 786, 788,
        803, 807, 813, 814, 824, 825, 829, 832, 837, 843, 846, 858, 859, 864,
        868, 874, 878, 879, 885, 887, 891, 895, 898, 901, 904, 907, 910, 913,
        915, 918, 923, 924, 926, 927, 928, 929, 930, 931, 933, 934, 935, 936,
        937, 938, 939, 1028, 1029, 1030, 1038, 1041, 1042, 1045, 1047, 1050,
        1052, 1055, 1056, 1058, 1067, 1070, 1072, 1074,
    ];

    public function run(): void
    {
        $existingIds = ProductUnit::whereIn('id', self::ELIGIBLE_IDS)->pluck('id')->all();
        $missingIds = array_values(array_diff(self::ELIGIBLE_IDS, $existingIds));

        if ($missingIds) {
            $message = sprintf(
                '%d of %d curated product_unit IDs do not exist and were skipped: %s',
                count($missingIds),
                count(self::ELIGIBLE_IDS),
                implode(',', $missingIds)
            );

            Log::warning('loyalty.eligible_product_units.missing_ids', [
                'missing_ids' => $missingIds,
                'missing_count' => count($missingIds),
                'curated_count' => count(self::ELIGIBLE_IDS),
            ]);

            $this->command?->warn($message);
        }

        ProductUnit::whereIn('id', self::ELIGIBLE_IDS)->update(['loyalty_eligible' => true]);
        ProductUnit::whereNotIn('id', self::ELIGIBLE_IDS)->update(['loyalty_eligible' => false]);

        $this->command?->info(sprintf(
            '%d of %d curated product units marked loyalty_eligible = true.',
            count($existingIds),
            count(self::ELIGIBLE_IDS)
        ));
    }
}
