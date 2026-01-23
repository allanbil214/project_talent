<?php
// classes/Upload.php

class Upload {
    private $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
    private $allowed_document_types = ['pdf', 'doc', 'docx'];
    private $max_image_size = 5242880; // 5MB
    private $max_document_size = 10485760; // 10MB
    
    /**
     * Upload profile photo
     */
    public function uploadProfilePhoto($file) {
        return $this->uploadImage($file, 'uploads/profiles');
    }
    
    /**
     * Upload company logo
     */
    public function uploadCompanyLogo($file) {
        return $this->uploadImage($file, 'uploads/company-logos');
    }
    
    /**
     * Upload resume
     */
    public function uploadResume($file) {
        return $this->uploadDocument($file, 'uploads/resumes');
    }
    
    /**
     * Upload portfolio file
     */
    public function uploadPortfolio($file) {
        return $this->uploadFile($file, 'uploads/portfolios', 
            array_merge($this->allowed_image_types, $this->allowed_document_types),
            $this->max_document_size
        );
    }
    
    /**
     * Upload contract document
     */
    public function uploadContract($file) {
        return $this->uploadDocument($file, 'uploads/documents');
    }
    
    /**
     * Upload image
     */
    private function uploadImage($file, $destination_folder) {
        return $this->uploadFile($file, $destination_folder, 
            $this->allowed_image_types, $this->max_image_size);
    }
    
    /**
     * Upload document
     */
    private function uploadDocument($file, $destination_folder) {
        return $this->uploadFile($file, $destination_folder, 
            $this->allowed_document_types, $this->max_document_size);
    }
    
    /**
     * Upload file (generic)
     */
    private function uploadFile($file, $destination_folder, $allowed_types, $max_size) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file uploaded');
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($extension, $allowed_types)) {
            throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowed_types));
        }
        
        // Validate file size
        if ($file['size'] > $max_size) {
            throw new Exception('File too large. Maximum size: ' . $this->formatFileSize($max_size));
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!$this->isValidMimeType($mime_type, $extension)) {
            throw new Exception('Invalid file type');
        }
        
        // Create destination folder if it doesn't exist
        if (!is_dir($destination_folder)) {
            mkdir($destination_folder, 0755, true);
        }
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($extension);
        $destination_path = $destination_folder . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination_path)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        // Resize image if needed
        if (in_array($extension, $this->allowed_image_types)) {
            $this->resizeImageIfNeeded($destination_path, $extension);
        }
        
        return $destination_path;
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($extension) {
        return time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
    
    /**
     * Validate MIME type
     */
    private function isValidMimeType($mime_type, $extension) {
        $valid_mime_types = [
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        ];
        
        if (!isset($valid_mime_types[$extension])) {
            return false;
        }
        
        return in_array($mime_type, $valid_mime_types[$extension]);
    }
    
    /**
     * Resize image if needed
     */
    private function resizeImageIfNeeded($file_path, $extension) {
        $max_width = 1920;
        $max_height = 1080;
        
        list($width, $height) = getimagesize($file_path);
        
        // Check if resize is needed
        if ($width <= $max_width && $height <= $max_height) {
            return;
        }
        
        // Calculate new dimensions
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        // Create image resource
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = imagecreatefromjpeg($file_path);
                break;
            case 'png':
                $source = imagecreatefrompng($file_path);
                break;
            case 'gif':
                $source = imagecreatefromgif($file_path);
                break;
            default:
                return;
        }
        
        // Create new image
        $destination = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG and GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
        }
        
        // Resize
        imagecopyresampled($destination, $source, 0, 0, 0, 0, 
            $new_width, $new_height, $width, $height);
        
        // Save
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($destination, $file_path, 90);
                break;
            case 'png':
                imagepng($destination, $file_path, 9);
                break;
            case 'gif':
                imagegif($destination, $file_path);
                break;
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($destination);
    }
    
    /**
     * Delete file
     */
    public function deleteFile($file_path) {
        if (file_exists($file_path)) {
            return unlink($file_path);
        }
        return false;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($error_code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $errors[$error_code] ?? 'Unknown upload error';
    }
    
    /**
     * Format file size
     */
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
    
    /**
     * Create thumbnail
     */
    public function createThumbnail($source_path, $width = 150, $height = 150) {
        $extension = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowed_image_types)) {
            throw new Exception('Not an image file');
        }
        
        list($orig_width, $orig_height) = getimagesize($source_path);
        
        // Create image resource
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = imagecreatefromjpeg($source_path);
                break;
            case 'png':
                $source = imagecreatefrompng($source_path);
                break;
            case 'gif':
                $source = imagecreatefromgif($source_path);
                break;
            default:
                return null;
        }
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($width, $height);
        
        // Preserve transparency
        if ($extension === 'png' || $extension === 'gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }
        
        // Resize and crop to square
        $ratio = max($width / $orig_width, $height / $orig_height);
        $new_width = round($orig_width * $ratio);
        $new_height = round($orig_height * $ratio);
        $x = round(($width - $new_width) / 2);
        $y = round(($height - $new_height) / 2);
        
        imagecopyresampled($thumbnail, $source, $x, $y, 0, 0, 
            $new_width, $new_height, $orig_width, $orig_height);
        
        // Generate thumbnail filename
        $thumbnail_path = str_replace('.' . $extension, '_thumb.' . $extension, $source_path);
        
        // Save
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnail, $thumbnail_path, 90);
                break;
            case 'png':
                imagepng($thumbnail, $thumbnail_path, 9);
                break;
            case 'gif':
                imagegif($thumbnail, $thumbnail_path);
                break;
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return $thumbnail_path;
    }
}