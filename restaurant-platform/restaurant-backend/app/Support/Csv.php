<?php

declare(strict_types=1);

namespace App\Support;

/**
 * "منع CSV injection" — Excel/Sheets/LibreOffice treat a cell that *starts*
 * with `=`, `+`, `-`, `@`, a tab, or a carriage return as a formula to
 * evaluate when the file is opened, regardless of the column's intent
 * (the classic CSV/"formula" injection attack: a customer name of
 * `=HYPERLINK("http://evil","click me")` or `@SUM(1+1)*cmd|...` gets
 * silently executed by the spreadsheet app, not the browser or this
 * server — nothing here can be exploited server-side, only whoever later
 * opens the exported file). The standard OWASP mitigation is applied here:
 * prefix the value with a single quote, which every mainstream
 * spreadsheet app renders as a literal leading character rather than a
 * formula trigger, while leaving the visible text unchanged for a human
 * reader.
 */
final class Csv
{
    private const array DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function sanitizeCell(string|int|float|null $value): string
    {
        $value = (string) ($value ?? '');

        if ($value === '') {
            return $value;
        }

        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }
}
