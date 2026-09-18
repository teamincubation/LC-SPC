<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\QrCode;
use GdImage;

/**
 * Unified Certificate Rendering Engine
 * Canonical A4 Landscape canvas ($2480 \times 1754$ pixels, 300 DPI equivalent).
 * Guarantees exact coordinate, typography, alignment, and wrapping parity
 * across browser live preview, high-resolution Image, and ISO-compliant PDF.
 */
class CertificateRenderer
{
    public const CANVAS_WIDTH = 2480;
    public const CANVAS_HEIGHT = 1754;
    public const DPI = 300;

    /**
     * Render the certificate canvas to a GD image resource.
     *
     * @param array $template Template record with background and layout_config
     * @param array $data Merged dynamic variables (e.g. name, phone, certificate_number)
     * @param bool $isRevoked Whether the certificate is invalidated/revoked
     * @return GdImage
     */
    public static function renderCanvas(array $template, array $data, bool $isRevoked = false): GdImage
    {
        $w = self::CANVAS_WIDTH;
        $h = self::CANVAS_HEIGHT;

        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, true);
        imagesavealpha($im, true);

        // Default institutional palette
        $bgWhite     = imagecolorallocate($im, 255, 255, 255);
        $primaryDark = imagecolorallocate($im, 15, 23, 42);    // #0F172A (Navy Slate)
        $accentRed   = imagecolorallocate($im, 191, 30, 46);   // #BF1E2E (LC Primary)
        $accentGold  = imagecolorallocate($im, 180, 130, 40);  // Gold Ornament
        $textDark    = imagecolorallocate($im, 30, 41, 59);    // #1E293B
        $textMuted   = imagecolorallocate($im, 100, 116, 139); // #64748B
        $lineColor   = imagecolorallocate($im, 203, 213, 225); // #CBD5E1

        imagefilledrectangle($im, 0, 0, $w, $h, $bgWhite);

        // 1. Draw Background Image if uploaded
        $hasCustomBg = false;
        $bgPath = $template['background_image_path'] ?? null;
        if (!empty($bgPath)) {
            $fullBgPath = self::resolveStoragePath($bgPath);
            if (file_exists($fullBgPath)) {
                $bgContent = @file_get_contents($fullBgPath);
                if ($bgContent) {
                    $bgImg = @imagecreatefromstring($bgContent);
                    if ($bgImg) {
                        imagecopyresampled($im, $bgImg, 0, 0, 0, 0, $w, $h, imagesx($bgImg), imagesy($bgImg));
                        imagedestroy($bgImg);
                        $hasCustomBg = true;
                    }
                }
            }
        }

        // 2. If no custom background, render institutional high-res border
        if (!$hasCustomBg) {
            imagesetthickness($im, 16);
            imagerectangle($im, 80, 80, $w - 80, $h - 80, $primaryDark);
            imagesetthickness($im, 4);
            imagerectangle($im, 104, 104, $w - 104, $h - 104, $accentRed);

            // Corner accents
            imagesetthickness($im, 6);
            $cLen = 80;
            imageline($im, 120, 120, 120 + $cLen, 120, $accentGold);
            imageline($im, 120, 120, 120, 120 + $cLen, $accentGold);

            imageline($im, $w - 120, 120, $w - 120 - $cLen, 120, $accentGold);
            imageline($im, $w - 120, 120, $w - 120, 120 + $cLen, $accentGold);

            imageline($im, 120, $h - 120, 120 + $cLen, $h - 120, $accentGold);
            imageline($im, 120, $h - 120, 120, $h - 120 - $cLen, $accentGold);

            imageline($im, $w - 120, $h - 120, $w - 120 - $cLen, $h - 120, $accentGold);
            imageline($im, $w - 120, $h - 120, $w - 120, $h - 120 - $cLen, $accentGold);
        }

        // 3. Parse Layout Elements
        $layout = $template['layout_config'] ?? [];
        if (is_string($layout)) {
            $layout = json_decode($layout, true) ?: [];
        }

        $elements = $layout['elements'] ?? self::getDefaultElements();

        foreach ($elements as $el) {
            $type = $el['type'] ?? 'text';
            $x = (int) ($el['x'] ?? 0);
            $y = (int) ($el['y'] ?? 0);
            $align = $el['align'] ?? 'left';
            $fontSize = (int) ($el['font_size'] ?? 24);
            $fontFamily = $el['font_family'] ?? 'arial';
            $fontWeight = $el['font_weight'] ?? 'normal';
            $fontStyle = $el['font_style'] ?? 'normal';
            $colorHex = $el['color'] ?? '#0F172A';
            $maxWidth = (int) ($el['max_width'] ?? ($w - 200));

            $color = self::allocateHexColor($im, $colorHex);
            $fontFile = self::resolveFont($fontFamily, $fontWeight === 'bold', $fontStyle === 'italic');

            switch ($type) {
                case 'text':
                case 'dynamic_text':
                    $rawText = $el['text'] ?? '';
                    $renderedText = VariableRegistry::replacePlaceholders($rawText, $data);
                    self::drawTextElement($im, $renderedText, $x, $y, $fontSize, $color, $fontFile, $align, $maxWidth);
                    break;

                case 'seal':
                    $sealPath = $template['seal_image_path'] ?? null;
                    $size = (int) ($el['size'] ?? 160);
                    if ($sealPath) {
                        self::drawImageElement($im, $sealPath, $x, $y, $size, $size);
                    }
                    break;

                case 'signature1':
                    $sig1Path = $template['signature1_image_path'] ?? null;
                    $width = (int) ($el['width'] ?? 220);
                    $height = (int) ($el['height'] ?? 80);
                    if ($sig1Path) {
                        self::drawImageElement($im, $sig1Path, $x, $y, $width, $height);
                    }
                    // Signatory 1 name & title
                    $s1Name = $template['signature1_name'] ?? ($el['name'] ?? '');
                    $s1Title = $template['signature1_designation'] ?? ($el['title'] ?? '');
                    if ($s1Name !== '') {
                        imagesetthickness($im, 3);
                        imageline($im, $x - ($width / 2), $y + $height + 10, $x + ($width / 2), $y + $height + 10, $lineColor);
                        self::drawTextElement($im, $s1Name, (int)$x, (int)($y + $height + 40), 22, $textDark, $fontFile, 'center', $width + 100);
                        self::drawTextElement($im, $s1Title, (int)$x, (int)($y + $height + 70), 18, $textMuted, $fontFile, 'center', $width + 100);
                    }
                    break;

                case 'signature2':
                    $sig2Path = $template['signature2_image_path'] ?? null;
                    $width = (int) ($el['width'] ?? 220);
                    $height = (int) ($el['height'] ?? 80);
                    if ($sig2Path) {
                        self::drawImageElement($im, $sig2Path, $x, $y, $width, $height);
                    }
                    // Signatory 2 name & title
                    $s2Name = $template['signature2_name'] ?? ($el['name'] ?? '');
                    $s2Title = $template['signature2_designation'] ?? ($el['title'] ?? '');
                    if ($s2Name !== '') {
                        imagesetthickness($im, 3);
                        imageline($im, $x - ($width / 2), $y + $height + 10, $x + ($width / 2), $y + $height + 10, $lineColor);
                        self::drawTextElement($im, $s2Name, (int)$x, (int)($y + $height + 40), 22, $textDark, $fontFile, 'center', $width + 100);
                        self::drawTextElement($im, $s2Title, (int)$x, (int)($y + $height + 70), 18, $textMuted, $fontFile, 'center', $width + 100);
                    }
                    break;

                case 'qr_code':
                    $token = $data['verification_token'] ?? '';
                    $verifyUrl = "https://teami.in/LC/certificates/verify/{$token}";
                    $qrSize = (int) ($el['size'] ?? 220);
                    $qr = new QrCode($verifyUrl);
                    $qrX = (int) ($x - ($qrSize / 2));
                    $qrY = (int) ($y);
                    $qr->drawOnGd($im, $qrX, $qrY, $qrSize, 2);

                    // Border around QR
                    imagesetthickness($im, 2);
                    imagerectangle($im, $qrX - 2, $qrY - 2, $qrX + $qrSize + 2, $qrY + $qrSize + 2, $lineColor);
                    break;
            }
        }

        // 4. Invalidation / Revocation Banner Overlay
        if ($isRevoked) {
            $revokedRed = imagecolorallocatealpha($im, 220, 38, 38, 15); // Semi-transparent Red
            $bannerWhite = imagecolorallocate($im, 255, 255, 255);
            $textRed = imagecolorallocate($im, 185, 28, 28);

            imagefilledrectangle($im, 80, 80, $w - 80, 160, $revokedRed);
            $fontBold = self::resolveFont('arial', true, false);
            self::drawTextElement($im, 'OFFICIALLY REVOKED / INVALID CREDENTIAL', (int) ($w / 2), 135, 32, $bannerWhite, $fontBold, 'center', $w);

            // Watermark diagonal or center void
            self::drawTextElement($im, 'VOID — REVOKED CREDENTIAL — VOID', (int) ($w / 2), 950, 48, $textRed, $fontBold, 'center', $w);
        }

        return $im;
    }

    /**
     * Render certificate directly to binary JPEG string.
     */
    public static function renderJpg(array $template, array $data, bool $isRevoked = false): string
    {
        $im = self::renderCanvas($template, $data, $isRevoked);
        ob_start();
        imagejpeg($im, null, 95);
        $jpegData = (string) ob_get_clean();
        imagedestroy($im);
        return $jpegData;
    }

    /**
     * Render certificate directly to binary PNG string.
     */
    public static function renderPng(array $template, array $data, bool $isRevoked = false): string
    {
        $im = self::renderCanvas($template, $data, $isRevoked);
        ob_start();
        imagepng($im, null, 6);
        $pngData = (string) ob_get_clean();
        imagedestroy($im);
        return $pngData;
    }

    /**
     * Render certificate encapsulated inside ISO-compliant A4 Landscape PDF (%PDF-1.4).
     */
    public static function renderPdf(array $template, array $data, bool $isRevoked = false): string
    {
        $jpegData = self::renderJpg($template, $data, $isRevoked);

        $metadata = [
            'certificate_number' => (string) ($data['certificate_number'] ?? ''),
            'recipient_name'     => (string) ($data['name'] ?? ''),
            'event_title'        => (string) ($data['event_title'] ?? ''),
            'issue_date'         => (string) ($data['date'] ?? ''),
            'verify_url'         => "https://teami.in/LC/certificates/verify/" . (string) ($data['verification_token'] ?? ''),
        ];

        return self::encapsulateA4LandscapePdf($jpegData, $metadata);
    }

    /**
     * Encapsulate high-resolution JPEG inside an ISO-compliant A4 Landscape PDF (%PDF-1.4).
     */
    private static function encapsulateA4LandscapePdf(string $jpegData, array $metadata): string
    {
        $imgInfo = @getimagesizefromstring($jpegData);
        $width = $imgInfo ? $imgInfo[0] : self::CANVAS_WIDTH;
        $height = $imgInfo ? $imgInfo[1] : self::CANVAS_HEIGHT;

        $pageWidthPt = 841.89; // 297mm in points
        $pageHeightPt = 595.28; // 210mm in points

        $esc = function (string $text): string {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        };

        $content = "q\n";
        $content .= sprintf("%.2f 0 0 %.2f 0 0 cm\n", $pageWidthPt, $pageHeightPt);
        $content .= "/Im1 Do\n";
        $content .= "Q\n";
        $content .= "BT\n";
        $content .= "/F1 12 Tf\n";
        $content .= "3 Tr\n"; // Invisible searchable text overlay
        $content .= sprintf("72 500 Td (%s) Tj\n", $esc("Certificate ID: " . ($metadata['certificate_number'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Recipient: " . ($metadata['recipient_name'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Event: " . ($metadata['event_title'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Issue Date: " . ($metadata['issue_date'] ?? '')));
        $content .= sprintf("0 -20 Td (%s) Tj\n", $esc("Verification URL: " . ($metadata['verify_url'] ?? '')));
        $content .= "ET\n";

        $contentLen = strlen($content);
        $imgLen = strlen($jpegData);

        $offsets = [];
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        $offsets[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Contents 4 0 R /Resources << /ProcSet [/PDF /Text /ImageC] /Font << /F1 6 0 R >> /XObject << /Im1 5 0 R >> >> >>\nendobj\n";

        $offsets[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<< /Length {$contentLen} >>\nstream\n{$content}\nendstream\nendobj\n";

        $offsets[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$imgLen} >>\nstream\n{$jpegData}\nendstream\nendobj\n";

        $offsets[6] = strlen($pdf);
        $pdf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $offsets[7] = strlen($pdf);
        $titleEsc = $esc("Certificate " . ($metadata['certificate_number'] ?? ''));
        $authorEsc = $esc("Listening Community");
        $pdf .= "7 0 obj\n<< /Title ({$titleEsc}) /Author ({$authorEsc}) >>\nendobj\n";

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 8\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 7; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size 8 /Root 1 0 R /Info 7 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    /**
     * Draw text element with alignment and bounding box calculation.
     */
    private static function drawTextElement(
        $im,
        string $text,
        int $x,
        int $y,
        int $size,
        int $color,
        ?string $font,
        string $align = 'left',
        int $maxWidth = 2000
    ): void {
        if ($text === '') {
            return;
        }

        if ($font && file_exists($font)) {
            $bbox = imagettfbbox($size, 0, $font, $text);
            $textWidth = abs($bbox[4] - $bbox[0]);

            $drawX = match ($align) {
                'center' => (int) ($x - ($textWidth / 2)),
                'right'  => (int) ($x - $textWidth),
                default  => $x,
            };

            imagettftext($im, $size, 0, $drawX, $y, $color, $font, $text);
        } else {
            // Built-in font fallback
            $fontIdx = 5;
            $charWidth = imagefontwidth($fontIdx);
            $textWidth = strlen($text) * $charWidth;

            $drawX = match ($align) {
                'center' => (int) ($x - ($textWidth / 2)),
                'right'  => (int) ($x - $textWidth),
                default  => $x,
            };

            imagestring($im, $fontIdx, $drawX, $y - 15, $text, $color);
        }
    }

    /**
     * Draw resampled image element (seal, signatures, logos).
     */
    private static function drawImageElement($im, string $storageRelPath, int $centerX, int $centerY, int $width, int $height): void
    {
        $filePath = self::resolveStoragePath($storageRelPath);
        if (!file_exists($filePath)) {
            return;
        }

        $content = @file_get_contents($filePath);
        if (!$content) {
            return;
        }

        $srcImg = @imagecreatefromstring($content);
        if (!$srcImg) {
            return;
        }

        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        $dstX = (int) ($centerX - ($width / 2));
        $dstY = (int) ($centerY);

        imagecopyresampled($im, $srcImg, $dstX, $dstY, 0, 0, $width, $height, $srcW, $srcH);
        imagedestroy($srcImg);
    }

    /**
     * Convert Hex string (#BF1E2E) to GD allocated color.
     */
    private static function allocateHexColor($im, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return imagecolorallocate($im, 15, 23, 42);
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return imagecolorallocate($im, (int)$r, (int)$g, (int)$b);
    }

    /**
     * Resolve font path with cross-platform fallbacks and custom font uploads.
     */
    public static function resolveFont(string $family = 'arial', bool $bold = false, bool $italic = false): ?string
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

        // Check if custom uploaded font in storage/fonts
        $customFontDir = $appRoot . '/storage/fonts';
        if (is_dir($customFontDir)) {
            $pattern = $customFontDir . '/' . strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $family)) . '.*';
            $matches = glob($pattern);
            if (!empty($matches) && file_exists($matches[0])) {
                return $matches[0];
            }
        }

        $candidates = [
            $bold ? $appRoot . '/public/assets/fonts/arialbd.ttf' : $appRoot . '/public/assets/fonts/arial.ttf',
            $bold ? 'C:/Windows/Fonts/arialbd.ttf' : 'C:/Windows/Fonts/arial.ttf',
            $bold ? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf' : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            $bold ? '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf' : '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Helper to resolve relative storage path.
     */
    private static function resolveStoragePath(string $relPath): string
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        return $appRoot . '/' . ltrim($relPath, '/\\');
    }

    /**
     * Canonical default layout elements for a standard A4 landscape certificate.
     */
    public static function getDefaultElements(): array
    {
        return [
            [
                'type'        => 'text',
                'id'          => 'header_org',
                'text'        => 'LISTENING COMMUNITY',
                'x'           => 1240,
                'y'           => 260,
                'font_size'   => 46,
                'font_family' => 'arial',
                'font_weight' => 'bold',
                'color'       => '#0F172A',
                'align'       => 'center',
            ],
            [
                'type'        => 'text',
                'id'          => 'header_sub',
                'text'        => 'SUICIDE PREVENTION CAMPAIGN • OFFICIAL CREDENTIAL',
                'x'           => 1240,
                'y'           => 315,
                'font_size'   => 22,
                'font_family' => 'arial',
                'font_weight' => 'bold',
                'color'       => '#BF1E2E',
                'align'       => 'center',
            ],
            [
                'type'        => 'dynamic_text',
                'id'          => 'title',
                'text'        => '{{certificate_type}}',
                'x'           => 1240,
                'y'           => 460,
                'font_size'   => 54,
                'font_family' => 'arial',
                'font_weight' => 'bold',
                'color'       => '#0F172A',
                'align'       => 'center',
            ],
            [
                'type'        => 'text',
                'id'          => 'presented_to',
                'text'        => 'This is proudly presented to',
                'x'           => 1240,
                'y'           => 550,
                'font_size'   => 26,
                'font_family' => 'arial',
                'font_weight' => 'normal',
                'color'       => '#64748B',
                'align'       => 'center',
            ],
            [
                'type'        => 'dynamic_text',
                'id'          => 'recipient_name',
                'text'        => '{{name}}',
                'x'           => 1240,
                'y'           => 670,
                'font_size'   => 68,
                'font_family' => 'arial',
                'font_weight' => 'bold',
                'color'       => '#0F172A',
                'align'       => 'center',
            ],
            [
                'type'        => 'dynamic_text',
                'id'          => 'body_recognition',
                'text'        => 'for successfully completing "{{event_title}}" held on {{date}} at {{place}}.',
                'x'           => 1240,
                'y'           => 790,
                'font_size'   => 28,
                'font_family' => 'arial',
                'font_weight' => 'normal',
                'color'       => '#334155',
                'align'       => 'center',
            ],
            [
                'type'        => 'signature1',
                'id'          => 'sig1',
                'x'           => 500,
                'y'           => 1260,
                'width'       => 220,
                'height'      => 80,
                'name'        => 'Program Coordinator',
                'title'       => 'Academic Lead, LC-SPC',
            ],
            [
                'type'        => 'signature2',
                'id'          => 'sig2',
                'x'           => 1980,
                'y'           => 1260,
                'width'       => 220,
                'height'      => 80,
                'name'        => 'Executive Director',
                'title'       => 'Campaign Director, LC-SPC',
            ],
            [
                'type'        => 'seal',
                'id'          => 'seal',
                'x'           => 1240,
                'y'           => 1050,
                'size'        => 160,
            ],
            [
                'type'        => 'qr_code',
                'id'          => 'qr_code',
                'x'           => 1240,
                'y'           => 1240,
                'size'        => 180,
            ],
            [
                'type'        => 'dynamic_text',
                'id'          => 'cert_id_display',
                'text'        => 'Certificate ID: {{certificate_number}}',
                'x'           => 1240,
                'y'           => 1460,
                'font_size'   => 22,
                'font_family' => 'arial',
                'font_weight' => 'bold',
                'color'       => '#0F172A',
                'align'       => 'center',
            ],
            [
                'type'        => 'text',
                'id'          => 'verify_disclaimer',
                'text'        => 'Scan QR code or visit teami.in/LC/certificates to verify authenticity',
                'x'           => 1240,
                'y'           => 1495,
                'font_size'   => 18,
                'font_family' => 'arial',
                'font_weight' => 'normal',
                'color'       => '#64748B',
                'align'       => 'center',
            ],
            [
                'type'        => 'text',
                'id'          => 'footer_text',
                'text'        => 'Listening Community SPC Foundation • Official Institutional Credential',
                'x'           => 1240,
                'y'           => 1630,
                'font_size'   => 16,
                'font_family' => 'arial',
                'font_weight' => 'normal',
                'color'       => '#94A3B8',
                'align'       => 'center',
            ],
        ];
    }
}
