<?php

namespace Tests\Unit;

use App\Helpers\ContentDispositionHelper;
use PHPUnit\Framework\TestCase;

class ContentDispositionHelperTest extends TestCase
{
    public function test_it_keeps_the_document_number_as_the_file_name(): void
    {
        $this->assertSame(
            'PAS-SO-10-26-0001.xml',
            ContentDispositionHelper::filename('PAS/SO/10/26/0001', 'sales-order-1')
        );
    }

    public function test_it_falls_back_when_the_document_number_is_missing(): void
    {
        $this->assertSame(
            'sales-order-42.xml',
            ContentDispositionHelper::filename(null, 'sales-order-42')
        );

        $this->assertSame(
            'delivery-order-7.xml',
            ContentDispositionHelper::filename('', 'delivery-order-7')
        );
    }

    public function test_it_never_returns_an_empty_file_name(): void
    {
        $this->assertSame(
            'download.xml',
            ContentDispositionHelper::filename(null, '')
        );
    }

    public function test_it_removes_path_separators_and_quotes(): void
    {
        $this->assertSame(
            'etc-passwd.xml',
            ContentDispositionHelper::filename('../../etc/passwd', 'fb')
        );

        $this->assertSame(
            'badname.xml',
            ContentDispositionHelper::filename('bad"name', 'fb')
        );
    }

    public function test_it_strips_control_characters_to_prevent_header_injection(): void
    {
        $filename = ContentDispositionHelper::filename("do\r\nX-Injected: 1", 'fb');

        $this->assertStringNotContainsString("\r", $filename);
        $this->assertStringNotContainsString("\n", $filename);
    }

    public function test_it_builds_a_quoted_attachment_header(): void
    {
        $this->assertSame(
            'attachment; filename="PAS-SO-10-26-0001.xml"',
            ContentDispositionHelper::attachmentHeader(
                ContentDispositionHelper::filename('PAS/SO/10/26/0001', 'fb')
            )
        );
    }

    public function test_it_keeps_the_extension_when_the_name_is_too_long(): void
    {
        $filename = ContentDispositionHelper::filename(str_repeat('a', 500), 'fb');

        $this->assertStringEndsWith('.xml', $filename);
        $this->assertLessThanOrEqual(120, strlen($filename));
    }
}
