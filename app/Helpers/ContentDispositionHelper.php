<?php

namespace App\Helpers;

/**
 * Builds safe Content-Disposition headers for file download endpoints.
 *
 * Document numbers are rendered from a configurable template (see CompanyEnum::invoiceNumberFormat)
 * and therefore may contain characters that are unsafe for a download name, e.g. the path
 * separator of "PAS/SO/10/26/01". Such a value must never be sent to the browser verbatim.
 */
class ContentDispositionHelper
{
    /**
     * Maximum length of the file name (including extension) kept in the header.
     */
    private const MAX_FILENAME_LENGTH = 120;

    /**
     * Build a safe download file name from a document number.
     *
     * @param  string|null  $documentNumber  Raw document number, e.g. the sales order or delivery order number.
     * @param  string  $fallback  Used when the document number is missing or unusable.
     * @param  string  $extension  File extension without the leading dot.
     */
    public static function filename(?string $documentNumber, string $fallback, string $extension = 'xml'): string
    {
        $base = static::sanitize($documentNumber);

        if ($base === '') {
            $base = static::sanitize($fallback);
        }

        if ($base === '') {
            $base = 'download';
        }

        $extension = static::sanitize($extension);

        if ($extension === '') {
            return $base;
        }

        // Keep the name within a sane length without cutting off the extension.
        $maxBaseLength = max(1, self::MAX_FILENAME_LENGTH - strlen($extension) - 1);

        if (strlen($base) > $maxBaseLength) {
            $base = substr($base, 0, $maxBaseLength);
        }

        return $base.'.'.$extension;
    }

    /**
     * Build the Content-Disposition header value for an attachment.
     */
    public static function attachmentHeader(string $filename): string
    {
        return 'attachment; filename="'.static::sanitize($filename).'"';
    }

    /**
     * Strip everything that is unsafe for an HTTP header value or for a file name.
     */
    private static function sanitize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Control characters, including CR/LF that could be used for header injection.
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
        // Path separators would let the name escape the download directory.
        $value = str_replace(['/', '\\'], '-', $value);
        // Quotes would break out of the quoted-string of the header.
        $value = str_replace('"', '', $value);
        // Collapse the separators produced above and trim the noisy edges.
        $value = preg_replace('/-{2,}/', '-', $value) ?? '';
        $value = trim($value, "- \t.");

        return $value;
    }
}
