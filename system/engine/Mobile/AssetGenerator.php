<?php

namespace Engine\Mobile;

/**
 * Class AssetGenerator
 *
 * Generates Android app icons and splash screens using GD.
 *
 * @package Engine\Mobile
 */
class AssetGenerator
{
    protected string $androidPath;
    protected string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->androidPath = $rootPath . '/native/android';
    }

    /**
     * Generate icons and splash screen.
     *
     * @param string $appName
     * @return void
     */
    public function generate(string $appName): void
    {
        if (!extension_loaded('gd')) {
            echo "Warning: GD extension not loaded. Skipping asset generation.\n";
            return;
        }

        echo "Generating assets for '$appName'...\n";

        // 1. Icons
        $this->generateIcons($appName);

        // 2. Splash Screen
        $this->generateSplash($appName);
    }

    protected function generateIcons(string $appName): void
    {
        $iconSource = $this->rootPath . '/icon.png';
        $useSource = file_exists($iconSource);

        $sizes = [
            'mipmap-mdpi' => 48,
            'mipmap-hdpi' => 72,
            'mipmap-xhdpi' => 96,
            'mipmap-xxhdpi' => 144,
            'mipmap-xxxhdpi' => 192,
        ];

        foreach ($sizes as $folder => $size) {
            $targetDir = $this->androidPath . "/app/src/main/res/$folder";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Target WebP to overwrite existing default assets
            $targetFile = "$targetDir/ic_launcher.webp";
            $targetRound = "$targetDir/ic_launcher_round.webp";
            $targetForeground = "$targetDir/ic_launcher_foreground.webp";

            // Remove conflicting PNGs if they exist
            if (file_exists("$targetDir/ic_launcher.png")) unlink("$targetDir/ic_launcher.png");
            if (file_exists("$targetDir/ic_launcher_round.png")) unlink("$targetDir/ic_launcher_round.png");
            if (file_exists("$targetDir/ic_launcher_foreground.png")) unlink("$targetDir/ic_launcher_foreground.png");

            if ($useSource) {
                $this->resizeImage($iconSource, $targetFile, $size, $size);
                $this->resizeImage($iconSource, $targetRound, $size, $size);
                $this->resizeImage($iconSource, $targetForeground, $size, $size); // Use same icon for foreground
            } else {
                $this->createPlaceholderIcon($appName, $targetFile, $size);
                $this->createPlaceholderIcon($appName, $targetRound, $size, true);
                $this->createPlaceholderIcon($appName, $targetForeground, $size, false); // Square for foreground
            }
        }
    }

    protected function generateSplash(string $appName): void
    {
        $splashSource = $this->rootPath . '/splash.png';
        $targetDir = $this->androidPath . '/app/src/main/res/drawable';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetFile = "$targetDir/splash.webp";

        // Remove conflicting PNG if it exists
        if (file_exists("$targetDir/splash.png")) unlink("$targetDir/splash.png");

        if (file_exists($splashSource)) {
            // Resize to a reasonable size, e.g., 1080x1920 max, maintaining aspect ratio?
            // Or just copy if it's already good. Let's resize to fit within 1080p to be safe.
            $this->resizeImage($splashSource, $targetFile, 1080, 1920, true);
        } else {
            $this->createPlaceholderSplash($appName, $targetFile);
        }
    }

    protected function createPlaceholderIcon(string $appName, string $path, int $size, bool $round = false): void
    {
        $im = imagecreatetruecolor($size, $size);

        // Random background color based on app name
        $hash = md5($appName);
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));

        $bg = imagecolorallocate($im, $r, $g, $b);
        $fg = imagecolorallocate($im, 255, 255, 255);

        if ($round) {
            // Transparent background
            $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefill($im, 0, 0, $trans);
            imagesavealpha($im, true);

            // Draw filled circle
            imagefilledellipse($im, $size/2, $size/2, $size, $size, $bg);
        } else {
            imagefill($im, 0, 0, $bg);
        }

        // Initials
        $initials = strtoupper(substr($appName, 0, 2));

        // Centered text
        // Use built-in font if no TTF
        $fontSize = 5; // Built-in font 1-5
        $fontWidth = imagefontwidth($fontSize);
        $fontHeight = imagefontheight($fontSize);
        $textWidth = strlen($initials) * $fontWidth;

        // Scale up for larger icons manually if needed, but built-in font is small.
        // For better results, we really need a TTF.
        // But let's use built-in for now.

        $x = ($size - $textWidth) / 2;
        $y = ($size - $fontHeight) / 2;

        imagestring($im, $fontSize, (int)$x, (int)$y, $initials, $fg);

        imagewebp($im, $path, 100);
        imagedestroy($im);
    }

    protected function createPlaceholderSplash(string $appName, string $path): void
    {
        $width = 1080;
        $height = 1920;
        $im = imagecreatetruecolor($width, $height);

        // Same color generation
        $hash = md5($appName);
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));

        $bg = imagecolorallocate($im, $r, $g, $b);
        $fg = imagecolorallocate($im, 255, 255, 255);

        imagefill($im, 0, 0, $bg);

        // Draw text scaled up to be larger
        $fontSize = 5;
        $fontWidth = imagefontwidth($fontSize);
        $fontHeight = imagefontheight($fontSize);

        $textLen = strlen($appName);
        $textPixelWidth = $textLen * $fontWidth;

        // Create a temporary small image for the text
        $tempW = $textPixelWidth + 4; // Minimal padding
        $tempH = $fontHeight + 4;
        $tempIm = imagecreatetruecolor($tempW, $tempH);

        // Fill temp with background color
        $tempBg = imagecolorallocate($tempIm, $r, $g, $b);
        $tempFg = imagecolorallocate($tempIm, 255, 255, 255);
        imagefill($tempIm, 0, 0, $tempBg);

        // Draw string centered in temp image
        $tempX = ($tempW - $textPixelWidth) / 2;
        $tempY = ($tempH - $fontHeight) / 2;
        imagestring($tempIm, $fontSize, (int)$tempX, (int)$tempY, $appName, $tempFg);

        // Scale up to 80% of screen width
        $targetW = (int)($width * 0.8);
        $scale = $targetW / $tempW;
        $targetH = (int)($tempH * $scale);

        // Limit height if it gets too tall (e.g. short text)
        if ($targetH > $height * 0.5) {
            $targetH = (int)($height * 0.5);
            $scale = $targetH / $tempH;
            $targetW = (int)($tempW * $scale);
        }

        $destX = ($width - $targetW) / 2;
        $destY = ($height - $targetH) / 2;

        // Use imagecopyresized for "pixel art" style sharpness, or resampled for blur
        // Given it's a tiny font, resampled might look very blurry. Resized keeps blocks.
        // Let's use resampled for smoother edges even if blurry.
        imagecopyresampled($im, $tempIm, (int)$destX, (int)$destY, 0, 0, $targetW, $targetH, $tempW, $tempH);

        imagedestroy($tempIm);

        imagewebp($im, $path, 100);
        imagedestroy($im);
    }

    protected function resizeImage(string $source, string $dest, int $w, int $h, bool $keepAspect = false): void
    {
        $info = getimagesize($source);
        if (!$info) return;

        $width = $info[0];
        $height = $info[1];
        $type = $info[2];

        if ($keepAspect) {
            $ratio = $width / $height;
            if ($w / $h > $ratio) {
                $w = (int)($h * $ratio);
            } else {
                $h = (int)($w / $ratio);
            }
        }

        $new = imagecreatetruecolor($w, $h);

        switch ($type) {
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($source);
                break;
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_WEBP:
                $src = imagecreatefromwebp($source);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($source);
                break;
            default:
                echo "Warning: Unsupported image type for $source. Skipping.\n";
                imagedestroy($new);
                return;
        }

        // Handle transparency
        imagealphablending($new, false);
        imagesavealpha($new, true);

        imagecopyresampled($new, $src, 0, 0, 0, 0, $w, $h, $width, $height);

        // Output as WebP
        imagewebp($new, $dest, 100);

        imagedestroy($new);
        imagedestroy($src);
    }
}
