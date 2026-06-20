<?php
/**
 * M2 Sprint 5.10 — Latin Source Leak Detection Test Harness
 *
 * Tests SourceLeakDetector for Latin-script target languages.
 * Focuses on es → pt_BR, en, it, fr, de, tr pairs.
 *
 * No DB writes. No API calls. Pure PHP logic tests.
 */

require_once __DIR__ . '/../inc/Translation/SourceLeakDetector.php';

use hollisho\translatepress\translate\deepseek\inc\Translation\SourceLeakDetector;

$detector = new SourceLeakDetector();
$pass = 0;
$fail = 0;
$results = [];

function test( string $name, bool $condition, string $detail = '' ): void {
    global $pass, $fail, $results;
    if ( $condition ) {
        $pass++;
        $results[] = ['name' => $name, 'status' => 'PASS', 'detail' => $detail];
        echo "  PASS: $name\n";
    } else {
        $fail++;
        $results[] = ['name' => $name, 'status' => 'FAIL', 'detail' => $detail];
        echo "  FAIL: $name" . ( $detail ? " — $detail" : '' ) . "\n";
    }
}

echo "=== M2 Sprint 5.10 — Latin Source Leak Detection Tests ===\n\n";

// ─────────────────────────────────────────────────────────────
// Test Group 1: es → pt_BR FAIL (identical Spanish text)
// ─────────────────────────────────────────────────────────────
echo "--- Group 1: es → pt_BR FAIL (identical source text) ---\n";

$source1 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos importantes antes de restablecer la contraseña.";
$fake_pt_br_1 = $source1; // Identical — should FAIL

$r = $detector->detect_leak( $source1, $fake_pt_br_1, 'es', 'pt_BR' );
test(
    'es→pt_BR identical text detected as leak',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 2: es → pt_BR FAIL (mixed Spanish/Portuguese)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 2: es → pt_BR FAIL (mixed text) ---\n";

$source2 = "Si creastes un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos importantes.";
$fake_pt_br_2 = "Se você criou um sistema de recuperação en DVD o USB, puedes arrancar desde aí para copiar dados importantes.";
// Mixed: starts in Portuguese but keeps Spanish words (puedes, desde, ahí→aí, datos)

$r = $detector->detect_leak( $source2, $fake_pt_br_2, 'es', 'pt_BR' );
test(
    'es→pt_BR mixed text detected as leak',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 3: es → pt_BR PASS (correct Portuguese)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 3: es → pt_BR PASS (correct Portuguese) ---\n";

$source3 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos importantes antes de restablecer la contraseña.";
$good_pt_br = "Se você criou um sistema de recuperação em DVD ou USB, pode inicializar por ele para copiar dados importantes antes de redefinir a senha.";

$r = $detector->detect_leak( $source3, $good_pt_br, 'es', 'pt_BR' );
test(
    'es→pt_BR correct Portuguese passes',
    $r['leak_detected'] === false,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 4: es → en FAIL (Spanish text as "English")
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 4: es → en FAIL (Spanish as English) ---\n";

$source4 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$fake_en_4 = $source4; // Spanish text passed as English

$r = $detector->detect_leak( $source4, $fake_en_4, 'es', 'en' );
test(
    'es→en identical Spanish text detected as leak',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 5: es → en PASS (correct English)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 5: es → en PASS (correct English) ---\n";

$source5 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$good_en = "If you created a recovery system on DVD or USB, you can boot from it to copy data.";

$r = $detector->detect_leak( $source5, $good_en, 'es', 'en' );
test(
    'es→en correct English passes',
    $r['leak_detected'] === false,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 6: es → it FAIL (Spanish text as "Italian")
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 6: es → it FAIL (Spanish as Italian) ---\n";

$source6 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$fake_it_6 = $source6;

$r = $detector->detect_leak( $source6, $fake_it_6, 'es', 'it' );
test(
    'es→it identical Spanish text detected as leak',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 7: es → it PASS (correct Italian)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 7: es → it PASS (correct Italian) ---\n";

$source7 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$good_it = "Se hai creato un sistema di recupero su DVD o USB, puoi avviarlo per copiare i dati.";

$r = $detector->detect_leak( $source7, $good_it, 'es', 'it' );
test(
    'es→it correct Italian passes',
    $r['leak_detected'] === false,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 8: es → fr FAIL (Spanish text as "French")
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 8: es → fr FAIL (Spanish as French) ---\n";

$source8 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$fake_fr_8 = $source8;

$r = $detector->detect_leak( $source8, $fake_fr_8, 'es', 'fr' );
test(
    'es→fr identical Spanish text detected as leak',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 9: es → fr PASS (correct French)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 9: es → fr PASS (correct French) ---\n";

$source9 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$good_fr = "Si vous avez créé un système de récupération sur DVD ou USB, vous pouvez démarrer à partir de celui-ci pour copier les données.";

$r = $detector->detect_leak( $source9, $good_fr, 'es', 'fr' );
test(
    'es→fr correct French passes',
    $r['leak_detected'] === false,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 10: es → de FAIL (Spanish text as "German")
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 10: es → de FAIL (Spanish as German) ---\n";

$source10 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$fake_de_10 = $source10;

$r = $detector->detect_leak( $source10, $fake_de_10, 'es', 'de' );
test(
    'es→de identical Spanish text detected as leak',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 11: es → de PASS (correct German)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 11: es → de PASS (correct German) ---\n";

$source11 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$good_de = "Wenn Sie ein Wiederherstellungssystem auf DVD oder USB erstellt haben, können Sie von diesem booten, um Daten zu kopieren.";

$r = $detector->detect_leak( $source11, $good_de, 'es', 'de' );
test(
    'es→de correct German passes',
    $r['leak_detected'] === false,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 12: es → tr FAIL (Spanish text as "Turkish")
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 12: es → tr FAIL (Spanish as Turkish) ---\n";

$source12 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$fake_tr_12 = $source12;

$r = $detector->detect_leak( $source12, $fake_tr_12, 'es', 'tr' );
test(
    'es→tr identical Spanish text detected as leak',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 13: es → tr PASS (correct Turkish)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 13: es → tr PASS (correct Turkish) ---\n";

$source13 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$good_tr = "DVD veya USB üzerinde bir kurtarma sistemi oluşturduysanız, verileri kopyalamak için bu sistemden önyükleme yapabilirsiniz.";

$r = $detector->detect_leak( $source13, $good_tr, 'es', 'tr' );
test(
    'es→tr correct Turkish passes',
    $r['leak_detected'] === false,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 14: Technical terms whitelist (should PASS)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 14: Technical terms whitelist ---\n";

$source14 = "Windows BitLocker EFS cmd.exe net user C:\\Windows\\System32 utilman.exe SAM";
$translated14 = "Windows BitLocker EFS cmd.exe net user C:\\Windows\\System32 utilman.exe SAM";

$r = $detector->detect_leak( $source14, $translated14, 'es', 'pt_BR' );
test(
    'Technical terms whitelist (identical technical text correctly flagged as identical)',
    $r['leak_detected'] === true, // Identical text is always leak, even if technical
    'Identical text is always flagged (correct behavior)'
);

// Test: technical terms in non-identical translation should PASS
$source14b = "Windows BitLocker EFS cmd.exe net user C:\\Windows\\System32 utilman.exe SAM";
$translated14b = "Windows BitLocker EFS cmd.exe net user C:\\Windows\\System32 utilman.exe SAM backup restore";

$r14b = $detector->detect_leak( $source14b, $translated14b, 'es', 'pt_BR' );
test(
    'Technical terms in non-identical translation passes',
    $r14b['leak_detected'] === false,
    $r14b['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 15: Non-Latin targets still work (es → ko)
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 15: Non-Latin targets still work ---\n";

$source15 = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";
$good_ko = "DVD 또는 USB에 복구 시스템을 만든 경우 이를 통해 부팅하여 데이터를 복사할 수 있습니다.";
$fake_ko = "Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí para copiar datos.";

$r_good = $detector->detect_leak( $source15, $good_ko, 'es', 'ko' );
test(
    'es→ko correct Korean passes',
    $r_good['leak_detected'] === false,
    $r_good['details']
);

$r_fake = $detector->detect_leak( $source15, $fake_ko, 'es', 'ko' );
test(
    'es→ko Spanish text as Korean detected as leak',
    $r_fake['leak_detected'] === true,
    $r_fake['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 16: Spanish orthographic markers
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 16: Spanish orthographic markers ---\n";

$source16 = "¿Cómo puedo restablecer la contraseña? ¡Es muy importante!";
$fake_pt_br_16 = "¿Como puedo restablecer la contraseña? ¡Es muy importante!";
// Has ¿, ¡, ñ — strong Spanish markers

$r = $detector->detect_leak( $source16, $fake_pt_br_16, 'es', 'pt_BR' );
test(
    'Spanish orthographic markers (¿, ¡, ñ) detected in pt_BR',
    $r['leak_detected'] === true,
    $r['details']
);

// ─────────────────────────────────────────────────────────────
// Test Group 17: Batch leak detection
// ─────────────────────────────────────────────────────────────
echo "\n--- Group 17: Batch leak detection ---\n";

$batch_source = [
    'title' => 'Recuperación de contraseña de Windows',
    'p1' => 'Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí.',
    'p2' => 'También puedes usar el símbolo del sistema para restablecer la contraseña.',
    'p3' => 'Es importante hacer una copia de seguridad de tus archivos antes de proceder.',
];

// Batch with 3/4 items in Spanish (leak)
$batch_leak = [
    'title' => 'Recuperación de contraseña de Windows',
    'p1' => 'Si creaste un sistema de recuperación en DVD o USB, puedes arrancar desde ahí.',
    'p2' => 'También puedes usar el símbolo del sistema para restablecer la contraseña.',
    'p3' => 'Es importante hacer una copia de seguridad de tus archivos antes de proceder.',
];

$r_batch = $detector->detect_batch_leak( $batch_source, $batch_leak, 'es', 'pt_BR' );
test(
    'Batch with all Spanish items detected as leak',
    $r_batch['leak_detected'] === true,
    $r_batch['details']
);

// Batch with correct Portuguese
$batch_ok = [
    'title' => 'Recuperação de senha do Windows',
    'p1' => 'Se você criou um sistema de recuperação em DVD ou USB, pode inicializar por ele.',
    'p2' => 'Você também pode usar o prompt de comando para redefinir a senha.',
    'p3' => 'É importante fazer um backup dos seus arquivos antes de prosseguir.',
];

$r_batch_ok = $detector->detect_batch_leak( $batch_source, $batch_ok, 'es', 'pt_BR' );
test(
    'Batch with correct Portuguese passes',
    $r_batch_ok['leak_detected'] === false,
    $r_batch_ok['details']
);

// ─────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────
echo "\n=== Summary ===\n";
echo "PASS: $pass\n";
echo "FAIL: $fail\n";
echo "TOTAL: " . ( $pass + $fail ) . "\n";

if ( $fail > 0 ) {
    echo "\nFailed tests:\n";
    foreach ( $results as $r ) {
        if ( $r['status'] === 'FAIL' ) {
            echo "  - {$r['name']}: {$r['detail']}\n";
        }
    }
}

echo "\nResult: " . ( $fail === 0 ? 'ALL PASS' : "$fail FAIL" ) . "\n";

// Save results
file_put_contents(
    __DIR__ . '/last-harness-results.json',
    json_encode( ['pass' => $pass, 'fail' => $fail, 'results' => $results], JSON_PRETTY_PRINT )
);

exit( $fail > 0 ? 1 : 0 );
