<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Pure PHP QR Code Generator (Model 2, Byte Mode, ISO/IEC 18004)
 * Generates vector SVG and GD bitmap QR codes without external dependencies.
 * Spec-compliant, offline, CSP-compliant, zero external API calls.
 */
class QrCode
{
    // Error Correction Levels
    public const EC_L = 1; // ~7% recovery
    public const EC_M = 0; // ~15% recovery (default)
    public const EC_Q = 3; // ~25% recovery
    public const EC_H = 2; // ~30% recovery

    // Galois Field GF(256) tables
    private static array $exp = [];
    private static array $log = [];
    private static bool $gfInit = false;

    // Capacity table for Byte mode at EC_M [version => [capacity_bytes, total_data_codewords, ec_codewords_per_block, num_blocks_g1, data_per_block_g1, num_blocks_g2, data_per_block_g2]]
    private static array $specM = [
        1 => [14, 19, 10, 1, 19, 0, 0],
        2 => [26, 34, 16, 1, 34, 0, 0],
        3 => [42, 55, 26, 1, 55, 0, 0],
        4 => [62, 80, 18, 2, 40, 0, 0],
        5 => [84, 108, 24, 2, 54, 0, 0],
        6 => [106, 136, 16, 4, 34, 0, 0],
        7 => [122, 156, 18, 4, 39, 0, 0],
        8 => [152, 194, 22, 2, 48, 2, 49],
        9 => [180, 232, 22, 3, 46, 2, 47],
        10 => [213, 274, 26, 4, 49, 2, 50],
    ];

    // Alignment pattern positions per version
    private static array $alignmentPatternPositions = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    private string $text;
    private int $version;
    private int $size;
    private array $modules = [];

    public function __construct(string $text)
    {
        $this->text = $text;
        self::initGf();
        $this->generate();
    }

    /**
     * Static helper to quickly render SVG string.
     */
    public static function svg(string $text, int $size = 200, int $margin = 2, string $fgColor = '#000000', string $bgColor = '#ffffff'): string
    {
        $qr = new self($text);
        return $qr->renderSvg($size, $margin, $fgColor, $bgColor);
    }

    /**
     * Static helper to quickly render Data URI for <img> tags.
     */
    public static function dataUri(string $text, int $size = 200, int $margin = 2): string
    {
        $svg = self::svg($text, $size, $margin);
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    /**
     * Render as standalone SVG markup.
     */
    public function renderSvg(int $pixelSize = 200, int $margin = 2, string $fgColor = '#000000', string $bgColor = '#ffffff'): string
    {
        $count = count($this->modules);
        $totalSize = $count + ($margin * 2);

        $path = '';
        for ($y = 0; $y < $count; $y++) {
            for ($x = 0; $x < $count; $x++) {
                if ($this->modules[$y][$x]) {
                    $px = $x + $margin;
                    $py = $y + $margin;
                    $path .= "M{$px},{$py}h1v1h-1z ";
                }
            }
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalSize . ' ' . $totalSize . '" '
             . 'width="' . $pixelSize . '" height="' . $pixelSize . '" shape-rendering="crispEdges">' . "\n";
        if ($bgColor !== 'transparent' && !empty($bgColor)) {
            $svg .= '  <rect width="' . $totalSize . '" height="' . $totalSize . '" fill="' . htmlspecialchars($bgColor) . '"/>' . "\n";
        }
        $svg .= '  <path d="' . rtrim($path) . '" fill="' . htmlspecialchars($fgColor) . '"/>' . "\n";
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Draw QR code directly onto an existing GD image resource.
     */
    public function drawOnGd($image, int $dstX, int $dstY, int $targetSize, int $margin = 2, int $fgColorRgb = 0x000000, int $bgColorRgb = 0xFFFFFF): void
    {
        $count = count($this->modules);
        $totalCells = $count + ($margin * 2);
        $cellSize = $targetSize / $totalCells;

        $fg = imagecolorallocate($image, ($fgColorRgb >> 16) & 0xFF, ($fgColorRgb >> 8) & 0xFF, $fgColorRgb & 0xFF);
        $bg = imagecolorallocate($image, ($bgColorRgb >> 16) & 0xFF, ($bgColorRgb >> 8) & 0xFF, $bgColorRgb & 0xFF);

        // Fill background
        imagefilledrectangle($image, $dstX, $dstY, $dstX + $targetSize - 1, $dstY + $targetSize - 1, $bg);

        // Fill dark modules
        for ($y = 0; $y < $count; $y++) {
            for ($x = 0; $x < $count; $x++) {
                if ($this->modules[$y][$x]) {
                    $x1 = (int) ($dstX + (($x + $margin) * $cellSize));
                    $y1 = (int) ($dstY + (($y + $margin) * $cellSize));
                    $x2 = (int) ($dstX + (($x + $margin + 1) * $cellSize) - 1);
                    $y2 = (int) ($dstY + (($y + $margin + 1) * $cellSize) - 1);
                    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $fg);
                }
            }
        }
    }

    /**
     * Render as a fresh GD image resource.
     */
    public function renderGd(int $size = 200, int $margin = 2)
    {
        $im = imagecreatetruecolor($size, $size);
        $this->drawOnGd($im, 0, 0, $size, $margin);
        return $im;
    }

    /**
     * Get the 2D boolean module matrix.
     */
    public function getMatrix(): array
    {
        return $this->modules;
    }

    /**
     * Initialize Galois Field GF(256) tables.
     */
    private static function initGf(): void
    {
        if (self::$gfInit) {
            return;
        }

        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);

        $val = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $val;
            self::$log[$val] = $i;
            $val <<= 1;
            if ($val & 0x100) {
                $val ^= 0x11D; // Primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }

        self::$gfInit = true;
    }

    private static function gfMul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    /**
     * Generate complete QR code matrix.
     */
    private function generate(): void
    {
        $dataBytes = array_values(unpack('C*', $this->text));
        $len = count($dataBytes);

        // Determine smallest version capable of holding data at EC_M
        $this->version = 0;
        foreach (self::$specM as $ver => $spec) {
            if ($len <= $spec[0]) {
                $this->version = $ver;
                break;
            }
        }

        if ($this->version === 0) {
            throw new InvalidArgumentException("Text too long for QR Code (max supported: 213 bytes at Version 10, got {$len} bytes)");
        }

        $spec = self::$specM[$this->version];
        $totalDataCodewords = $spec[1];
        $ecCodewordsPerBlock = $spec[2];
        $numBlocksG1 = $spec[3];
        $dataPerBlockG1 = $spec[4];
        $numBlocksG2 = $spec[5];
        $dataPerBlockG2 = $spec[6];

        // 1. Build bitstream: Mode (0100 for Byte) + Count (8 bits for v1-9, 16 bits for v10+) + Data
        $bits = '';
        $bits .= '0100'; // Byte mode indicator
        $charCountBits = ($this->version >= 10) ? 16 : 8;
        $bits .= str_pad(decbin($len), $charCountBits, '0', STR_PAD_LEFT);
        foreach ($dataBytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        // Add terminator (up to 4 zeroes)
        $totalDataBits = $totalDataCodewords * 8;
        $diff = $totalDataBits - strlen($bits);
        if ($diff > 0) {
            $bits .= str_repeat('0', min(4, $diff));
        }

        // Pad to byte boundary
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Pad with alternating 0xEC and 0x11
        $pad = ['11101100', '00010001'];
        $pIdx = 0;
        while (strlen($bits) < $totalDataBits) {
            $bits .= $pad[$pIdx % 2];
            $pIdx++;
        }

        // Convert bits to byte codewords
        $dataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }

        // 2. Divide into blocks and compute Reed-Solomon EC codewords
        $blocks = [];
        $cwOffset = 0;
        for ($b = 0; $b < $numBlocksG1; $b++) {
            $slice = array_slice($dataCodewords, $cwOffset, $dataPerBlockG1);
            $cwOffset += $dataPerBlockG1;
            $ec = $this->calculateEc($slice, $ecCodewordsPerBlock);
            $blocks[] = ['data' => $slice, 'ec' => $ec];
        }
        for ($b = 0; $b < $numBlocksG2; $b++) {
            $slice = array_slice($dataCodewords, $cwOffset, $dataPerBlockG2);
            $cwOffset += $dataPerBlockG2;
            $ec = $this->calculateEc($slice, $ecCodewordsPerBlock);
            $blocks[] = ['data' => $slice, 'ec' => $ec];
        }

        // 3. Interleave data codewords then EC codewords
        $finalCodewords = [];
        $maxDataLen = max($dataPerBlockG1, $dataPerBlockG2);
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocks as $blk) {
                if ($i < count($blk['data'])) {
                    $finalCodewords[] = $blk['data'][$i];
                }
            }
        }
        for ($i = 0; $i < $ecCodewordsPerBlock; $i++) {
            foreach ($blocks as $blk) {
                $finalCodewords[] = $blk['ec'][$i];
            }
        }

        // 4. Matrix initialization
        $this->size = ($this->version - 1) * 4 + 21;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, null));
        $isFunction = array_fill(0, $this->size, array_fill(0, $this->size, false));

        // Place function patterns
        $this->placeFinderPatterns($isFunction);
        $this->placeAlignmentPatterns($isFunction);
        $this->placeTimingPatterns($isFunction);
        $this->reserveFormatInfo($isFunction);

        // 5. Place data codewords
        $this->placeDataCodewords($finalCodewords, $isFunction);

        // 6. Select best mask (evaluate standard 8 masks by penalty score)
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestModules = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $tempModules = $this->modules;
            $this->applyMask($tempModules, $isFunction, $mask);
            $this->placeFormatInfo($tempModules, $mask);
            $penalty = $this->calculatePenalty($tempModules);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestModules = $tempModules;
            }
        }

        $this->modules = $bestModules;
    }

    private function calculateEc(array $data, int $ecCount): array
    {
        // Build generator polynomial
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $factor = [1, self::$exp[$i]];
            $newGen = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $newGen[$j] ^= self::gfMul($gen[$j], $factor[0]);
                $newGen[$j + 1] ^= self::gfMul($gen[$j], $factor[1]);
            }
            $gen = $newGen;
        }

        // Polynomial division
        $poly = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $poly[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $poly[$i + $j] ^= self::gfMul($gen[$j], $coef);
                }
            }
        }

        return array_slice($poly, count($data));
    }

    private function placeFinderPatterns(array &$isFunction): void
    {
        $finder = [
            [1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 1, 1, 1, 1, 1, 1],
        ];

        $corners = [
            [0, 0],
            [$this->size - 7, 0],
            [0, $this->size - 7],
        ];

        foreach ($corners as [$ox, $oy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $px = $ox + $x;
                    $py = $oy + $y;
                    if ($px >= 0 && $px < $this->size && $py >= 0 && $py < $this->size) {
                        $isFunction[$py][$px] = true;
                        if ($x >= 0 && $x < 7 && $y >= 0 && $y < 7) {
                            $this->modules[$py][$px] = ($finder[$y][$x] === 1);
                        } else {
                            $this->modules[$py][$px] = false; // Separator
                        }
                    }
                }
            }
        }
    }

    private function placeAlignmentPatterns(array &$isFunction): void
    {
        $pos = self::$alignmentPatternPositions[$this->version] ?? [];
        if (empty($pos)) {
            return;
        }

        $coords = [];
        foreach ($pos as $r) {
            foreach ($pos as $c) {
                // Skip if overlapping finder patterns
                if (($r <= 8 && $c <= 8) || ($r <= 8 && $c >= $this->size - 9) || ($r >= $this->size - 9 && $c <= 8)) {
                    continue;
                }
                $coords[] = [$c, $r];
            }
        }

        foreach ($coords as [$cx, $cy]) {
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $px = $cx + $dx;
                    $py = $cy + $dy;
                    $isFunction[$py][$px] = true;
                    $val = (abs($dx) === 2 || abs($dy) === 2 || ($dx === 0 && $dy === 0));
                    $this->modules[$py][$px] = $val;
                }
            }
        }
    }

    private function placeTimingPatterns(array &$isFunction): void
    {
        for ($i = 8; $i < $this->size - 8; $i++) {
            if (!$isFunction[6][$i]) {
                $isFunction[6][$i] = true;
                $this->modules[6][$i] = ($i % 2 === 0);
            }
            if (!$isFunction[$i][6]) {
                $isFunction[$i][6] = true;
                $this->modules[$i][6] = ($i % 2 === 0);
            }
        }

        // Dark module
        $this->modules[$this->size - 8][8] = true;
        $isFunction[$this->size - 8][8] = true;
    }

    private function reserveFormatInfo(array &$isFunction): void
    {
        for ($i = 0; $i < 9; $i++) {
            $isFunction[8][$i] = true;
            $isFunction[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $isFunction[8][$this->size - 1 - $i] = true;
            $isFunction[$this->size - 1 - $i][8] = true;
        }
    }

    private function placeDataCodewords(array $codewords, array $isFunction): void
    {
        $bitIndex = 0;
        $totalBits = count($codewords) * 8;

        $right = $this->size - 1;
        $upward = true;

        while ($right > 0) {
            if ($right === 6) {
                $right--; // Skip vertical timing column
            }

            for ($i = 0; $i < $this->size; $i++) {
                $y = $upward ? ($this->size - 1 - $i) : $i;

                for ($dx = 0; $dx < 2; $dx++) {
                    $x = $right - $dx;
                    if (!$isFunction[$y][$x]) {
                        if ($bitIndex < $totalBits) {
                            $byte = $codewords[(int) ($bitIndex / 8)];
                            $bit = ($byte >> (7 - ($bitIndex % 8))) & 1;
                            $this->modules[$y][$x] = ($bit === 1);
                            $bitIndex++;
                        } else {
                            $this->modules[$y][$x] = false;
                        }
                    }
                }
            }

            $upward = !$upward;
            $right -= 2;
        }
    }

    private function applyMask(array &$modules, array $isFunction, int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($y + $x) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($y + $x) % 3 === 0,
                    4 => ((int) ($y / 2) + (int) ($x / 3)) % 2 === 0,
                    5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
                    6 => ((($y * $x) % 2) + (($y * $x) % 3)) % 2 === 0,
                    7 => ((($y + $x) % 2) + (($y * $x) % 3)) % 2 === 0,
                    default => false,
                };
                if ($invert) {
                    $modules[$y][$x] = !$modules[$y][$x];
                }
            }
        }
    }

    private function placeFormatInfo(array &$modules, int $mask): void
    {
        // EC_M indicator is 0 (00 in 2 bits). Format code: (EC_M << 3) | mask = mask.
        $data = (self::EC_M << 3) | $mask;
        $rem = $data << 10;
        $gen = 0x537; // 10100110111
        for ($i = 4; $i >= 0; $i--) {
            if ($rem & (1 << ($i + 10))) {
                $rem ^= ($gen << $i);
            }
        }
        $formatBits = (($data << 10) | $rem) ^ 0x5412; // Mask with 101010000010010

        // Place around top-left
        $bits = [];
        for ($i = 0; $i < 15; $i++) {
            $bits[$i] = (($formatBits >> $i) & 1) === 1;
        }

        // Top-left horizontal & vertical
        $modules[8][0] = $bits[0];
        $modules[8][1] = $bits[1];
        $modules[8][2] = $bits[2];
        $modules[8][3] = $bits[3];
        $modules[8][4] = $bits[4];
        $modules[8][5] = $bits[5];
        $modules[8][7] = $bits[6];
        $modules[8][8] = $bits[7];
        $modules[7][8] = $bits[8];
        $modules[5][8] = $bits[9];
        $modules[4][8] = $bits[10];
        $modules[3][8] = $bits[11];
        $modules[2][8] = $bits[12];
        $modules[1][8] = $bits[13];
        $modules[0][8] = $bits[14];

        // Second copy around top-right and bottom-left
        for ($i = 0; $i < 8; $i++) {
            $modules[8][$this->size - 1 - $i] = $bits[$i];
        }
        for ($i = 8; $i < 15; $i++) {
            $modules[$this->size - 15 + $i][8] = $bits[$i];
        }
    }

    private function calculatePenalty(array $modules): int
    {
        $penalty = 0;
        $size = $this->size;

        // Condition 1: 5 or more consecutive identical modules in rows/cols
        for ($y = 0; $y < $size; $y++) {
            $runColor = null;
            $runLength = 0;
            for ($x = 0; $x < $size; $x++) {
                $col = $modules[$y][$x];
                if ($col === $runColor) {
                    $runLength++;
                } else {
                    if ($runLength >= 5) {
                        $penalty += 3 + ($runLength - 5);
                    }
                    $runColor = $col;
                    $runLength = 1;
                }
            }
            if ($runLength >= 5) {
                $penalty += 3 + ($runLength - 5);
            }
        }

        for ($x = 0; $x < $size; $x++) {
            $runColor = null;
            $runLength = 0;
            for ($y = 0; $y < $size; $y++) {
                $col = $modules[$y][$x];
                if ($col === $runColor) {
                    $runLength++;
                } else {
                    if ($runLength >= 5) {
                        $penalty += 3 + ($runLength - 5);
                    }
                    $runColor = $col;
                    $runLength = 1;
                }
            }
            if ($runLength >= 5) {
                $penalty += 3 + ($runLength - 5);
            }
        }

        // Condition 2: 2x2 blocks of same color
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $modules[$y][$x];
                if ($c === $modules[$y][$x + 1] && $c === $modules[$y + 1][$x] && $c === $modules[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        return $penalty;
    }
}
