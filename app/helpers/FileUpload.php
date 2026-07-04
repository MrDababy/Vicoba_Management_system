<?php
/**
 * File Upload Helper
 * 
 * Handles secure file uploads with validation for profile pictures
 * and other documents.
 * 
 * @package Vicoba
 * @author VICOBA Team
 * @version 1.0.0
 */

namespace App\Helpers;

class FileUpload
{
    /**
     * @var array Allowed image types
     */
    private array $allowedImageTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    /**
     * @var int Maximum file size (5MB)
     */
    private int $maxFileSize = 5242880;

    /**
     * @var string Upload directory
     */
    private string $uploadDir = 'uploads/profiles/';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Create upload directory if it doesn't exist
        $this->createUploadDirectory();
    }

    /**
     * Upload a profile picture
     * 
     * @param array $file File array from $_FILES
     * @return array ['success' => bool, 'filename' => string, 'message' => string]
     */
    public function uploadProfilePicture(array $file): array
    {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message']
            ];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $this->generateFilename($extension);

        // Move file to upload directory
        $destination = $this->uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Resize image if needed
            $this->resizeImage($destination, 400, 400);

            return [
                'success' => true,
                'filename' => $filename,
                'message' => 'File uploaded successfully'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to upload file'
        ];
    }

    /**
     * Validate file
     * 
     * @param array $file File array from $_FILES
     * @return array ['valid' => bool, 'message' => string]
     */
    private function validateFile(array $file): array
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->handleUploadError($file['error']);
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File is too large. Maximum size is ' . $this->formatFileSize($this->maxFileSize)
            ];
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedImageTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Allowed types: JPEG, PNG, GIF, WebP'
            ];
        }

        // Additional security checks
        if (!$this->isImageSafe($file['tmp_name'])) {
            return [
                'valid' => false,
                'message' => 'File appears to be corrupted or unsafe'
            ];
        }

        return ['valid' => true, 'message' => 'File validation passed'];
    }

    /**
     * Handle upload errors
     * 
     * @param int $errorCode Upload error code
     * @return array
     */
    private function handleUploadError(int $errorCode): array
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the maximum size limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum size limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
        ];

        return [
            'valid' => false,
            'message' => $messages[$errorCode] ?? 'Unknown upload error.'
        ];
    }

    /**
     * Check if image is safe
     * 
     * @param string $filePath Path to file
     * @return bool
     */
    private function isImageSafe(string $filePath): bool
    {
        // Check if it's a valid image
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            return false;
        }

        // Check for animated GIFs (which can be a security risk)
        if ($imageInfo[2] === IMAGETYPE_GIF) {
            $gifContent = file_get_contents($filePath);
            if (strpos($gifContent, 'NETSCAPE2.0') !== false) {
                // Animated GIF - could be a security risk
                return false;
            }
        }

        return true;
    }

    /**
     * Generate unique filename
     * 
     * @param string $extension File extension
     * @return string
     */
    private function generateFilename(string $extension): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return "profile_{$timestamp}_{$random}." . $extension;
    }

    /**
     * Resize image
     * 
     * @param string $filePath Path to image
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @return bool
     */
    private function resizeImage(string $filePath, int $maxWidth, int $maxHeight): bool
    {
        try {
            // Get image info
            list($width, $height, $type) = getimagesize($filePath);

            // Only resize if image is larger than max dimensions
            if ($width <= $maxWidth && $height <= $maxHeight) {
                return true;
            }

            // Calculate new dimensions maintaining aspect ratio
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);

            // Create new image
            $source = $this->imageCreateFromType($filePath, $type);
            if (!$source) {
                return false;
            }

            $destination = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and GIF
            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
                imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
                imagealphablending($destination, false);
                imagesavealpha($destination, true);
            }

            // Resize
            imagecopyresampled(
                $destination,
                $source,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );

            // Save image
            $this->imageSaveByType($destination, $filePath, $type);

            // Free memory
            imagedestroy($source);
            imagedestroy($destination);

            return true;

        } catch (\Exception $e) {
            error_log('Image resize failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create image resource from file type
     * 
     * @param string $filePath Path to image
     * @param int $type Image type constant
     * @return resource|false
     */
    private function imageCreateFromType(string $filePath, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }

    /**
     * Save image by type
     * 
     * @param resource $image Image resource
     * @param string $filePath Path to save
     * @param int $type Image type constant
     * @return bool
     */
    private function imageSaveByType($image, string $filePath, int $type): bool
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($image, $filePath, 90);
            case IMAGETYPE_PNG:
                return imagepng($image, $filePath, 9);
            case IMAGETYPE_GIF:
                return imagegif($image, $filePath);
            case IMAGETYPE_WEBP:
                return imagewebp($image, $filePath, 90);
            default:
                return false;
        }
    }

    /**
     * Delete an uploaded file
     * 
     * @param string $filename File name to delete
     * @return bool
     */
    public function deleteFile(string $filename): bool
    {
        $filePath = $this->uploadDir . $filename;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Create upload directory if it doesn't exist
     * 
     * @return void
     */
    private function createUploadDirectory(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // Create .htaccess to prevent direct access
        $htaccessPath = $this->uploadDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $content = "Options -Indexes\n";
            $content .= "Deny from all\n";
            $content .= "<FilesMatch '\.(jpg|jpeg|png|gif|webp)$'>\n";
            $content .= "    Allow from all\n";
            $content .= "</FilesMatch>\n";
            file_put_contents($htaccessPath, $content);
        }
    }

    /**
     * Format file size for display
     * 
     * @param int $bytes File size in bytes
     * @return string
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}