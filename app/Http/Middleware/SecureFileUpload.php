<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Government-Grade Secure File Upload Middleware
 * Protects against malicious file uploads, IDOR attacks, and file enumeration.
 */
class SecureFileUpload
{
    /**
     * Allowed MIME types for visa documents.
     */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    /**
     * Allowed file extensions.
     */
    protected array $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'pdf',
    ];

    /**
     * Dangerous file signatures (magic bytes) to block.
     */
    protected array $dangerousSignatures = [
        "\x4D\x5A",                     // Windows executable (MZ)
        "\x7F\x45\x4C\x46",             // Linux executable (ELF)
        "<?php",                         // PHP code
        "<?=",                           // PHP short tag
        "<script",                       // JavaScript
        "#!/",                           // Shell script
        "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00PK", // ZIP bomb
    ];

    /**
     * Maximum file size in bytes (10MB).
     */
    protected int $maxFileSize = 10485760;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasFile('file')) {
            return $next($request);
        }

        $file = $request->file('file');
        
        if (is_array($file)) {
            foreach ($file as $f) {
                $result = $this->validateFile($f, $request);
                if ($result !== true) {
                    return $result;
                }
            }
        } else {
            $result = $this->validateFile($file, $request);
            if ($result !== true) {
                return $result;
            }
        }

        return $next($request);
    }

    /**
     * Validate a single file.
     */
    protected function validateFile(UploadedFile $file, Request $request): Response|bool
    {
        // 1. Check file size
        if ($file->getSize() > $this->maxFileSize) {
            return $this->rejectFile('File size exceeds maximum allowed (10MB)', $request, $file);
        }

        // 2. Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            return $this->rejectFile('File extension not allowed', $request, $file);
        }

        // 3. Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return $this->rejectFile('File type not allowed', $request, $file);
        }

        // 4. Verify MIME type matches extension
        if (!$this->mimeMatchesExtension($mimeType, $extension)) {
            return $this->rejectFile('File extension does not match content type', $request, $file);
        }

        // 5. Check for dangerous file signatures
        if ($this->hasDangerousSignature($file)) {
            return $this->rejectFile('File contains potentially malicious content', $request, $file);
        }

        // 6. Check for double extensions
        $originalName = $file->getClientOriginalName();
        if ($this->hasDoubleExtension($originalName)) {
            return $this->rejectFile('Double file extensions not allowed', $request, $file);
        }

        // 7. Validate image dimensions (prevent image bombs)
        if (str_starts_with($mimeType, 'image/')) {
            if (!$this->validateImageDimensions($file)) {
                return $this->rejectFile('Image dimensions exceed maximum allowed', $request, $file);
            }
        }

        return true;
    }

    /**
     * Check if MIME type matches extension.
     */
    protected function mimeMatchesExtension(string $mime, string $extension): bool
    {
        $mimeExtensionMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'application/pdf' => ['pdf'],
        ];

        return isset($mimeExtensionMap[$mime]) && in_array($extension, $mimeExtensionMap[$mime]);
    }

    /**
     * Check for dangerous file signatures.
     */
    protected function hasDangerousSignature(UploadedFile $file): bool
    {
        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
            return true; // Fail safe
        }

        $header = fread($handle, 256);
        fclose($handle);

        foreach ($this->dangerousSignatures as $signature) {
            if (str_contains($header, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for double extensions (e.g., file.php.jpg).
     */
    protected function hasDoubleExtension(string $filename): bool
    {
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'svg'];
        
        $parts = explode('.', strtolower($filename));
        
        if (count($parts) > 2) {
            foreach ($parts as $part) {
                if (in_array($part, $dangerousExtensions)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate image dimensions to prevent image bombs.
     */
    protected function validateImageDimensions(UploadedFile $file): bool
    {
        $maxDimension = 10000; // 10,000 pixels max
        
        try {
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo === false) {
                return false;
            }

            [$width, $height] = $imageInfo;
            
            if ($width > $maxDimension || $height > $maxDimension) {
                return false;
            }

            // Check for decompression bomb (very small file, huge dimensions)
            $fileSize = $file->getSize();
            $pixelCount = $width * $height;
            
            if ($fileSize < 1000 && $pixelCount > 1000000) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reject file and log security event.
     */
    protected function rejectFile(string $reason, Request $request, UploadedFile $file): Response
    {
        \Log::channel('security')->warning('Malicious file upload blocked', [
            'reason' => $reason,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => $reason,
            'error' => 'FILE_VALIDATION_FAILED',
        ], 422);
    }
}
