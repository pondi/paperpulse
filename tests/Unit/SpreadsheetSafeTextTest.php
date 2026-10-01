<?php

declare(strict_types=1);

use App\Support\SpreadsheetSafeText;

it('neutralizes spreadsheet formula prefixes and leading controls', function (string $value) {
    expect(SpreadsheetSafeText::format($value))->toBe("'".$value);
})->with([
    '=SUM(1,2)',
    '+SUM(1,2)',
    '-SUM(1,2)',
    '@SUM(1,2)',
    '  =SUM(1,2)',
    "\t=SUM(1,2)",
    "\r+SUM(1,2)",
    "\n-SUM(1,2)",
    "\0@SUM(1,2)",
    "  \tordinary text",
    "\tordinary text",
    '-12.50',
]);

it('preserves ordinary text cells', function (string $value) {
    expect(SpreadsheetSafeText::format($value))->toBe($value);
})->with(['', 'Groceries', '  Shop', 'Item = 2', '12.50', "O'Brien", "'already text"]);
