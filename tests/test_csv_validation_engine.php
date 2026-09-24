<?php

declare(strict_types=1);

/**
 * Focused Test Suite: Certificate CSV Validation & Ingestion Engine
 *
 * Covers:
 * 1. Valid CSV (correct headers, multiple rows, UTF-8, valid phone, BOM stripping)
 * 2. Invalid CSV (missing required column, unsupported column, malformed, invalid row, empty required value, invalid phone, duplicate rows)
 * 3. Response Contract (structured fields, line/row parity, errors array, no undefined .map)
 * 4. Phone Invariant (phone is NOT a design variable, NOT rendered, remains for ingestion/search)
 * 5. Template Variable Invariant (Sample CSV + validation matches selected template)
 * 6. Exact Uploaded CSV Verification (75 rows from sample_certificate_data_suicide_gatekeeper_training (3).csv)
 *
 * Run via: php tests/test_csv_validation_engine.php
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\Exceptions\ValidationException;
use App\Services\CsvValidationService;
use App\Services\VariableRegistry;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertEngine(string $description, bool $condition, string $details = ''): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}\n";
        if ($details) {
            echo "         Details: {$details}\n";
        }
    }
}

echo "=== LC-SPC Certificate CSV Validation Engine Test Suite ===\n\n";

$template = [
    'id'                 => 42,
    'name'               => 'Gatekeeper Training (Level 1)',
    'certificate_type'   => 'Certificate of Participation',
    'required_variables' => ['name', 'date', 'event_title', 'organization', 'duration', 'place'],
    'layout_config'      => [
        'elements' => [
            ['type' => 'dynamic_text', 'text' => 'Recipient: {{name}}'],
            ['type' => 'dynamic_text', 'text' => 'Event: {{event_title}} on {{date}} at {{place}}'],
            ['type' => 'dynamic_text', 'text' => 'Org: {{organization}}, Duration: {{duration}}'],
            ['type' => 'qr_code', 'url_template' => '{{verification_url}}'],
        ]
    ]
];

// =============================================================================
// SECTION 1: Valid CSV Scenarios
// =============================================================================
echo "--- 1. Testing Valid CSV Processing ---\n";

// 1.1 Correct headers, multiple rows, UTF-8 names, valid phone
$validCsv = "name,phone,date,event_title,organization,duration,place,email\n"
    . "Arjun Menon,9876543210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,\"MES College, Kunnukara\",arjun@example.com\n"
    . "ലക്ഷ്മി നായർ,9876543211,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,\"MES College, Kunnukara\",lakshmi@example.com\n"
    . "Fathima Noor,+91 98765 43212,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,\"MES College, Kunnukara\",\n";

$res1 = CsvValidationService::validate($validCsv, $template);
assertEngine('Valid CSV with 3 rows processes successfully', $res1['valid_count'] === 3 && $res1['invalid_count'] === 0);
assertEngine('UTF-8 Malayalam character name preserved without corruption', $res1['valid_records'][1]['name'] === 'ലക്ഷ്മി നായർ');
assertEngine('Phone numbers normalized to E.164 (+91)', $res1['valid_records'][0]['phone_normalized'] === '+919876543210');
assertEngine('Phone with spaces and +91 normalized cleanly', $res1['valid_records'][2]['phone_normalized'] === '+919876543212');

// 1.2 UTF-8 BOM Stripping
$bomCsv = "\xEF\xBB\xBF" . $validCsv;
$resBom = CsvValidationService::validate($bomCsv, $template);
assertEngine('UTF-8 BOM is stripped cleanly without affecting first header', $resBom['valid_count'] === 3 && $resBom['headers'][0] === 'name');

// 1.3 CSV without phone column (phone is optional in CSV)
$noPhoneTemplate = [
    'id'                 => 43,
    'name'               => 'Standard Certificate',
    'certificate_type'   => 'Participation',
    'required_variables' => ['name', 'date', 'place'],
];
$noPhoneCsv = "name,date,place\n"
    . "Rahul V,15 September 2026,Kozhikode\n"
    . "Anjali S,15 September 2026,Kozhikode\n";
$resNoPhone = CsvValidationService::validate($noPhoneCsv, $noPhoneTemplate);
assertEngine('CSV without phone column is accepted when template does not require it', $resNoPhone['valid_count'] === 2);
assertEngine('Record without phone column defaults to empty strings', $resNoPhone['valid_records'][0]['phone'] === '' && $resNoPhone['valid_records'][0]['phone_normalized'] === '');

// =============================================================================
// SECTION 2: Invalid CSV Scenarios
// =============================================================================
echo "\n--- 2. Testing Invalid CSV & Column Rejections ---\n";

// 2.1 Missing required template column
$missingColCsv = "name,phone,organization,duration,place\n"
    . "Test User,9876543210,Listening Community SPC,2 Hours,Kozhikode\n";
$caughtMissing = false;
$missingMsg = '';
try {
    CsvValidationService::validate($missingColCsv, $template);
} catch (ValidationException $e) {
    $caughtMissing = true;
    $missingMsg = $e->getMessage();
}
assertEngine('Missing required template columns strictly throws ValidationException', $caughtMissing);
assertEngine('Error message explicitly identifies missing columns [date, event_title]', str_contains($missingMsg, 'date') && str_contains($missingMsg, 'event_title'));

// 2.2 Missing mandatory 'name' column
$noNameCsv = "phone,date,event_title,organization,duration,place\n"
    . "9876543210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n";
$caughtNoName = false;
try {
    CsvValidationService::validate($noNameCsv, $template);
} catch (ValidationException $e) {
    $caughtNoName = true;
}
assertEngine('Missing mandatory name column is strictly rejected', $caughtNoName);

// 2.3 Unsupported extra column
$unsupportedCsv = "name,phone,date,event_title,organization,duration,place,unsupported_column_xyz\n"
    . "Test User,9876543210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode,invalid\n";
$caughtUnsupported = false;
$unsupportedMsg = '';
try {
    CsvValidationService::validate($unsupportedCsv, $template);
} catch (ValidationException $e) {
    $caughtUnsupported = true;
    $unsupportedMsg = $e->getMessage();
}
assertEngine('Unsupported CSV column strictly throws ValidationException', $caughtUnsupported);
assertEngine('Error message explicitly lists unsupported column name', str_contains($unsupportedMsg, 'unsupported_column_xyz'));

// 2.4 Empty or malformed CSV
$caughtEmpty = false;
try {
    CsvValidationService::validate("   \n\n  ", $template);
} catch (ValidationException $e) {
    $caughtEmpty = true;
}
assertEngine('Empty CSV string is strictly rejected with ValidationException', $caughtEmpty);

// 2.5 Invalid Row: Blank recipient name
$blankNameCsv = "name,phone,date,event_title,organization,duration,place\n"
    . ",9876543210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n"
    . "A,9876543211,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n"
    . "Valid User,9876543212,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n";
$resBlankName = CsvValidationService::validate($blankNameCsv, $template);
assertEngine('Blank/single-char recipient names flagged as invalid rows', $resBlankName['invalid_count'] === 2 && $resBlankName['valid_count'] === 1);
assertEngine('Blank name row error explains minimum character requirement', str_contains($resBlankName['invalid_rows'][0]['error'], 'Recipient Name is required'));

// 2.6 Invalid Row: Empty required template value
$emptyReqCsv = "name,phone,date,event_title,organization,duration,place\n"
    . "Test User,9876543210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,\n";
$resEmptyReq = CsvValidationService::validate($emptyReqCsv, $template);
assertEngine('Empty required template variable on data row flagged as invalid', $resEmptyReq['invalid_count'] === 1);
assertEngine('Empty required variable error identifies missing field', str_contains($resEmptyReq['invalid_rows'][0]['error'], 'place'));

// 2.7 Invalid Row: Invalid phone format
$invalidPhoneCsv = "name,phone,date,event_title,organization,duration,place\n"
    . "Test User,12345,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n"
    . "Test User 2,not-a-number,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n";
$resInvalidPhone = CsvValidationService::validate($invalidPhoneCsv, $template);
assertEngine('Invalid phone formats correctly flagged as invalid rows', $resInvalidPhone['invalid_count'] === 2);
assertEngine('Phone error explains 10-15 digit requirement', str_contains($resInvalidPhone['invalid_rows'][0]['error'], 'valid 10-15 digit mobile number'));

// 2.8 Invalid Row: Duplicate phone in same CSV (normalized comparison)
$dupPhoneCsv = "name,phone,date,event_title,organization,duration,place\n"
    . "Original Recipient,9876543210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n"
    . "Duplicate Recipient,+91 98765 43210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n";
$resDupPhone = CsvValidationService::validate($dupPhoneCsv, $template);
assertEngine('Duplicate phone number within file flagged as invalid row', $resDupPhone['invalid_count'] === 1 && $resDupPhone['valid_count'] === 1);
assertEngine('Duplicate phone error mentions original row number (row 2)', str_contains($resDupPhone['invalid_rows'][0]['error'], 'row 2'));

// 2.9 Invalid Row: Malformed email
$badEmailCsv = "name,phone,date,event_title,organization,duration,place,email\n"
    . "Test User,9876543210,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode,not-an-email\n";
$resBadEmail = CsvValidationService::validate($badEmailCsv, $template);
assertEngine('Invalid email address format flagged as invalid row', $resBadEmail['invalid_count'] === 1);

// =============================================================================
// SECTION 3: Response Contract & No Undefined .map Invariant
// =============================================================================
echo "\n--- 3. Testing Backend Response Contract & No Undefined .map ---\n";

assertEngine('Validation result contains total_records integer', isset($resDupPhone['total_records']) && is_int($resDupPhone['total_records']));
assertEngine('Validation result contains valid_count integer', isset($resDupPhone['valid_count']) && is_int($resDupPhone['valid_count']));
assertEngine('Validation result contains invalid_count integer', isset($resDupPhone['invalid_count']) && is_int($resDupPhone['invalid_count']));
assertEngine('Validation result contains invalid_rows array', isset($resDupPhone['invalid_rows']) && is_array($resDupPhone['invalid_rows']));

$invalidRow = $resDupPhone['invalid_rows'][0];
assertEngine('invalid_rows item contains line number property', isset($invalidRow['line']) && is_int($invalidRow['line']));
assertEngine('invalid_rows item contains row number property (backward compatibility)', isset($invalidRow['row']) && is_int($invalidRow['row']));
assertEngine('invalid_rows item contains errors array (CRITICAL: prevents undefined.map)', isset($invalidRow['errors']) && is_array($invalidRow['errors']));
assertEngine('invalid_rows item errors array is non-empty', count($invalidRow['errors']) > 0 && is_string($invalidRow['errors'][0]));
assertEngine('invalid_rows item contains data object with name and phone', isset($invalidRow['data']['name'], $invalidRow['data']['phone']));

// Verify Error Report CSV generation
$errorCsv = CsvValidationService::generateErrorReportCsv($resDupPhone['invalid_rows']);
assertEngine('Error report CSV includes headers (Row Number, Recipient Name, Phone Provided, Validation Error)', str_contains($errorCsv, 'Row Number') && str_contains($errorCsv, 'Validation Error'));
assertEngine('Error report CSV contains duplicate row detail', str_contains($errorCsv, 'Duplicate Recipient'));

// =============================================================================
// SECTION 4: Phone Invariant Verification
// =============================================================================
echo "\n--- 4. Testing Phone Invariant ---\n";

// 4.1 Phone is NOT in VariableRegistry::getAll()
$allRegistryVars = VariableRegistry::getAll();
assertEngine('Phone is strictly EXCLUDED from VariableRegistry::getAll() design variables', !array_key_exists('phone', $allRegistryVars));

// 4.2 Phone is NOT mandatory in validateTemplateRequirements
$caughtTemplateReq = false;
try {
    VariableRegistry::validateTemplateRequirements(['name', 'certificate_number']);
} catch (ValidationException $e) {
    $caughtTemplateReq = true;
}
assertEngine('validateTemplateRequirements passes without phone (only name is mandatory)', !$caughtTemplateReq);

// 4.3 Phone is strictly NOT rendered on certificate
$rendered = VariableRegistry::replacePlaceholders('Name: {{name}}, Phone: {{phone}}, Date: {{date}}', [
    'name'  => 'Secret Recipient',
    'phone' => '+919999999999',
    'date'  => '10 September 2026',
]);
assertEngine('replacePlaceholders replaces {{name}} and {{date}}', str_contains($rendered, 'Secret Recipient') && str_contains($rendered, '10 September 2026'));
assertEngine('replacePlaceholders strictly SUPPRESSES {{phone}} (replaces with empty string)', !str_contains($rendered, '+919999999999') && !str_contains($rendered, '{{phone}}'));

// 4.4 Phone normalization still functions for database ingestion and search
$normPhone = CsvValidationService::normalizePhoneNumber('098765 43210');
assertEngine('normalizePhoneNumber correctly standardizes 10-digit/local Indian numbers to +91 E.164', $normPhone === '+919876543210');

// =============================================================================
// SECTION 5: Template Variable Invariant & Sample CSV Parity
// =============================================================================
echo "\n--- 5. Testing Template Variable Invariant & Sample CSV Parity ---\n";

$expectedVars = CsvValidationService::getExpectedTemplateVariables($template);
assertEngine('getExpectedTemplateVariables returns template design variables', in_array('name', $expectedVars, true) && in_array('date', $expectedVars, true) && in_array('place', $expectedVars, true));
assertEngine('getExpectedTemplateVariables excludes phone', !in_array('phone', $expectedVars, true));
assertEngine('getExpectedTemplateVariables excludes system variables', !in_array('certificate_number', $expectedVars, true) && !in_array('verification_url', $expectedVars, true));

$sampleCsv = CsvValidationService::generateSampleCsv($template);
$firstLine = strtok($sampleCsv, "\r\n");
$sampleHeaders = str_getcsv($firstLine, ',', '"', '\\');
assertEngine('Sample CSV starts with name and phone', $sampleHeaders[0] === 'name' && $sampleHeaders[1] === 'phone');
assertEngine('Sample CSV includes template design variables (date, event_title, organization, duration, place)', in_array('date', $sampleHeaders, true) && in_array('event_title', $sampleHeaders, true) && in_array('place', $sampleHeaders, true));
assertEngine('Sample CSV contains data row for Gatekeeper Training', str_contains($sampleCsv, 'Gatekeeper Training (Level 1)'));

// =============================================================================
// SECTION 6: Exact Uploaded CSV Test (75 rows)
// =============================================================================
echo "\n--- 6. Testing Exact Uploaded CSV: sample_certificate_data_suicide_gatekeeper_training (3).csv ---\n";

$exactCsvPath = 'C:/Users/incub/Downloads/sample_certificate_data_suicide_gatekeeper_training (3).csv';
if (file_exists($exactCsvPath)) {
    $exactCsvContent = file_get_contents($exactCsvPath);
    $exactRes = CsvValidationService::validate($exactCsvContent, $template);

    assertEngine('Exact uploaded CSV total records is 75', $exactRes['total_records'] === 75);
    assertEngine('Exact uploaded CSV valid count is 74', $exactRes['valid_count'] === 74);
    assertEngine('Exact uploaded CSV invalid count is 1 (duplicate phone on row 76)', $exactRes['invalid_count'] === 1);
    assertEngine('Invalid row 76 correctly identifies HASNA FATHIMA', $exactRes['invalid_rows'][0]['name'] === 'HASNA FATHIMA');
    assertEngine('Invalid row 76 line number is 76', $exactRes['invalid_rows'][0]['line'] === 76);
    assertEngine('Invalid row 76 errors array is non-empty (no undefined .map)', is_array($exactRes['invalid_rows'][0]['errors']) && count($exactRes['invalid_rows'][0]['errors']) === 1);
    assertEngine('Invalid row 76 error explains duplicate with row 20', str_contains($exactRes['invalid_rows'][0]['errors'][0], 'row 20'));
} else {
    echo "  [SKIP] Exact CSV file not found on disk at {$exactCsvPath}\n";
}

// =============================================================================
// SECTION 7: Batch Phone Isolation & Global Uniqueness Invariants
// =============================================================================
echo "\n--- 7. Testing Batch Phone Isolation & Global Uniqueness Invariants ---\n";

// 7.1 Same phone in Different Batches is 100% VALID
$batchACsv = "name,phone,date,event_title,organization,duration,place\n"
    . "HASNA FATHIMA,9400804835,10 September 2026,Gatekeeper Training,Listening Community SPC,2 Hours,Kozhikode\n";
$batchBCsv = "name,phone,date,event_title,organization,duration,place\n"
    . "HASNA FATHIMA,9400804835,18 October 2026,Mental Health Workshop,Listening Community SPC,4 Hours,Ernakulam\n";

$resBatchA = CsvValidationService::validate($batchACsv, $template);
$resBatchB = CsvValidationService::validate($batchBCsv, $template);
assertEngine('Batch A with phone 9400804835 is VALID (Total: 1, Valid: 1)', $resBatchA['valid_count'] === 1 && $resBatchA['invalid_count'] === 0);
assertEngine('Batch B with same phone 9400804835 is VALID (phone is batch-scoped, not globally unique)', $resBatchB['valid_count'] === 1 && $resBatchB['invalid_count'] === 0);

// 7.2 Database schema inspection: phone_normalized is NOT globally unique
try {
    $pdo = App\Core\Database::getConnection();
    $indexes = $pdo->query("SHOW INDEX FROM `v3_certificates`")->fetchAll(PDO::FETCH_ASSOC);
    $phoneUnique = false;
    $certIdUnique = false;
    $tokenUnique = false;

    foreach ($indexes as $idx) {
        if ($idx['Column_name'] === 'phone_normalized' && (int)$idx['Non_unique'] === 0) {
            $phoneUnique = true;
        }
        if ($idx['Column_name'] === 'certificate_id' && (int)$idx['Non_unique'] === 0) {
            $certIdUnique = true;
        }
        if ($idx['Column_name'] === 'verification_token' && (int)$idx['Non_unique'] === 0) {
            $tokenUnique = true;
        }
    }

    assertEngine('Database invariant: phone_normalized has NO unique constraint (Non_unique = 1)', !$phoneUnique);
    assertEngine('Database invariant: certificate_id remains globally unique (Non_unique = 0)', $certIdUnique);
    assertEngine('Database invariant: verification_token remains globally unique (Non_unique = 0)', $tokenUnique);
} catch (\Throwable $e) {
    echo "  [SKIP] MariaDB connection check skipped: " . $e->getMessage() . "\n";
}

// =============================================================================
// SUMMARY
// =============================================================================
echo "\n=== Test Summary ===\n";
echo "Total Assertions: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: {$failedTests}\n";

if ($failedTests > 0) {
    exit(1);
}
echo "ALL TESTS PASSED!\n";
