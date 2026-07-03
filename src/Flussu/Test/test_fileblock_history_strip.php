<?php
/* --------------------------------------------------------------------*
 * Test FlussuFileBlock::stripForHistory() — il blocco flussu-file non
 * deve mai essere persistito nella chat history (costo token + i modelli
 * piccoli lo ricopiano invece di rispondere): al suo posto un placeholder.
 * --------------------------------------------------------------------*
 * CREATED DATE:     03.07.2026 - Flussu v5.0
 * --------------------------------------------------------------------*/

require_once __DIR__ . '/../../../vendor/autoload.php';

use Flussu\Helpers\FlussuFileBlock;

$pass = 0; $fail = 0;
function check(string $name, bool $cond): void {
    global $pass, $fail;
    if ($cond) { echo "[OK] $name\n"; $pass++; }
    else       { echo "[FAIL] $name\n"; $fail++; }
}

// 1) blocco valido con filename: prosa conservata, blocco sostituito da placeholder
$body = "Titolo\n\nContenuto del report con ```php\ncodice\n``` dentro.";
$reply1 = "Ecco il documento Word.\n````flussu-file format=docx filename=report.docx\n$body\n````";
$h1 = FlussuFileBlock::stripForHistory($reply1);
check("prose kept",              strpos($h1, "Ecco il documento Word.") !== false);
check("block body removed",      strpos($h1, "Contenuto del report") === false);
check("no fence in history",     strpos($h1, "flussu-file") === false);
check("placeholder w/ filename", strpos($h1, "report.docx") !== false);

// 2) reply senza blocco: invariata
$reply2 = "Risposta normale, nessun file.";
check("no-block untouched", FlussuFileBlock::stripForHistory($reply2) === $reply2);

// 3) blocco senza filename: placeholder col formato (default docx se assente)
$reply3 = "Here it is.\n````flussu-file format=csv\na;b\n1;2\n````";
$h3 = FlussuFileBlock::stripForHistory($reply3);
check("no fence (csv case)",    strpos($h3, "flussu-file") === false);
check("placeholder w/ format",  strpos($h3, "file.csv") !== false);

// 4) blocco invalido (body vuoto): comunque strippato, placeholder generico
$reply4 = "Ecco.\n````flussu-file format=docx\n\n````";
$h4 = FlussuFileBlock::stripForHistory($reply4);
check("invalid block stripped", strpos($h4, "flussu-file") === false);
check("generic placeholder",    stripos($h4, "omitted from history") !== false);

echo "\n=== RESULT: $pass passed, $fail failed ===\n";
exit($fail > 0 ? 1 : 0);
