<?php

namespace Tests\Unit;

use App\Enums\CompanyEnum;
use App\Enums\ImportType;
use App\Enums\PaymentType;
use App\Enums\UserType;
use App\Exports\VoucherExport;
use PHPUnit\Framework\TestCase;

class BaseEnumTest extends TestCase
{
    public function test_description_matches_the_legacy_friendly_name(): void
    {
        $this->assertSame('Customer event', UserType::CustomerEvent->getDescription());
        $this->assertSame('Spg', UserType::Spg->getDescription());
        $this->assertSame('Credit card', PaymentType::CREDIT_CARD->getDescription());
        $this->assertSame('Qris', PaymentType::QRIS->getDescription());
        $this->assertSame('Voucher', ImportType::VOUCHER->getDescription());
    }

    public function test_coerce_accepts_value_key_or_case(): void
    {
        $this->assertSame(ImportType::VOUCHER, ImportType::coerce('voucher'));
        $this->assertSame(ImportType::VOUCHER, ImportType::coerce('VOUCHER'));
        $this->assertSame(ImportType::VOUCHER, ImportType::coerce(ImportType::VOUCHER));
        $this->assertNull(ImportType::coerce('unknown'));
        $this->assertNull(ImportType::coerce(null));
    }

    public function test_has_value_strict_and_loose(): void
    {
        $this->assertTrue(CompanyEnum::hasValue('pas'));
        $this->assertFalse(CompanyEnum::hasValue('PAS'));
        $this->assertFalse(CompanyEnum::hasValue(null));
        $this->assertTrue(CompanyEnum::hasValue('pa', false));
    }

    public function test_is_and_is_not_accept_cases_and_values(): void
    {
        $this->assertTrue(UserType::Spg->is(UserType::Spg));
        $this->assertTrue(UserType::Spg->is('spg'));
        $this->assertFalse(UserType::Spg->is('admin'));
        $this->assertTrue(UserType::Admin->isNot(UserType::Reseller));
        $this->assertTrue(UserType::Admin->in([UserType::Reseller, 'admin']));
    }

    public function test_methods_still_resolve_per_case(): void
    {
        $this->assertSame('{company}/{type}/{month}/{year}/{sequence}', CompanyEnum::PAS->invoiceNumberFormat());
        $this->assertSame('{type}/{company}/{month}/{year}/{sequence}', CompanyEnum::PA->invoiceNumberFormat());
        $this->assertSame(VoucherExport::class, ImportType::VOUCHER->getExporter());
    }
}
