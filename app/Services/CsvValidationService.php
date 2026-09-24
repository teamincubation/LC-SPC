<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\ValidationException;

/**
 * Robust CSV Validation & Normalization Engine for Certificate Generation
 * Handles UTF-8 encoding, BOM stripping, delimiter sniffing, formula injection defense,
 * mandatory name & phone validation, phone normalization, and row-level error reporting.
 */
class CsvValidationService
{
    public const MAX_FILE_SIZE_BYTES = 10485760; // 10 MB
    public const MAX_RECORDS_LIMIT = 5000;

    /**
     * Resolve the list of required design variables for a given template.
     * System variables ('certificate_number', 'verification_url', 'verification_token')
     * and ingestion fields ('phone') are excluded from design requirements.
     * 'name' is always mandatory.
     *
     * @param array $template
     * @return array List of required column names (e.g. ['name', 'date', 'event_title', 'place'])
     */
    public static function getExpectedTemplateVariables(array $template): array
    {
        $vars = $template['required_variables'] ?? [];
        if (is_string($vars)) {
            $vars = json_decode($vars, true) ?: [];
        }

        $systemExclude = ['certificate_number', 'verification_url', 'verification_token', 'phone'];
        $cleanVars = [];

        foreach ($vars as $v) {
            $k = is_array($v) ? ($v['key'] ?? '') : (string) $v;
            $cleanKey = trim(strtolower(str_replace(['{{', '}}'], '', $k)));
            $cleanKey = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', $cleanKey));
            if ($cleanKey !== '' && !in_array($cleanKey, $systemExclude, true) && !in_array($cleanKey, $cleanVars, true)) {
                $cleanVars[] = $cleanKey;
            }
        }

        // Also inspect layout elements if present
        $layout = $template['layout_config'] ?? [];
        if (is_string($layout)) {
            $layout = json_decode($layout, true) ?: [];
        }
        $elements = $layout['elements'] ?? [];
        foreach ($elements as $el) {
            $type = $el['type'] ?? '';
            if ($type === 'variable' && !empty($el['variable'])) {
                $k = trim(strtolower(str_replace(['{{', '}}'], '', (string) $el['variable'])));
                $k = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', $k));
                if ($k !== '' && !in_array($k, $systemExclude, true) && !in_array($k, $cleanVars, true)) {
                    $cleanVars[] = $k;
                }
            } elseif (($type === 'text' || $type === 'dynamic_text') && !empty($el['text'])) {
                $extracted = VariableRegistry::extractPlaceholders((string) $el['text']);
                foreach ($extracted as $k) {
                    $k = trim(strtolower($k));
                    $k = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', $k));
                    if ($k !== '' && !in_array($k, $systemExclude, true) && !in_array($k, $cleanVars, true)) {
                        $cleanVars[] = $k;
                    }
                }
            }
        }

        // 'name' is always mandatory for any certificate
        if (!in_array('name', $cleanVars, true)) {
            array_unshift($cleanVars, 'name');
        }

        return $cleanVars;
    }

    /**
     * Generate template-specific sample CSV content based on actual template variables.
     */
    public static function generateSampleCsv(array $template): string
    {
        $expectedVars = self::getExpectedTemplateVariables($template);

        // Standard column order:
        // 1. name
        // 2. phone (ingestion & lookup field)
        // 3. template-specific design variables
        // 4. email (standard contact field)
        $headers = ['name', 'phone'];
        foreach ($expectedVars as $v) {
            if (!in_array($v, $headers, true)) {
                $headers[] = $v;
            }
        }
        if (!in_array('email', $headers, true)) {
            $headers[] = 'email';
        }

        $allVars = VariableRegistry::getAll();
        $sampleRow1 = [];
        $sampleRow2 = [];

        foreach ($headers as $h) {
            if ($h === 'name') {
                $sampleRow1[] = 'John Mathew';
                $sampleRow2[] = 'Sarah Jenkins';
            } elseif ($h === 'phone') {
                $sampleRow1[] = '+919876543210';
                $sampleRow2[] = '+919876543211';
            } elseif ($h === 'email') {
                $sampleRow1[] = 'john.m@example.com';
                $sampleRow2[] = 'sarah.j@example.com';
            } elseif ($h === 'date') {
                $sampleRow1[] = '10 September 2026';
                $sampleRow2[] = '10 September 2026';
            } elseif ($h === 'event_title') {
                $sampleRow1[] = $template['name'] ?? 'Gatekeeper Training (Level 1)';
                $sampleRow2[] = $template['name'] ?? 'Gatekeeper Training (Level 1)';
            } elseif ($h === 'organization') {
                $sampleRow1[] = 'Listening Community SPC';
                $sampleRow2[] = 'Listening Community SPC';
            } elseif ($h === 'duration') {
                $sampleRow1[] = '2 Hours';
                $sampleRow2[] = '2 Hours';
            } elseif ($h === 'place') {
                $sampleRow1[] = 'MES T O Abdulla Memorial College, Kunnukara';
                $sampleRow2[] = 'MES T O Abdulla Memorial College, Kunnukara';
            } else {
                $sampleRow1[] = $allVars[$h]['example'] ?? 'Sample ' . ucfirst($h);
                $sampleRow2[] = $allVars[$h]['example'] ?? 'Sample ' . ucfirst($h);
            }
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers, ',', '"', "\\");
        fputcsv($output, $sampleRow1, ',', '"', "\\");
        fputcsv($output, $sampleRow2, ',', '"', "\\");
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Validate and parse uploaded CSV file contents.
     *
     * @param string $csvContent Raw CSV file string
     * @param array $template Template record to match required headers against
     * @return array [
     *     'total_records'  => int,
     *     'valid_count'    => int,
     *     'invalid_count'  => int,
     *     'valid_records'  => array,
     *     'invalid_rows'   => array,
     *     'headers'        => array,
     * ]
     * @throws ValidationException
     */
    public static function validate(string $csvContent, array $template): array
    {
        if (trim($csvContent) === '') {
            throw new ValidationException('The uploaded CSV file is empty.', ['csv_file' => 'File is empty.']);
        }

        // 1. Strip UTF-8 BOM (\xEF\xBB\xBF)
        if (str_starts_with($csvContent, "\xEF\xBB\xBF")) {
            $csvContent = substr($csvContent, 3);
        }

        // 2. Ensure valid UTF-8
        if (!mb_check_encoding($csvContent, 'UTF-8')) {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'ISO-8859-1, Windows-1252, auto');
        }

        // 3. Detect Delimiter
        $delimiter = self::sniffDelimiter($csvContent);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);

        // 4. Parse Header Row
        $rawHeaders = fgetcsv($stream, 0, $delimiter, '"', "\\");
        if (!$rawHeaders || empty(array_filter($rawHeaders))) {
            fclose($stream);
            throw new ValidationException('Unable to parse CSV headers. Please ensure the file has a valid header row.');
        }

        $headers = array_map(function ($h) {
            $cleaned = trim(strtolower((string) $h));
            return preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', $cleaned));
        }, $rawHeaders);

        // Resolve expected design variables for selected template
        $expectedVars = self::getExpectedTemplateVariables($template);

        // Check required template headers
        $missingHeaders = [];
        foreach ($expectedVars as $reqVar) {
            if (!in_array($reqVar, $headers, true)) {
                $missingHeaders[] = $reqVar;
            }
        }

        if (!empty($missingHeaders)) {
            fclose($stream);
            throw new ValidationException(
                'Missing required columns for selected template: [' . implode(', ', $missingHeaders) . ']. Please ensure your CSV contains all required template variables.',
                ['csv_headers' => 'Required columns missing: ' . implode(', ', $missingHeaders)]
            );
        }

        // Check for unsupported CSV columns
        $allRegistryVars = array_keys(VariableRegistry::getAll());
        $allowedHeaders = array_unique(array_merge(
            $allRegistryVars,
            $expectedVars,
            ['phone', 'email']
        ));

        $unsupportedHeaders = [];
        foreach ($headers as $h) {
            if (!in_array($h, $allowedHeaders, true)) {
                $unsupportedHeaders[] = $h;
            }
        }

        if (!empty($unsupportedHeaders)) {
            fclose($stream);
            throw new ValidationException(
                'Unsupported CSV columns: [' . implode(', ', $unsupportedHeaders) . ']. Please use only columns supported by this template or system variables.',
                ['csv_headers' => 'Unsupported columns: ' . implode(', ', $unsupportedHeaders)]
            );
        }

        $validRecords = [];
        $invalidRows = [];
        $seenPhonesInFile = [];
        $hasPhoneColumn = in_array('phone', $headers, true);
        $rowNumber = 1; // Header is row 1, data starts at row 2

        while (($row = fgetcsv($stream, 0, $delimiter, '"', "\\")) !== false) {
            $rowNumber++;

            // Skip completely empty lines
            if (empty(array_filter($row, fn($val) => trim((string) $val) !== ''))) {
                continue;
            }

            if (count($validRecords) + count($invalidRows) >= self::MAX_RECORDS_LIMIT) {
                $err = 'Maximum record batch limit (' . self::MAX_RECORDS_LIMIT . ') reached. Remaining rows ignored.';
                $invalidRows[] = [
                    'line'   => $rowNumber,
                    'row'    => $rowNumber,
                    'name'   => '-',
                    'phone'  => '-',
                    'error'  => $err,
                    'errors' => [$err],
                    'data'   => ['name' => '-', 'phone' => '-'],
                ];
                break;
            }

            // Map row to associative array
            $data = [];
            foreach ($headers as $index => $key) {
                $val = isset($row[$index]) ? trim((string) $row[$index]) : '';
                // CSV Formula Injection Defense
                $data[$key] = self::sanitizeFormulaInjection($val);
            }

            $name = $data['name'] ?? '';
            $phone = $data['phone'] ?? '';

            // Validation Rule 1: Blank Name
            if ($name === '' || mb_strlen($name) < 2) {
                $err = 'Recipient Name is required (minimum 2 characters).';
                $invalidRows[] = [
                    'line'   => $rowNumber,
                    'row'    => $rowNumber,
                    'name'   => $name ?: '-',
                    'phone'  => $phone ?: '-',
                    'error'  => $err,
                    'errors' => [$err],
                    'data'   => $data,
                ];
                continue;
            }

            // Validation Rule 2: Check required template variables are not empty on this row
            $emptyReqVar = null;
            foreach ($expectedVars as $reqVar) {
                if ($reqVar === 'name') continue;
                $val = $data[$reqVar] ?? '';
                if ($val === '') {
                    // Check if fallback exists in template
                    if ($reqVar === 'event_title' && !empty($template['name'])) {
                        $data['event_title'] = $template['name'];
                    } elseif ($reqVar === 'date') {
                        $data['date'] = date('d F Y');
                    } elseif ($reqVar === 'certificate_type' && !empty($template['certificate_type'])) {
                        $data['certificate_type'] = $template['certificate_type'];
                    } else {
                        $emptyReqVar = $reqVar;
                        break;
                    }
                }
            }

            if ($emptyReqVar !== null) {
                $err = "Required value for '{$emptyReqVar}' is empty.";
                $invalidRows[] = [
                    'line'   => $rowNumber,
                    'row'    => $rowNumber,
                    'name'   => $name,
                    'phone'  => $phone ?: '-',
                    'error'  => $err,
                    'errors' => [$err],
                    'data'   => $data,
                ];
                continue;
            }

            // Validation Rule 3: Phone validation (if phone column present)
            if ($hasPhoneColumn) {
                if ($phone === '') {
                    $err = 'Phone / WhatsApp number is required when phone column is provided.';
                    $invalidRows[] = [
                        'line'   => $rowNumber,
                        'row'    => $rowNumber,
                        'name'   => $name,
                        'phone'  => '-',
                        'error'  => $err,
                        'errors' => [$err],
                        'data'   => $data,
                    ];
                    continue;
                }

                $normalizedPhone = self::normalizePhoneNumber($phone);
                if ($normalizedPhone === null) {
                    $err = 'Invalid phone number format. Must be a valid 10-15 digit mobile number.';
                    $invalidRows[] = [
                        'line'   => $rowNumber,
                        'row'    => $rowNumber,
                        'name'   => $name,
                        'phone'  => $phone,
                        'error'  => $err,
                        'errors' => [$err],
                        'data'   => $data,
                    ];
                    continue;
                }
                $data['phone_normalized'] = $normalizedPhone;

                // Duplicate check in same CSV
                if (isset($seenPhonesInFile[$normalizedPhone])) {
                    $prevRow = $seenPhonesInFile[$normalizedPhone];
                    $err = "Duplicate phone number in this CSV batch. Already used in row {$prevRow}.";
                    $invalidRows[] = [
                        'line'   => $rowNumber,
                        'row'    => $rowNumber,
                        'name'   => $name,
                        'phone'  => $phone,
                        'error'  => $err,
                        'errors' => [$err],
                        'data'   => $data,
                    ];
                    continue;
                }
                $seenPhonesInFile[$normalizedPhone] = $rowNumber;
            } else {
                $data['phone'] = '';
                $data['phone_normalized'] = '';
            }

            // Validation Rule 4: Email format check (if present and non-empty)
            if (!empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $err = 'Invalid email address format: ' . htmlspecialchars($data['email']);
                    $invalidRows[] = [
                        'line'   => $rowNumber,
                        'row'    => $rowNumber,
                        'name'   => $name,
                        'phone'  => $phone ?: '-',
                        'error'  => $err,
                        'errors' => [$err],
                        'data'   => $data,
                    ];
                    continue;
                }
            }

            // Defaults from template if not provided
            if (empty($data['certificate_type'])) {
                $data['certificate_type'] = $template['certificate_type'] ?? 'Certificate of Participation';
            }
            if (empty($data['event_title']) && !empty($template['name'])) {
                $data['event_title'] = $template['name'];
            }
            if (empty($data['date'])) {
                $data['date'] = date('d F Y');
            }

            $data['_row_number'] = $rowNumber;
            $validRecords[] = $data;
        }

        fclose($stream);

        return [
            'total_records' => count($validRecords) + count($invalidRows),
            'valid_count'   => count($validRecords),
            'invalid_count' => count($invalidRows),
            'valid_records' => $validRecords,
            'invalid_rows'  => $invalidRows,
            'headers'       => $headers,
        ];
    }

    /**
     * Generate an Error Report CSV for download.
     */
    public static function generateErrorReportCsv(array $invalidRows): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['Row Number', 'Recipient Name', 'Phone Provided', 'Validation Error'], ',', '"', "\\");

        foreach ($invalidRows as $err) {
            $rowNum = $err['line'] ?? $err['row'] ?? '';
            $nameVal = $err['name'] ?? ($err['data']['name'] ?? '');
            $phoneVal = $err['phone'] ?? ($err['data']['phone'] ?? '');
            $errorMsg = is_array($err['errors'] ?? null) ? implode('; ', $err['errors']) : ($err['error'] ?? '');
            fputcsv($output, [
                $rowNum,
                $nameVal,
                $phoneVal,
                $errorMsg,
            ], ',', '"', "\\");
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Neutralize CSV Formula Injection (DDE / Excel formula execution risks).
     * Prefixes malicious leading characters (=, +, -, @, \t, \r) with an apostrophe.
     */
    public static function sanitizeFormulaInjection(string $value): string
    {
        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Standardize phone number into normalized E.164-compatible format (+919876543210).
     */
    public static function normalizePhone(string $phone): ?string
    {
        return self::normalizePhoneNumber($phone);
    }

    /**
     * Standardize phone number into normalized E.164-compatible format (+919876543210).
     */
    public static function normalizePhoneNumber(string $phone): ?string
    {
        // Remove spaces, hyphens, brackets, dots
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        if ($cleaned === '') {
            return null;
        }

        // Handle Indian local numbers starting with 0
        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 11) {
            $cleaned = '+91' . substr($cleaned, 1);
        } elseif (strlen($cleaned) === 10 && ctype_digit($cleaned)) {
            // Standard 10-digit Indian mobile
            $cleaned = '+91' . $cleaned;
        } elseif (str_starts_with($cleaned, '91') && strlen($cleaned) === 12) {
            $cleaned = '+' . $cleaned;
        }

        // Validate final length: + followed by 10 to 14 digits
        if (preg_match('/^\+[1-9]\d{9,14}$/', $cleaned)) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Sniff CSV delimiter (comma, semicolon, tab).
     */
    private static function sniffDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n");
        if ($firstLine === false) {
            return ',';
        }

        $delimiters = [',', ';', "\t"];
        $counts = [];
        foreach ($delimiters as $d) {
            $counts[$d] = substr_count($firstLine, $d);
        }

        arsort($counts);
        $best = key($counts);

        return $counts[$best] > 0 ? $best : ',';
    }
}
