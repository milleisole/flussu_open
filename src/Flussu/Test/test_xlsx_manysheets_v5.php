<?php
// v5.x — regression test for the MAX_SPREADSHEET_SHEETS cap.
// A real 54-sheet financial-plan workbook ("FP su 5 anni Canonity.xlsx")
// was silently truncated to 30 sheets, dropping the detail tables the LLM
// needed. Full extraction (~127K tok) fits the 200K-token budget, so the
// per-sheet cap was the sole cause. This generates a 40-sheet workbook and
// asserts every sheet survives extraction.
require_once __DIR__ . '/../../../vendor/autoload.php';
use Flussu\Documents\DocumentSpace;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;

$N = 40; // > old cap (30), < new cap (128)
$tmp = tempnam(sys_get_temp_dir(), 'xlms') . '.xlsx';
$w = new Writer();
$w->openToFile($tmp);
for ($i = 1; $i <= $N; $i++) {
    $s = ($i === 1) ? $w->getCurrentSheet() : $w->addNewSheetAndMakeItCurrent();
    $s->setName('Foglio_' . $i);
    $w->addRow(Row::fromValues(['Voce', 'Valore']));
    $w->addRow(Row::fromValues(['marker' . $i, $i * 10]));
}
$w->close();

$ds  = new DocumentSpace('test-xlms-' . getmypid());
$ref = new ReflectionMethod(DocumentSpace::class, '_processSpreadsheet');
$ref->setAccessible(true);
$out = $ref->invoke($ds, $tmp);

$fail = 0;
function chk($l, $c) { global $fail; echo ($c ? "  PASS  " : "  FAIL  ") . $l . "\n"; if (!$c) $fail++; }

$seen = substr_count($out, '=== FOGLIO:');
chk("tutti i $N fogli estratti (visti=$seen)", $seen === $N);
chk("primo foglio presente",      str_contains($out, '=== FOGLIO: Foglio_1 '));
chk("foglio 31 presente (oltre vecchio cap 30)", str_contains($out, '=== FOGLIO: Foglio_31 '));
chk("ultimo foglio presente",     str_contains($out, '=== FOGLIO: Foglio_' . $N . ' '));
chk("dato ultimo foglio presente", str_contains($out, 'marker' . $N . ',' . ($N * 10)));
chk("nessun marker di troncamento workbook", !str_contains($out, 'workbook troncato'));

DocumentSpace::cleanup('test-xlms-' . getmypid());
@unlink($tmp);
echo "\n" . ($fail === 0 ? "ALL MANY-SHEETS TESTS PASSED" : "$fail TEST(S) FAILED") . "\n";
exit($fail === 0 ? 0 : 1);
