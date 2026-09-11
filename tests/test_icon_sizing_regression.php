<?php

declare(strict_types=1);

/**
 * Dedicated Regression Test: SVG Icon Sizing & Component Hardening
 * Ensures vector SVG icons render with deterministic intrinsic attributes,
 * component-scoped sizing classes, and never expand to parent/viewport dimensions.
 */

require_once __DIR__ . '/../app/Core/Icon.php';
require_once __DIR__ . '/../app/Core/helpers.php';

use App\Core\Icon;

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

echo "=== LC-SPC SVG Icon Sizing & Regression Verification Suite ===\n\n";

// -----------------------------------------------------------------------------
// 1. Testing Default Icon Rendering Dimensions & Attributes
// -----------------------------------------------------------------------------
echo "1. Testing Default Intrinsic Sizing Attributes...\n";

$defaultHtml = Icon::render('target');
assertCondition(
    str_contains($defaultHtml, 'width="20"'),
    'Default Icon::render() produces explicit width="20"',
    $defaultHtml
);
assertCondition(
    str_contains($defaultHtml, 'height="20"'),
    'Default Icon::render() produces explicit height="20"',
    $defaultHtml
);
assertCondition(
    str_contains($defaultHtml, 'viewBox="0 0 24 24"'),
    'Icon retains standard 24x24 viewBox',
    $defaultHtml
);
assertCondition(
    str_contains($defaultHtml, 'data-icon="target"'),
    'Icon generates data-icon attribute for component-scoped targeting',
    $defaultHtml
);
assertCondition(
    str_contains($defaultHtml, 'focusable="false"'),
    'Icon includes focusable="false" for SVG accessibility compliance',
    $defaultHtml
);
assertCondition(
    str_contains($defaultHtml, 'class="svg-icon svg-icon-target svg-icon-md"'),
    'Default icon includes base, key-scoped, and default md size classes',
    $defaultHtml
);

// -----------------------------------------------------------------------------
// 2. Testing Size Shorthand & Class Parsing
// -----------------------------------------------------------------------------
echo "\n2. Testing Size Shorthand & Class Token Resolution...\n";

$xsHtml = Icon::render('clock', ['size' => 'xs']);
assertCondition(
    str_contains($xsHtml, 'width="14"') && str_contains($xsHtml, 'height="14"') && str_contains($xsHtml, 'svg-icon-xs'),
    'size="xs" resolves to 14px width/height and svg-icon-xs class',
    $xsHtml
);

$smHtml = Icon::render('plus', ['class' => 'svg-icon-sm']);
assertCondition(
    str_contains($smHtml, 'width="16"') && str_contains($smHtml, 'height="16"') && str_contains($smHtml, 'svg-icon-sm'),
    'Class token "svg-icon-sm" automatically sets width="16" and height="16"',
    $smHtml
);

$lgHtml = Icon::render('shield', ['size' => 'lg']);
assertCondition(
    str_contains($lgHtml, 'width="24"') && str_contains($lgHtml, 'height="24"') && str_contains($lgHtml, 'svg-icon-lg'),
    'size="lg" resolves to 24px width/height and svg-icon-lg class',
    $lgHtml
);

$xlHtml = Icon::render('award', ['size' => 'xl']);
assertCondition(
    str_contains($xlHtml, 'width="32"') && str_contains($xlHtml, 'height="32"') && str_contains($xlHtml, 'svg-icon-xl'),
    'size="xl" resolves to 32px width/height and svg-icon-xl class',
    $xlHtml
);

$numericHtml = Icon::render('edit', ['size' => 18]);
assertCondition(
    str_contains($numericHtml, 'width="18"') && str_contains($numericHtml, 'height="18"'),
    'Numeric size=18 produces width="18" height="18"',
    $numericHtml
);

$explicitHtml = Icon::render('home', ['width' => '14', 'height' => '14']);
assertCondition(
    str_contains($explicitHtml, 'width="14"') && str_contains($explicitHtml, 'height="14"') && str_contains($explicitHtml, 'svg-icon-xs'),
    'Explicit width="14" height="14" resolves correctly with mapped size class',
    $explicitHtml
);

$logoutHtml = Icon::render('log-out');
assertCondition(
    str_contains($logoutHtml, 'width="20"') && str_contains($logoutHtml, 'height="20"'),
    'Logout icon renders with deterministic 20px intrinsic dimensions',
    $logoutHtml
);

// -----------------------------------------------------------------------------
// 3. Testing CSS Reset & Stylesheet Invariants
// -----------------------------------------------------------------------------
echo "\n3. Testing Stylesheet Invariants in public/assets/css/app.css...\n";

$cssPath = __DIR__ . '/../public/assets/css/app.css';
$cssContent = file_get_contents($cssPath);

assertCondition(
    $cssContent !== false && strlen($cssContent) > 0,
    'public/assets/css/app.css is accessible and non-empty'
);

// Verify 'svg' was removed from the global img reset
$hasSvgInGlobalReset = preg_match('/img\s*,\s*svg\s*,/i', $cssContent) === 1;
assertCondition(
    !$hasSvgInGlobalReset,
    'svg tag is NOT present in global img, video reset rule (prevents 100% width blowout)',
    'Found "img, svg," in app.css'
);

// Verify .svg-icon base rules
assertCondition(
    str_contains($cssContent, '.svg-icon,') && str_contains($cssContent, 'max-width: 1.25rem;') && str_contains($cssContent, 'max-height: 1.25rem;'),
    '.svg-icon base rule defines strict max-width and max-height constraints'
);

// Verify flex: 0 0 auto containment
assertCondition(
    str_contains($cssContent, 'flex: 0 0 auto;') && str_contains($cssContent, 'flex-shrink: 0;'),
    'flex: 0 0 auto and flex-shrink: 0 applied to icon classes'
);

// Verify metric card icon containment (20-24px spec)
assertCondition(
    preg_match('/\.metric-card-icon\s+svg[^\{]*\{[^}]*width:\s*22px/i', $cssContent) === 1,
    'Metric card icons constrained to 22px (within 20-24px spec)',
    'Expected .metric-card-icon svg { width: 22px; }'
);

// Verify logout button icon containment (20-22px spec)
assertCondition(
    preg_match('/\.btn-logout\s+svg[^\{]*\{[^}]*width:\s*20px/i', $cssContent) === 1,
    'Logout button icon constrained to 20px (within 20-22px spec)',
    'Expected .btn-logout svg { width: 20px; }'
);

// Verify navigation icon containment (20-22px spec)
assertCondition(
    preg_match('/\.admin-nav-icon\s+svg[^\{]*\{[^}]*width:\s*20px/i', $cssContent) === 1,
    'Navigation icons constrained to 20px (within 20-22px spec)',
    'Expected .admin-nav-icon svg { width: 20px; }'
);

// Verify button icons containment (16-18px spec)
assertCondition(
    preg_match('/\.btn\s+svg[^\{]*\{[^}]*width:\s*16px/i', $cssContent) === 1,
    'Button icons constrained to 16px (within 16-18px spec)',
    'Expected .btn svg { width: 16px; }'
);

// Verify table actions icon containment (16-18px spec)
assertCondition(
    preg_match('/\.table-actions\s+svg[^\{]*\{[^}]*width:\s*16px/i', $cssContent) === 1,
    'Table action icons constrained to 16px (within 16-18px spec)',
    'Expected .table-actions svg { width: 16px; }'
);

// -----------------------------------------------------------------------------
// 4. Testing Layout Cache-Busting
// -----------------------------------------------------------------------------
echo "\n4. Testing Layout Asset Cache-Busting...\n";

$layoutPath = __DIR__ . '/../app/Views/layouts/admin.php';
$layoutContent = file_get_contents($layoutPath);

assertCondition(
    str_contains($layoutContent, 'css/app.css\')) ?>?v='),
    'admin.php layout uses cache-busting query parameter for app.css',
    $layoutContent
);

assertCondition(
    str_contains($layoutContent, 'js/app.js\')) ?>?v='),
    'admin.php layout uses cache-busting query parameter for app.js',
    $layoutContent
);

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n----------------------------------------------------\n";
echo "Tests Passed: {$passed} / " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo "FAILED: {$failed} tests did not pass.\n";
    exit(1);
}

echo "ALL SVG ICON SIZING REGRESSION TESTS PASSED SUCCESSFULLY.\n";
exit(0);
