<?php
/**
 * Long Article 17 Languages Test Harness.
 *
 * Tests source leak detection and batch integrity validation
 * without requiring WordPress or database access.
 *
 * Usage: php tools/long-article-17-languages-test.php
 *
 * @package hollisho\translatepress\translate\deepseek
 */

// Bootstrap: load classes directly without WordPress.
$base_dir = dirname( __DIR__ );

// Minimal stubs for WordPress functions.
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof \WP_Error;
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct( $code = '', $message = '', $data = '' ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

// Register a simple PSR-4 autoloader for our namespace.
spl_autoload_register( function ( $class ) use ( $base_dir ) {
    $prefix = 'hollisho\\translatepress\\translate\\deepseek\\inc\\';
    $len    = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative = str_replace( '\\', '/', substr( $class, $len ) );
    $file     = $base_dir . '/inc/' . $relative . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

use hollisho\translatepress\translate\deepseek\inc\Translation\SourceLeakDetector;
use hollisho\translatepress\translate\deepseek\inc\Translation\BatchIntegrityValidator;

// ─── Configuration ───────────────────────────────────────────────────────────

$fixture_path = $base_dir . '/tests/fixtures/long-windows-password-3143-plus.txt';

// Active TranslatePress languages (from production).
$active_languages = [
    'en' => 'English',
    'pt_BR' => 'Portuguese (Brazil)',
    'es' => 'Spanish',
    'fr' => 'French',
    'de' => 'German',
    'it' => 'Italian',
    'ja' => 'Japanese',
    'ko' => 'Korean',
    'zh_CN' => 'Chinese (Simplified)',
    'ar' => 'Arabic',
    'ru' => 'Russian',
    'hi' => 'Hindi',
    'th' => 'Thai',
    'tr' => 'Turkish',
    'nl' => 'Dutch',
    'pl' => 'Polish',
    'uk' => 'Ukrainian',
];

$source_lang = 'es';

// ─── Results storage ─────────────────────────────────────────────────────────

$results = [
    'timestamp'    => gmdate( 'c' ),
    'fixture'      => [],
    'source_leak'  => [],
    'batch_integrity' => [],
    'summary'      => [],
];

// ─────────────────────────────────────────────────────────────────────────────
// Part A: Fixture Analysis
// ─────────────────────────────────────────────────────────────────────────────

echo "═══════════════════════════════════════════════════════════════\n";
echo "  Long Article 17 Languages Test Harness\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "[Part A] Fixture Analysis\n";
echo "───────────────────────────────────────────────────────────────\n";

if ( ! file_exists( $fixture_path ) ) {
    echo "  FAIL: Fixture file not found: {$fixture_path}\n";
    exit( 1 );
}

$fixture_content = file_get_contents( $fixture_path );
$char_count      = mb_strlen( $fixture_content, 'UTF-8' );
$line_count      = substr_count( $fixture_content, "\n" ) + 1;
$paragraph_count = preg_match_all( '/\n\s*\n/', $fixture_content ) + 1;
$heading_count   = preg_match_all( '/^#{1,6}\s+/m', $fixture_content );
$code_blocks     = preg_match_all( '/```|`[^`]+`/', $fixture_content );
$has_html        = (bool) preg_match( '/<[^>]+>/', $fixture_content );

// Split into translatable segments (paragraphs + headings).
$segments        = preg_split( '/\n\s*\n/', $fixture_content, -1, PREG_SPLIT_NO_EMPTY );
$segment_count   = count( $segments );

echo "  File:              {$fixture_path}\n";
echo "  Characters:        {$char_count}\n";
echo "  Lines:             {$line_count}\n";
echo "  Paragraphs:        {$paragraph_count}\n";
echo "  Segments:          {$segment_count}\n";
echo "  Headings:          {$heading_count}\n";
echo "  Has HTML:          " . ( $has_html ? 'yes' : 'no' ) . "\n";
echo "  Meets 3143+ req:   " . ( $char_count >= 3143 ? 'PASS' : 'FAIL' ) . "\n";

$results['fixture'] = [
    'path'            => $fixture_path,
    'char_count'      => $char_count,
    'line_count'      => $line_count,
    'paragraph_count' => $paragraph_count,
    'segment_count'   => $segment_count,
    'heading_count'   => $heading_count,
    'meets_requirement' => $char_count >= 3143,
];

// ─────────────────────────────────────────────────────────────────────────────
// Part B: Source Leak Detection Tests
// ─────────────────────────────────────────────────────────────────────────────

echo "\n[Part B] Source Leak Detection Tests\n";
echo "───────────────────────────────────────────────────────────────\n";

$detector = new SourceLeakDetector();

// Test 1: Korean with Spanish source leak.
echo "\n  Test 1: Korean target with Spanish source leak\n";
$source_text = "Recuperar la contraseña de Windows es un proceso sencillo si sigues los pasos correctos.";
$leaked_text = "비밀번호를 복구하는 것은 올바른 단계를 따르면 간단한 프로세스입니다. Sin embargo, algunos métodos requieren acceso físico a la computadora. Se recomienda crear un disco de restablecimiento.";
$result = $detector->detect_leak( $source_text, $leaked_text, 'es', 'ko' );
echo "    Leak detected: " . ( $result['leak_detected'] ? 'YES' : 'NO' ) . "\n";
echo "    Leak ratio:    " . round( $result['leak_ratio'] * 100, 1 ) . "%\n";
echo "    Details:       {$result['details']}\n";
echo "    Expected:      FAIL (leak detected) → " . ( $result['leak_detected'] ? 'PASS' : 'FAIL_BUG' ) . "\n";

$results['source_leak']['test_1_ko_leak'] = [
    'target' => 'ko',
    'leak_detected' => $result['leak_detected'],
    'leak_ratio' => $result['leak_ratio'],
    'status' => $result['leak_detected'] ? 'PASS' : 'FAIL_BUG',
];

// Test 2: Korean with proper translation.
echo "\n  Test 2: Korean target with proper translation\n";
$proper_ko = "비밀번호를 복구하는 것은 올바른 단계를 따르면 간단한 프로세스입니다. 그러나 일부 방법은 컴퓨터에 물리적인 접근이 필요합니다.";
$result2 = $detector->detect_leak( $source_text, $proper_ko, 'es', 'ko' );
echo "    Leak detected: " . ( $result2['leak_detected'] ? 'YES' : 'NO' ) . "\n";
echo "    Leak ratio:    " . round( $result2['leak_ratio'] * 100, 1 ) . "%\n";
echo "    Details:       {$result2['details']}\n";
echo "    Expected:      PASS (no leak) → " . ( ! $result2['leak_detected'] ? 'PASS' : 'FAIL_BUG' ) . "\n";

$results['source_leak']['test_2_ko_proper'] = [
    'target' => 'ko',
    'leak_detected' => $result2['leak_detected'],
    'leak_ratio' => $result2['leak_ratio'],
    'status' => ! $result2['leak_detected'] ? 'PASS' : 'WARN',
];

// Test 3: Thai with Spanish source leak.
echo "\n  Test 3: Thai target with Spanish source leak\n";
$thai_leaked = "การกู้คืนรหัสผ่านของ Windows เป็นขั้นตอนที่ง่าย Sin embargo, algunos métodos requieren acceso físico.";
$result3 = $detector->detect_leak( $source_text, $thai_leaked, 'es', 'th' );
echo "    Leak detected: " . ( $result3['leak_detected'] ? 'YES' : 'NO' ) . "\n";
echo "    Leak ratio:    " . round( $result3['leak_ratio'] * 100, 1 ) . "%\n";
echo "    Details:       {$result3['details']}\n";
echo "    Expected:      FAIL (leak detected) → " . ( $result3['leak_detected'] ? 'PASS' : 'FAIL_BUG' ) . "\n";

$results['source_leak']['test_3_th_leak'] = [
    'target' => 'th',
    'leak_detected' => $result3['leak_detected'],
    'leak_ratio' => $result3['leak_ratio'],
    'status' => $result3['leak_detected'] ? 'PASS' : 'FAIL_BUG',
];

// Test 4: Hindi with proper translation.
echo "\n  Test 4: Hindi target with proper translation\n";
$proper_hi = "विंडोज पासवर्ड को पुनर्प्राप्त करना एक सरल प्रक्रिया है। हालांकि, कुछ तरीकों के लिए कंप्यूटर तक भौतिक पहुंच की आवश्यकता होती है।";
$result4 = $detector->detect_leak( $source_text, $proper_hi, 'es', 'hi' );
echo "    Leak detected: " . ( $result4['leak_detected'] ? 'YES' : 'NO' ) . "\n";
echo "    Leak ratio:    " . round( $result4['leak_ratio'] * 100, 1 ) . "%\n";
echo "    Details:       {$result4['details']}\n";
echo "    Expected:      PASS (no leak) → " . ( ! $result4['leak_detected'] ? 'PASS' : 'WARN' ) . "\n";

$results['source_leak']['test_4_hi_proper'] = [
    'target' => 'hi',
    'leak_detected' => $result4['leak_detected'],
    'leak_ratio' => $result4['leak_ratio'],
    'status' => ! $result4['leak_detected'] ? 'PASS' : 'WARN',
];

// Test 5: Japanese with source leak.
echo "\n  Test 5: Japanese target with source leak\n";
$ja_leaked = "Windowsのパスワードを回復するのは簡単なプロセスです。Sin embargo, algunos métodos requieren acceso físico.";
$result5 = $detector->detect_leak( $source_text, $ja_leaked, 'es', 'ja' );
echo "    Leak detected: " . ( $result5['leak_detected'] ? 'YES' : 'NO' ) . "\n";
echo "    Leak ratio:    " . round( $result5['leak_ratio'] * 100, 1 ) . "%\n";
echo "    Details:       {$result5['details']}\n";
echo "    Expected:      FAIL (leak detected) → " . ( $result5['leak_detected'] ? 'PASS' : 'FAIL_BUG' ) . "\n";

$results['source_leak']['test_5_ja_leak'] = [
    'target' => 'ja',
    'leak_detected' => $result5['leak_detected'],
    'leak_ratio' => $result5['leak_ratio'],
    'status' => $result5['leak_detected'] ? 'PASS' : 'FAIL_BUG',
];

// Test 6: Arabic with source leak.
echo "\n  Test 6: Arabic target with source leak\n";
$ar_leaked = "استعادة كلمة مرور Windows هي عملية بسيطة. Sin embargo, algunos métodos requieren acceso físico.";
$result6 = $detector->detect_leak( $source_text, $ar_leaked, 'es', 'ar' );
echo "    Leak detected: " . ( $result6['leak_detected'] ? 'YES' : 'NO' ) . "\n";
echo "    Leak ratio:    " . round( $result6['leak_ratio'] * 100, 1 ) . "%\n";
echo "    Details:       {$result6['details']}\n";
echo "    Expected:      FAIL (leak detected) → " . ( $result6['leak_detected'] ? 'PASS' : 'FAIL_BUG' ) . "\n";

$results['source_leak']['test_6_ar_leak'] = [
    'target' => 'ar',
    'leak_detected' => $result6['leak_detected'],
    'leak_ratio' => $result6['leak_ratio'],
    'status' => $result6['leak_detected'] ? 'PASS' : 'FAIL_BUG',
];

// Test 7: Batch leak detection.
echo "\n  Test 7: Batch leak detection (mixed translations)\n";
$batch_input = [
    'title'   => 'Cómo recuperar la contraseña de Windows',
    'intro'   => 'Esta guía cubre los métodos más efectivos para recuperar la contraseña.',
    'method1' => 'Usa una cuenta de Microsoft para restablecer la contraseña en línea.',
    'method2' => 'Utiliza un disco de restablecimiento de contraseña creado previamente.',
    'method3' => 'Inicia sesión con otra cuenta de administrador para cambiar la contraseña.',
    'warning' => 'Algunos métodos requieren acceso físico a la computadora.',
    'conclusion' => 'Toma medidas de seguridad para evitar perder el acceso en el futuro.',
];

// Simulate: title translated, intro partially leaked, methods 1-3 properly translated, warning fully leaked, conclusion OK.
$batch_output = [
    'title'   => 'Windows 비밀번호를 복구하는 방법',
    'intro'   => '이 가이드는 비밀번호를 복구하는 가장 효과적인 방법을 다룹니다. Esta guía cubre los métodos más efectivos.',
    'method1' => 'Microsoft 계정을 사용하여 온라인으로 비밀번호를 재설정하세요.',
    'method2' => '사전에 생성한 비밀번호 재설정 디스크를 사용하세요.',
    'method3' => '다른 관리자 계정으로 로그인하여 비밀번호를 변경하세요.',
    'warning' => 'Algunos métodos requieren acceso físico a la computadora.',
    'conclusion' => '향후 접근 권한을 잃지 않도록 보안 조치를 취하세요.',
];

$batch_result = $detector->detect_batch_leak( $batch_input, $batch_output, 'es', 'ko' );
echo "    Leak detected:   " . ( $batch_result['leak_detected'] ? 'YES' : 'NO' ) . "\n";
echo "    Leak ratio:      " . round( $batch_result['leak_ratio'] * 100, 1 ) . "%\n";
echo "    Leak percentage: " . round( $batch_result['leak_percentage'] * 100, 1 ) . "%\n";
echo "    Leaking items:   " . count( $batch_result['leaking_keys'] ) . "/" . count( $batch_output ) . "\n";
echo "    Details:         {$batch_result['details']}\n";
echo "    Expected:        FAIL (batch leak) → " . ( $batch_result['leak_detected'] ? 'PASS' : 'FAIL_BUG' ) . "\n";

$results['source_leak']['test_7_batch_leak'] = [
    'target' => 'ko',
    'leak_detected' => $batch_result['leak_detected'],
    'leak_ratio' => $batch_result['leak_ratio'],
    'leak_percentage' => $batch_result['leak_percentage'],
    'leaking_count' => count( $batch_result['leaking_keys'] ),
    'total_count' => count( $batch_output ),
    'status' => $batch_result['leak_detected'] ? 'PASS' : 'FAIL_BUG',
];

// ─────────────────────────────────────────────────────────────────────────────
// Part C: Batch Integrity Validator Tests
// ─────────────────────────────────────────────────────────────────────────────

echo "\n[Part C] Batch Integrity Validator Tests\n";
echo "───────────────────────────────────────────────────────────────\n";

$validator = new BatchIntegrityValidator();

// Test 8: validate_source_leak with semi-translated batch.
echo "\n  Test 8: validate_source_leak — semi-translated Korean batch\n";
$integrity_result = $validator->validate_source_leak( $batch_input, $batch_output, 'es', 'ko' );
if ( is_wp_error( $integrity_result ) ) {
    echo "    Result: FAIL (blocked) — Code: {$integrity_result->get_error_code()}\n";
    echo "    Message: " . substr( $integrity_result->get_error_message(), 0, 120 ) . "...\n";
    echo "    Expected: PASS (leak blocked) → PASS\n";
    $results['batch_integrity']['test_8_source_leak'] = [
        'status' => 'PASS',
        'error_code' => $integrity_result->get_error_code(),
    ];
} else {
    echo "    Result: PASS (no leak detected) — WARNING: leak should have been detected!\n";
    $results['batch_integrity']['test_8_source_leak'] = [
        'status' => 'FAIL_BUG',
        'error_code' => 'none',
    ];
}

// Test 9: validate_no_near_identical_long.
echo "\n  Test 9: validate_no_near_identical_long\n";
$near_identical_input = [
    'long_text_1' => str_repeat( 'Este es un texto largo que debería ser traducido correctamente a otro idioma. ', 5 ),
];
$near_identical_output = [
    'long_text_1' => str_repeat( 'Este es un texto largo que debería ser traducido correctamente a otro idioma. ', 5 ),
];
$near_result = $validator->validate_no_near_identical_long( $near_identical_input, $near_identical_output );
if ( is_wp_error( $near_result ) ) {
    echo "    Result: FAIL (blocked) — Code: {$near_result->get_error_code()}\n";
    echo "    Expected: PASS (near-identical blocked) → PASS\n";
    $results['batch_integrity']['test_9_near_identical'] = [
        'status' => 'PASS',
        'error_code' => $near_result->get_error_code(),
    ];
} else {
    echo "    Result: PASS (no issue) — FAIL: near-identical should have been detected!\n";
    $results['batch_integrity']['test_9_near_identical'] = [
        'status' => 'FAIL_BUG',
        'error_code' => 'none',
    ];
}

// Test 10: validate_no_mixed_language for Korean.
echo "\n  Test 10: validate_no_mixed_language — Korean with Latin leaks\n";
$mixed_output = [
    'title' => 'Windows 비밀번호를 복구하는 방법',
    'body'  => 'Esta es una guía completa sobre cómo recuperar la contraseña de Windows. Se cubren varios métodos.',
];
$mixed_result = $validator->validate_no_mixed_language( $mixed_output, 'ko' );
if ( is_wp_error( $mixed_result ) ) {
    echo "    Result: FAIL (blocked) — Code: {$mixed_result->get_error_code()}\n";
    echo "    Expected: PASS (mixed language blocked) → PASS\n";
    $results['batch_integrity']['test_10_mixed_language'] = [
        'status' => 'PASS',
        'error_code' => $mixed_result->get_error_code(),
    ];
} else {
    echo "    Result: PASS (no issue) — FAIL: mixed language should have been detected!\n";
    $results['batch_integrity']['test_10_mixed_language'] = [
        'status' => 'FAIL_BUG',
        'error_code' => 'none',
    ];
}

// Test 11: validate_no_mixed_language for Korean (proper translation).
echo "\n  Test 11: validate_no_mixed_language — Korean proper translation\n";
$proper_output = [
    'title' => 'Windows 비밀번호를 복구하는 방법',
    'body'  => '이것은 Windows 비밀번호를 복구하는 방법에 대한 종합 가이드입니다. 여러 가지 방법이 다루어집니다.',
];
$mixed_result2 = $validator->validate_no_mixed_language( $proper_output, 'ko' );
if ( is_wp_error( $mixed_result2 ) ) {
    echo "    Result: FAIL (blocked) — Code: {$mixed_result2->get_error_code()}\n";
    echo "    Expected: PASS (no mixed language) → FAIL_BUG\n";
    $results['batch_integrity']['test_11_mixed_proper'] = [
        'status' => 'FAIL_BUG',
        'error_code' => $mixed_result2->get_error_code(),
    ];
} else {
    echo "    Result: PASS (no issue) — Expected: PASS → PASS\n";
    $results['batch_integrity']['test_11_mixed_proper'] = [
        'status' => 'PASS',
        'error_code' => 'none',
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Part D: 17 Languages Simulation
// ─────────────────────────────────────────────────────────────────────────────

echo "\n[Part D] 17 Languages Source Leak Simulation\n";
echo "───────────────────────────────────────────────────────────────\n";

$long_sample = "Cómo recuperar la contraseña de Windows: guía completa. Perder el acceso a tu computadora con Windows puede ser una experiencia estresante. Esta guía cubre los métodos más efectivos para recuperar la contraseña de Windows 10 y Windows 11.";
$long_sample_ko_proper = "Windows 비밀번호를 복구하는 방법: 종합 가이드. Windows 컴퓨터에 접근하는 것을 잃으면 스트레스가 될 수 있습니다. 이 가이드에서는 Windows 10 및 Windows 11 비밀번호를 복구하는 가장 효과적인 방법을 다룹니다.";
$long_sample_ko_leaked = "Windows 비밀번호를 복구하는 방법: 종합 가이드. Perder el acceso a tu computadora con Windows puede ser una experiencia estresante. Esta guía cubre los métodos más efectivos para recuperar la contraseña de Windows 10 y Windows 11.";

$lang_results = [];
foreach ( $active_languages as $code => $name ) {
    if ( $code === $source_lang ) {
        continue;
    }

    // Test with leaked content.
    $leak_result = $detector->detect_leak( $long_sample, $long_sample_ko_leaked, $source_lang, $code );

    // Test with proper content (using Korean as proxy).
    $proper_result = $detector->detect_leak( $long_sample, $long_sample_ko_proper, $source_lang, $code );

    $lang_results[ $code ] = [
        'name'           => $name,
        'leak_detected'  => $leak_result['leak_detected'],
        'leak_ratio'     => $leak_result['leak_ratio'],
        'proper_ok'      => ! $proper_result['leak_detected'],
        'status'         => $leak_result['leak_detected'] && ! $proper_result['leak_detected'] ? 'PASS' : 'WARN',
    ];

    $status_icon = $lang_results[ $code ]['status'] === 'PASS' ? '✓' : '?';
    echo "  {$status_icon} {$code} ({$name}): leak detected=" . ( $leak_result['leak_detected'] ? 'YES' : 'NO' ) . ", proper OK=" . ( ! $proper_result['leak_detected'] ? 'YES' : 'NO' ) . "\n";
}

$results['source_leak']['lang_simulation'] = $lang_results;

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  Summary\n";
echo "═══════════════════════════════════════════════════════════════\n";

$total_tests = 0;
$passed      = 0;
$failed      = 0;
$warns       = 0;

foreach ( $results['source_leak'] as $test => $data ) {
    if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
        continue;
    }
    $total_tests++;
    if ( $data['status'] === 'PASS' ) {
        $passed++;
    } elseif ( $data['status'] === 'FAIL_BUG' ) {
        $failed++;
        echo "  FAIL: {$test}\n";
    } else {
        $warns++;
    }
}

foreach ( $results['batch_integrity'] as $test => $data ) {
    if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
        continue;
    }
    $total_tests++;
    if ( $data['status'] === 'PASS' ) {
        $passed++;
    } elseif ( $data['status'] === 'FAIL_BUG' ) {
        $failed++;
        echo "  FAIL: {$test}\n";
    } else {
        $warns++;
    }
}

echo "\n  Total tests: {$total_tests}\n";
echo "  Passed:      {$passed}\n";
echo "  Failed:      {$failed}\n";
echo "  Warns:       {$warns}\n";

if ( $failed > 0 ) {
    echo "\n  OVERALL: FAIL_BUG — Source leak not detected in some cases.\n";
    $overall = 'FAIL_BUG';
} elseif ( $warns > 0 ) {
    echo "\n  OVERALL: WARN — Some edge cases need attention.\n";
    $overall = 'WARN';
} else {
    echo "\n  OVERALL: PASS — All source leak detection tests passed.\n";
    $overall = 'PASS';
}

$results['summary'] = [
    'total'   => $total_tests,
    'passed'  => $passed,
    'failed'  => $failed,
    'warns'   => $warns,
    'overall' => $overall,
];

echo "\n═══════════════════════════════════════════════════════════════\n";

// Save results as JSON for reporting.
$report_file = dirname( __DIR__ ) . '/tools/last-harness-results.json';
file_put_contents( $report_file, json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo "\nResults saved to: {$report_file}\n";

exit( $failed > 0 ? 1 : 0 );
