<?php

namespace App\Services;

class CaptchaService
{
    /**
     * Generate a single CAPTCHA image's base64 URI for a given code.
     */
    public function generateImageBase64(string $code): string
    {
        $width = 120;
        $height = 40;
        $image = imagecreatetruecolor($width, $height);

        // Soft light gray background
        $backgroundColor = imagecolorallocate($image, 245, 247, 250);
        imagefill($image, 0, 0, $backgroundColor);

        // Add noise (lines & dots)
        for ($i = 0; $i < 4; $i++) {
            $lineColor = imagecolorallocate($image, random_int(180, 220), random_int(180, 220), random_int(180, 220));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
        }

        for ($i = 0; $i < 100; $i++) {
            $dotColor = imagecolorallocate($image, random_int(150, 220), random_int(150, 220), random_int(150, 220));
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
        }

        // Draw text
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $char = $code[$i];
            $x = 12 + ($i * 20);
            $y = random_int(8, 16);

            // Dark slate shades for characters
            $charColor = imagecolorallocate($image, random_int(20, 60), random_int(30, 80), random_int(40, 100));

            imagechar($image, 5, $x, $y, $char, $charColor);
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Generate a pool of CAPTCHAs, save codes to session, and return image URIs.
     */
    public function generatePool(int $size = 5): array
    {
        $chars = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $codes = [];
        $images = [];

        for ($k = 0; $k < $size; $k++) {
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $codes[] = $code;
            $images[] = $this->generateImageBase64($code);
        }

        // Save pool to session
        session()->put('captcha_pool', $codes);

        return $images;
    }

    /**
     * Generate a new single captcha, append to session pool, and return base64 image.
     */
    public function generateAndPoolNext(): string
    {
        $chars = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $pool = session()->get('captcha_pool', []);
        $pool[] = $code;

        // Cap pool at maximum 10 items
        if (count($pool) > 10) {
            array_shift($pool);
        }

        session()->put('captcha_pool', $pool);

        return $this->generateImageBase64($code);
    }
}
