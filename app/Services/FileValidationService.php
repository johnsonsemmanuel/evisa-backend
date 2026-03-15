<?php

namespace App\Services;

use App\Exceptions\InvalidFileContentException;
use App\Exceptions\MaliciousFileException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * FileValidationService
 * 
 * Defense-in-depth file upload validation.
 * Implements OWASP A04:2021 — Insecure Design mitigation.
 * 
 * SECURITY LAYERS:
 * 1. Request-level validation (Laravel rules)
 * 2. Content-level MIME sniffing (magic numbers)
 * 3. Malicious content detection (PHP code, scripts)
 * 4. EXIF data stripping (privacy + security)
 * 
 * @package App\Services
 */
class FileValidationService
{
    /**
     * Magic number signatures for allowed file types
     */
    private const MAGIC_NUMBERS = [
        'pdf'  => "\x25\x50\x44\x46",                     // %PDF
        'jpg'  => "\xFF\xD8\xFF",                         // JFIF/EXIF
        'jpeg' => "\xFF\xD8\xFF",                         // JFIF/EXIF
        'png'  => "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A",   // PNG signature
    ];

    /**
     * Malicious patterns to detect in file content
     */
    private const MALICIOUS_PATTERNS = [
        '/<\?php/i',                    // PHP opening tag
        '/<\?=/i',                      // PHP short echo tag
        '/<script[\s>]/i',              // JavaScript
        '/eval\s*\(/i',                 // eval() function
        '/base64_decode\s*\(/i',        // base64_decode (common in malware)
        '/system\s*\(/i',               // system() function
        '/exec\s*\(/i',                 // exec() function
        '/shell_exec\s*\(/i',           // shell_exec() function
        '/passthru\s*\(/i',             // passthru() function
        '/`[^`]+`/i',                   // Backtick execution
    ];

    /**
     * Maximum file sizes by type (in bytes)
     */
    private const MAX_FILE_SIZES = [
        'pdf'  => 5 * 1024 * 1024,  // 5MB
        'jpg'  => 5 * 1024 * 1024,  // 5MB
        'jpeg' => 5 * 1024 * 1024,  // 5MB
        'png'  => 5 * 1024 * 1024,  // 5MB
    ];

    /**
     * Validate file content using magic numbers and malicious content detection
     *
     * @param UploadedFile $file
     * @return void
     * @throws InvalidFileContentException
     * @throws MaliciousFileException
     */
    public function validateFileContent(UploadedFile $file): void
    {
        $this->validateMagicNumbers($file);
        $this->detectMaliciousContent($file);
        $this->validateFileSize($file);
    }

    /**
     * Validate file magic numbers (first bytes)
     *
     * @param UploadedFile $file
     * @return void
     * @throws InvalidFileContentException
     */
    private function validateMagicNumbers(UploadedFile $file): void
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            throw new InvalidFileContentException('Unable to read file content');
        }

        $bytes = fread($handle, 8);
        fclose($handle);

        $valid = false;
        $detectedType = null;

        foreach (self::MAGIC_NUMBERS as $type => $magic) {
            if (str_starts_with($bytes, $magic)) {
                $valid = true;
                $detectedType = $type;
                break;
            }
        }

        if (!$valid) {
            Log::channel('security')->warning('Invalid file magic number detected', [
                'filename' => $file->getClientOriginalName(),
                'declared_mime' => $file->getMimeType(),
                'declared_extension' => $file->getClientOriginalExtension(),
                'first_bytes' => bin2hex($bytes),
            ]);

            throw new InvalidFileContentException(
                'File content does not match declared type. The file may be corrupted or forged.'
            );
        }

        // Verify detected type matches declared extension
        $declaredExtension = strtolower($file->getClientOriginalExtension());
        if ($detectedType !== $declaredExtension && !($detectedType === 'jpg' && $declaredExtension === 'jpeg')) {
            Log::channel('security')->warning('File extension mismatch', [
                'filename' => $file->getClientOriginalName(),
                'declared_extension' => $declaredExtension,
                'detected_type' => $detectedType,
            ]);

            throw new InvalidFileContentException(
                "File extension mismatch. Declared: {$declaredExtension}, Detected: {$detectedType}"
            );
        }
    }

    /**
     * Detect malicious content in file (polyglot attacks, embedded code)
     *
     * @param UploadedFile $file
     * @return void
     * @throws MaliciousFileException
     */
    private function detectMaliciousContent(UploadedFile $file): void
    {
        // Read file content (limit to first 1MB for performance)
        $maxReadSize = 1024 * 1024; // 1MB
        $handle = fopen($file->getRealPath(), 'rb');
        $content = fread($handle, $maxReadSize);
        fclose($handle);

        // Check for malicious patterns
        foreach (self::MALICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                Log::channel('security')->critical('Malicious file content detected', [
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'pattern_matched' => $pattern,
                    'ip_address' => request()->ip(),
                    'user_id' => auth()->id(),
                ]);

                throw new MaliciousFileException(
                    'File contains potentially malicious content and has been rejected.'
                );
            }
        }

        // Additional check for null bytes (often used in path traversal)
        if (str_contains($content, "\0")) {
            Log::channel('security')->critical('Null byte detected in file', [
                'filename' => $file->getClientOriginalName(),
            ]);

            throw new MaliciousFileException(
                'File contains invalid characters and has been rejected.'
            );
        }
    }

    /**
     * Validate file size
     *
     * @param UploadedFile $file
     * @return void
     * @throws InvalidFileContentException
     */
    private function validateFileSize(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $maxSize = self::MAX_FILE_SIZES[$extension] ?? self::MAX_FILE_SIZES['pdf'];

        if ($file->getSize() > $maxSize) {
            throw new InvalidFileContentException(
                'File size exceeds maximum allowed size of ' . ($maxSize / 1024 / 1024) . 'MB'
            );
        }
    }

    /**
     * Strip EXIF data from images (privacy + security)
     *
     * @param UploadedFile $file
     * @param string $outputPath
     * @return bool
     */
    public function stripExifData(UploadedFile $file, string $outputPath): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // Only process images
        if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
            return false;
        }

        try {
            // Load image
            $image = match ($extension) {
                'png' => imagecreatefrompng($file->getRealPath()),
                'jpg', 'jpeg' => imagecreatefromjpeg($file->getRealPath()),
                default => false,
            };

            if (!$image) {
                return false;
            }

            // Save without EXIF data
            $result = match ($extension) {
                'png' => imagepng($image, $outputPath, 9),
                'jpg', 'jpeg' => imagejpeg($image, $outputPath, 90),
                default => false,
            };

            imagedestroy($image);

            return $result !== false;
        } catch (\Exception $e) {
            Log::error('Failed to strip EXIF data', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get allowed MIME types
     *
     * @return array
     */
    public function getAllowedMimeTypes(): array
    {
        return [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ];
    }

    /**
     * Get allowed extensions
     *
     * @return array
     */
    public function getAllowedExtensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png'];
    }
}
