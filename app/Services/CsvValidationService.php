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
     * Generate template-specific sample CSV content.
     */
    public static function generateSampleCsv(array $template): string
    {
        $vars = $template['required_variables'] ?? [];
        if (is_string($vars)) {
            $vars = json_decode($vars, true) ?: [];
        }

        // Ensure mandatory fields come first
        $headers = ['name', 'phone'];
        foreach ($vars as $v) {
            $k = is_array($v) ? ($v['key'] ?? '') : (string) $v;
            $cleanKey = trim(str_replace(['{{', '}}'], '', $k));
            if ($cleanKey !== '' && !in_array($cleanKey, $headers, true) && $cleanKey !== 'certificate_number') {
                $headers[] = $cleanKey;
            }
        }

        // Add common optional headers if not already present
        foreach (['event_title', 'date', 'place', 'email'] as $extra) {
            if (!in_array($extra, $headers, true)) {
                $headers[] = $extra;
            }
        }

        $allVars = VariableRegistry::getAll();
        $sampleRow1 = [];
        $sampleRow2 = [];

        foreach ($headers as $h) {
            $sampleRow1[] = $allVars[$h]['example'] ?? 'Sample ' . ucfirst($h);
            if ($h === 'name') {
                $sampleRow2[] = 'Sarah Jenkins';
            } elseif ($h === 'phone') {
                $sampleRow2[] = '+919876543211';
            } elseif ($h === 'email') {
                $sampleRow2[] = 'sarah.j@example.com';
            } else {
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

        // Check required mandatory headers: name & phone
        $missingHeaders = [];
        if (!in_array('name', $headers, true)) {
            $missingHeaders[] = 'name';
        }
        if (!in_array('phone', $headers, true)) {
            $missingHeaders[] = 'phone';
        }

        if (!empty($missingHeaders)) {
            fclose($stream);
            throw new ValidationException(
                'Missing mandatory CSV headers: [' . implode(', ', $missingHeaders) . ']. Both "name" and "phone" columns are required.',
                ['csv_headers' => 'Required columns missing: ' . implode(', ', $missingHeaders)]
            );
        }

        $validRecords = [];
        $invalidRows = [];
        $seenPhonesInFile = [];
        $rowNumber = 1; // Header is row 1, data starts at row 2

        while (($row = fgetcsv($stream, 0, $delimiter, '"', "\\")) !== false) {
            $rowNumber++;

            // Skip completely empty lines
            if (empty(array_filter($row, fn($val) => trim((string) $val) !== ''))) {
                continue;
            }

            if (count($validRecords) + count($invalidRows) >= self::MAX_RECORDS_LIMIT) {
                $invalidRows[] = [
                    'row'   => $rowNumber,
                    'name'  => '—',
                    'phone' => '—',
                    'error' => 'Maximum record batch limit (' . self::MAX_RECORDS_LIMIT . ') reached. Remaining rows ignored.',
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
                $invalidRows[] = [
                    'row'   => $rowNumber,
                    'name'  => $name ?: '—',
                    'phone' => $phone ?: '—',
                    'error' => 'Recipient Name is required (minimum 2 characters).',
                ];
                continue;
            }

            // Validation Rule 2: Blank Phone
            if ($phone === '') {
                $invalidRows[] = [
                    'row'   => $rowNumber,
                    'name'  => $name,
                    'phone' => '—',
                    'error' => 'Phone / WhatsApp number is required.',
                ];
                continue;
            }

            // Validation Rule 3: Phone Normalization & Format
            $normalizedPhone = self::normalizePhoneNumber($phone);
            if ($normalizedPhone === null) {
                $invalidRows[] = [
                    'row'   => $rowNumber,
                    'name'  => $name,
                    'phone' => $phone,
                    'error' => 'Invalid phone number format. Must be a valid 10-15 digit mobile number.',
                ];
                continue;
            }
            $data['phone_normalized'] = $normalizedPhone;

            // Validation Rule 4: Duplicate in same CSV
            if (isset($seenPhonesInFile[$normalizedPhone])) {
                $prevRow = $seenPhonesInFile[$normalizedPhone];
                $invalidRows[] = [
                    'row'   => $rowNumber,
                    'name'  => $name,
                    'phone' => $phone,
                    'error' => "Duplicate phone number in file (already present on row {$prevRow}).",
                ];
                continue;
            }
            $seenPhonesInFile[$normalizedPhone] = $rowNumber;

            // Validation Rule 5: Email format check (if present)
            if (!empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $invalidRows[] = [
                        'row'   => $rowNumber,
                        'name'  => $name,
                        'phone' => $phone,
                        'error' => 'Invalid email address format: ' . htmlspecialchars($data['email']),
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
            fputcsv($output, [
                $err['row'],
                $err['name'],
                $err['phone'],
                $err['error'],
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
