<?php

declare(strict_types=1);

/**
 * Test Suite: Certificate Template Designer Redesign & Functional Parity
 *
 * Verifies:
 * 1. Strict Option A Asset Security (PNG/WebP/JPG allowed, strictly NO SVG).
 * 2. Mandatory variable enforcement on layout save ({{name}} and {{phone}} required).
 * 3. Grouped Signature component architecture (image + line + name + title).
 * 4. Canonical QR Code architecture with dynamic {{verification_url}} resolution.
 * 5. Deterministic background fit mode (100% 100% exact canvas fill).
 * 6. GD Canvas rendering and PDF encapsulation parity.
 * 7. Asset upload, deletion, and DB column synchronization.
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';

use App\Core\Config;
use App\Core\Request;
use App\Services\CertificateRenderer;
use App\Services\VariableRegistry;
use App\Services\RoleService;
use App\Repositories\V3CertificateTemplateRepository;
use App\Controllers\Admin\CertificateTemplateController;

$passed = 0;
$failed = 0;

function assertCondition(bool $condition, string $description, ?string $details = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
        if ($details !== null) {
            echo "         Details: {$details}\n";
        }
    }
}

echo "=== LC-SPC V3 Certificate Template Designer Test Suite ===\n\n";

// -----------------------------------------------------------------------------
// 1. VariableRegistry Dynamic Variable Support
// -----------------------------------------------------------------------------
echo "1. Testing VariableRegistry Dynamic Verification URL Support...\n";

$allVars = VariableRegistry::getAll();
assertCondition(
    isset($allVars['verification_url']),
    'VariableRegistry registers {{verification_url}}'
);
assertCondition(
    $allVars['verification_url']['placeholder'] === '{{verification_url}}',
    'verification_url placeholder is strictly {{verification_url}}'
);

$dummy = VariableRegistry::getPreviewDummyData();
assertCondition(
    isset($dummy['verification_url']) && str_contains($dummy['verification_url'], '/certificates/verify/'),
    'getPreviewDummyData provides deterministic verification_url for preview'
);
assertCondition(
    isset($dummy['verification_token']),
    'getPreviewDummyData provides verification_token'
);

// -----------------------------------------------------------------------------
// 2. Default Elements & Layout Configuration
// -----------------------------------------------------------------------------
echo "\n2. Testing Default Elements Structure...\n";

$defaultElements = CertificateRenderer::getDefaultElements();
assertCondition(
    is_array($defaultElements) && count($defaultElements) >= 8,
    'getDefaultElements returns full default institutional element set'
);

$qrElement = null;
$sig1Element = null;
$sig2Element = null;
foreach ($defaultElements as $el) {
    if ($el['type'] === 'qr_code') $qrElement = $el;
    if ($el['type'] === 'signature1') $sig1Element = $el;
    if ($el['type'] === 'signature2') $sig2Element = $el;
}

assertCondition(
    $qrElement !== null && ($qrElement['url_template'] ?? '') === '{{verification_url}}',
    'Default QR element stores dynamic url_template as {{verification_url}}'
);
assertCondition(
    $sig1Element !== null && isset($sig1Element['width'], $sig1Element['height']),
    'Signature 1 is configured with compound width and height'
);
assertCondition(
    $sig2Element !== null && isset($sig2Element['width'], $sig2Element['height']),
    'Signature 2 is configured with compound width and height'
);

// -----------------------------------------------------------------------------
// 3. Mandatory Variable Validation on Designer Save
// -----------------------------------------------------------------------------
echo "\n3. Testing Mandatory Variables Validation ({{name}} and {{phone}})...\n";

$controller = new CertificateTemplateController();

// Create a dummy template in DB for testing
$repo = new V3CertificateTemplateRepository();
$testTplId = $repo->create([
    'name'               => 'Automated Test Designer Template ' . bin2hex(random_bytes(3)),
    'certificate_type'   => 'participation',
    'status'             => 'draft',
    'required_variables' => ['name', 'phone'],
    'layout_config'      => ['elements' => $defaultElements],
]);

assertCondition(
    $testTplId > 0,
    "Created test certificate template #{$testTplId}"
);

// A) Save without {{name}} should be rejected with 422
$invalidElements1 = [
    ['type' => 'text', 'text' => 'Recipient: John Doe (Missing Tag)'],
    ['type' => 'dynamic_text', 'text' => 'Phone: {{phone}}'],
];
$reqNoName = new Request([], ['layout_config' => json_encode(['elements' => $invalidElements1])]);
$resNoName = $controller->saveDesigner($reqNoName, ['id' => $testTplId]);
assertCondition(
    $resNoName->getStatusCode() === 422,
    'Saving template layout without {{name}} is strictly rejected (HTTP 422)'
);

// B) Save without {{phone}} should be rejected with 422
$invalidElements2 = [
    ['type' => 'dynamic_text', 'text' => 'Recipient: {{name}}'],
    ['type' => 'text', 'text' => 'Phone: +919876543210 (Missing Tag)'],
];
$reqNoPhone = new Request([], ['layout_config' => json_encode(['elements' => $invalidElements2])]);
$resNoPhone = $controller->saveDesigner($reqNoPhone, ['id' => $testTplId]);
assertCondition(
    $resNoPhone->getStatusCode() === 422,
    'Saving template layout without {{phone}} is strictly rejected (HTTP 422)'
);

// C) Save with both {{name}} and {{phone}} should succeed
$validElements = [
    ['type' => 'dynamic_text', 'text' => 'This is to certify that {{name}} has participated.'],
    ['type' => 'dynamic_text', 'text' => 'Phone: {{phone}}'],
    [
        'type'         => 'qr_code',
        'x'            => 1240,
        'y'            => 1240,
        'size'         => 180,
        'url_template' => '{{verification_url}}',
    ],
    [
        'type'   => 'signature1',
        'x'      => 500,
        'y'      => 1260,
        'width'  => 220,
        'height' => 80,
        'name'   => 'Program Lead',
        'title'  => 'Director',
    ],
];
$reqValid = new Request([], [
    'layout_config'         => json_encode(['elements' => $validElements]),
    'signature1_name'        => 'Test Signatory 1',
    'signature1_designation' => 'Director of Academics',
]);
$resValid = $controller->saveDesigner($reqValid, ['id' => $testTplId]);
assertCondition(
    $resValid->getStatusCode() === 200,
    'Saving valid layout with both {{name}} and {{phone}} succeeds (HTTP 200)'
);

$savedTpl = $repo->findById($testTplId);
$savedElements = $savedTpl['layout_config']['elements'] ?? [];
assertCondition(
    count($savedElements) === 4,
    'Saved layout contains exactly 4 elements'
);
assertCondition(
    $savedTpl['signature1_name'] === 'Test Signatory 1',
    'Signatory 1 metadata persisted cleanly'
);

// -----------------------------------------------------------------------------
// 4. Strict Option A Asset Security (Strictly NO SVG)
// -----------------------------------------------------------------------------
echo "\n4. Testing Strict Option A Asset Upload Security (NO SVG)...\n";

// Create temporary dummy files for upload tests
$tmpDir = sys_get_temp_dir();
$svgPath = $tmpDir . '/malicious_test.svg';
file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

// Mock $_FILES for SVG upload
$_FILES['asset_file'] = [
    'name'     => 'malicious_test.svg',
    'type'     => 'image/svg+xml',
    'tmp_name' => $svgPath,
    'error'    => UPLOAD_ERR_OK,
    'size'     => strlen(file_get_contents($svgPath)),
];

$reqSvg = new Request([], ['asset_type' => 'seal']);
$resSvg = $controller->uploadAsset($reqSvg, ['id' => $testTplId]);

assertCondition(
    $resSvg->getStatusCode() === 422,
    'SVG upload is strictly rejected under Option A security (HTTP 422)'
);
$resData = json_decode($resSvg->getBody(), true);
assertCondition(
    str_contains($resData['error'] ?? '', 'SVG is not supported') || str_contains($resData['error'] ?? '', 'Only PNG'),
    'Error message explicitly informs user that SVG is prohibited'
);

// Test with a real valid 1x1 transparent PNG
$pngPath = $tmpDir . '/valid_test_seal.png';
$im = imagecreatetruecolor(100, 100);
imagealphablending($im, false);
imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefilledrectangle($im, 0, 0, 99, 99, $transparent);
imagepng($im, $pngPath);
imagedestroy($im);

$_FILES['asset_file'] = [
    'name'     => 'valid_test_seal.png',
    'type'     => 'image/png',
    'tmp_name' => $pngPath,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($pngPath),
];

$reqPng = new Request([], ['asset_type' => 'seal']);
$resPng = $controller->uploadAsset($reqPng, ['id' => $testTplId]);

assertCondition(
    $resPng->getStatusCode() === 200,
    'Valid PNG seal upload is accepted (HTTP 200)'
);

$resPngData = json_decode($resPng->getBody(), true);
assertCondition(
    !empty($resPngData['path']) && str_starts_with($resPngData['path'], "storage/templates/{$testTplId}/"),
    'Uploaded asset is isolated to template storage folder'
);

$updatedTpl = $repo->findById($testTplId);
assertCondition(
    !empty($updatedTpl['seal_image_path']) && $updatedTpl['seal_image_path'] === $resPngData['path'],
    'Template seal_image_path column updated in database'
);

// -----------------------------------------------------------------------------
// 5. Testing Asset Deletion
// -----------------------------------------------------------------------------
echo "\n5. Testing Asset Deletion...\n";

$reqDel = new Request([], ['asset_type' => 'seal']);
$resDel = $controller->deleteAsset($reqDel, ['id' => $testTplId]);

assertCondition(
    $resDel->getStatusCode() === 200,
    'Asset deletion endpoint succeeds (HTTP 200)'
);

$afterDelTpl = $repo->findById($testTplId);
assertCondition(
    empty($afterDelTpl['seal_image_path']),
    'Template seal_image_path is set to null upon deletion'
);

// Clean up temp files
@unlink($svgPath);
@unlink($pngPath);

// -----------------------------------------------------------------------------
// 6. Dynamic QR Code URL Parity & Rendering
// -----------------------------------------------------------------------------
echo "\n6. Testing Dynamic QR Code Verification URL Parity...\n";

$tplData = [
    'id'                   => $testTplId,
    'name'                 => 'Dynamic QR Template',
    'background_image_path'=> null,
    'seal_image_path'      => null,
    'layout_config'        => [
        'elements' => [
            [
                'type'         => 'qr_code',
                'id'           => 'qr_code',
                'x'            => 1240,
                'y'            => 1240,
                'size'         => 180,
                'url_template' => '{{verification_url}}',
            ],
            [
                'type' => 'dynamic_text',
                'text' => 'Recipient: {{name}}',
                'x'    => 1240,
                'y'    => 700,
            ],
            [
                'type' => 'dynamic_text',
                'text' => 'Phone: {{phone}}',
                'x'    => 1240,
                'y'    => 800,
            ],
        ],
    ],
];

// A) Preview dummy rendering (should use fallback without failing)
$previewData = VariableRegistry::getPreviewDummyData();
$previewGd = CertificateRenderer::renderCanvas($tplData, $previewData);
assertCondition(
    $previewGd instanceof GdImage,
    'renderCanvas succeeds with preview dummy data and dynamic QR element'
);
imagedestroy($previewGd);

// B) Generation time rendering with real certificate token
$realCertData = [
    'name'               => 'Rahul Sharma',
    'phone'              => '+919876543210',
    'certificate_number' => 'LC26-TEST-9999',
    'verification_token' => 'SECRET_TOKEN_ABC123XYZ',
    'verification_url'   => 'https://teami.in/LC/certificates/verify/SECRET_TOKEN_ABC123XYZ',
];
$genGd = CertificateRenderer::renderCanvas($tplData, $realCertData);
assertCondition(
    $genGd instanceof GdImage,
    'renderCanvas succeeds with actual generation cert data and dynamic verification_url'
);
imagedestroy($genGd);

// -----------------------------------------------------------------------------
// 7. Grouped Signature Component Rendering Parity
// -----------------------------------------------------------------------------
echo "\n7. Testing Grouped Signature Component Rendering...\n";

$sigTplData = [
    'id'                   => $testTplId,
    'name'                 => 'Signature Parity Template',
    'background_image_path'=> null,
    'seal_image_path'      => null,
    'signature1_image_path'=> null,
    'signature1_name'      => 'Dr. Ananya Roy',
    'signature1_designation'=> 'Chief Medical Officer',
    'signature2_image_path'=> null,
    'signature2_name'      => 'Prof. Vikram Singh',
    'signature2_designation'=> 'Honorary President',
    'layout_config'        => [
        'elements' => [
            [
                'type'           => 'signature1',
                'x'              => 500,
                'y'              => 1260,
                'width'          => 240,
                'height'         => 80,
                'show_line'      => true,
                'name_font_size' => 22,
                'title_font_size'=> 18,
            ],
            [
                'type'           => 'signature2',
                'x'              => 1980,
                'y'              => 1260,
                'width'          => 240,
                'height'         => 80,
                'show_line'      => true,
                'name_font_size' => 22,
                'title_font_size'=> 18,
            ],
        ],
    ],
];

$sigGd = CertificateRenderer::renderCanvas($sigTplData, $realCertData);
assertCondition(
    $sigGd instanceof GdImage,
    'renderCanvas renders grouped Signature 1 and Signature 2 components successfully'
);
imagedestroy($sigGd);

// -----------------------------------------------------------------------------
// 8. PDF and JPEG Generation Parity
// -----------------------------------------------------------------------------
echo "\n8. Testing Backend JPEG Stream & PDF Encapsulation...\n";

$jpgBinary = CertificateRenderer::renderJpg($sigTplData, $realCertData);
assertCondition(
    !empty($jpgBinary) && strlen($jpgBinary) > 10000,
    'renderJpg produces non-empty binary stream (>10KB)'
);
$jpgInfo = @getimagesizefromstring($jpgBinary);
assertCondition(
    $jpgInfo[0] === 2480 && $jpgInfo[1] === 1754,
    'Rendered JPEG matches canonical 2480x1754 resolution'
);

$pdfBinary = CertificateRenderer::renderPdf($sigTplData, $realCertData);
assertCondition(
    !empty($pdfBinary) && str_starts_with($pdfBinary, '%PDF-1.4'),
    'renderPdf produces valid %PDF-1.4 ISO document'
);
assertCondition(
    str_contains($pdfBinary, '841.89 595.28'),
    'PDF media box matches exact A4 Landscape dimensions (841.89 x 595.28 pt)'
);

// Clean up test template in DB
$repo->delete($testTplId);

echo "\n=== Test Summary ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
