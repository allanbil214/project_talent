<?php
// classes/Upload.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class Upload {
    private $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
    private $allowed_document_types = ['pdf', 'doc', 'docx'];
    private $max_image_size = 5242880; // 5MB
    private $max_document_size = 10485760; // 10MB
    private $project_root;
    private $db; // Add database property for logging
    
    public function __construct($db = null) { // Accept database connection
        $this->project_root = dirname(__DIR__);
        $this->db = $db; // Store database connection
    }
    
    /**
     * Upload profile photo
     */
    public function uploadProfilePhoto($file) {
        try {
            $result = $this->uploadImage($file, 'uploads/profiles');
            
            // Log successful upload
            if ($this->db) {
                logActivity($this->db, 'file_uploaded', 
                    "Profile photo uploaded: {$result}. " .
                    "Size: " . $this->formatFileSize($file['size']) . ", " .
                    "Type: {$file['type']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Log failed upload
            if ($this->db) {
                logActivity($this->db, 'file_upload_failed', 
                    "Profile photo upload failed: " . $e->getMessage() . ". " .
                    "Filename: {$file['name']}, Size: " . $this->formatFileSize($file['size']));
            }
            throw $e;
        }
    }
    
    /**
     * Upload company logo
     */
    public function uploadCompanyLogo($file) {
        try {
            $result = $this->uploadImage($file, 'uploads/company-logos');
            
            // Log successful upload
            if ($this->db) {
                logActivity($this->db, 'file_uploaded', 
                    "Company logo uploaded: {$result}. " .
                    "Size: " . $this->formatFileSize($file['size']) . ", " .
                    "Type: {$file['type']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Log failed upload
            if ($this->db) {
                logActivity($this->db, 'file_upload_failed', 
                    "Company logo upload failed: " . $e->getMessage() . ". " .
                    "Filename: {$file['name']}, Size: " . $this->formatFileSize($file['size']));
            }
            throw $e;
        }
    }
    
    /**
     * Upload resume
     */
    public function uploadResume($file) {
        try {
            $result = $this->uploadDocument($file, 'uploads/resumes');
            
            // Log successful upload
            if ($this->db) {
                logActivity($this->db, 'file_uploaded', 
                    "Resume uploaded: {$result}. " .
                    "Size: " . $this->formatFileSize($file['size']) . ", " .
                    "Type: {$file['type']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Log failed upload
            if ($this->db) {
                logActivity($this->db, 'file_upload_failed', 
                    "Resume upload failed: " . $e->getMessage() . ". " .
                    "Filename: {$file['name']}, Size: " . $this->formatFileSize($file['size']));
            }
            throw $e;
        }
    }
    
    /**
     * Upload portfolio file
     */
    public function uploadPortfolio($file) {
        try {
            $result = $this->uploadFile($file, 'uploads/portfolios', 
                array_merge($this->allowed_image_types, $this->allowed_document_types),
                $this->max_document_size
            );
            
            // Log successful upload
            if ($this->db) {
                logActivity($this->db, 'file_uploaded', 
                    "Portfolio file uploaded: {$result}. " .
                    "Size: " . $this->formatFileSize($file['size']) . ", " .
                    "Type: {$file['type']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Log failed upload
            if ($this->db) {
                logActivity($this->db, 'file_upload_failed', 
                    "Portfolio upload failed: " . $e->getMessage() . ". " .
                    "Filename: {$file['name']}, Size: " . $this->formatFileSize($file['size']));
            }
            throw $e;
        }
    }
    
    /**
     * Upload contract document
     */
    public function uploadContract($file) {
        try {
            $result = $this->uploadDocument($file, 'uploads/documents');
            
            // Log successful upload
            if ($this->db) {
                logActivity($this->db, 'file_uploaded', 
                    "Contract document uploaded: {$result}. " .
                    "Size: " . $this->formatFileSize($file['size']) . ", " .
                    "Type: {$file['type']}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Log failed upload
            if ($this->db) {
                logActivity($this->db, 'file_upload_failed', 
                    "Contract document upload failed: " . $e->getMessage() . ". " .
                    "Filename: {$file['name']}, Size: " . $this->formatFileSize($file['size']));
            }
            throw $e;
        }
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
            $error_msg = 'No file uploaded';
            if ($this->db) {
                logActivity($this->db, 'file_upload_validation_failed', 
                    "Upload validation failed: {$error_msg}");
            }
            throw new Exception($error_msg);
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_msg = $this->getUploadErrorMessage($file['error']);
            if ($this->db) {
                logActivity($this->db, 'file_upload_error', 
                    "Upload error (code {$file['error']}): {$error_msg}. " .
                    "Filename: {$file['name']}");
            }
            throw new Exception($error_msg);
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($extension, $allowed_types)) {
            $error_msg = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
            if ($this->db) {
                logActivity($this->db, 'file_upload_type_invalid', 
                    "Invalid file type: {$extension}. " .
                    "Filename: {$file['name']}, Allowed: " . implode(', ', $allowed_types));
            }
            throw new Exception($error_msg);
        }
        
        // Validate file size
        if ($file['size'] > $max_size) {
            $error_msg = 'File too large. Maximum size: ' . $this->formatFileSize($max_size);
            if ($this->db) {
                logActivity($this->db, 'file_upload_size_invalid', 
                    "File too large: " . $this->formatFileSize($file['size']) . ". " .
                    "Filename: {$file['name']}, Max allowed: " . $this->formatFileSize($max_size));
            }
            throw new Exception($error_msg);
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!$this->isValidMimeType($mime_type, $extension)) {
            $error_msg = 'Invalid file type (MIME mismatch)';
            if ($this->db) {
                logActivity($this->db, 'file_upload_mime_invalid', 
                    "MIME type mismatch: Extension {$extension}, MIME {$mime_type}. " .
                    "Filename: {$file['name']}");
            }
            throw new Exception($error_msg);
        }
        
        // Create full destination folder path
        $full_destination_folder = $this->project_root . '/' . $destination_folder;
        
        // Create destination folder if it doesn't exist
        if (!is_dir($full_destination_folder)) {
            if (!mkdir($full_destination_folder, 0755, true)) {
                $error_msg = 'Failed to create upload directory';
                if ($this->db) {
                    logActivity($this->db, 'file_upload_directory_failed', 
                        "Failed to create directory: {$full_destination_folder}");
                }
                throw new Exception($error_msg);
            }
            
            // Log directory creation
            if ($this->db) {
                logActivity($this->db, 'upload_directory_created', 
                    "Upload directory created: {$destination_folder}");
            }
        }
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($extension);
        $full_destination_path = $full_destination_folder . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $full_destination_path)) {
            $error_msg = 'Failed to move uploaded file';
            if ($this->db) {
                logActivity($this->db, 'file_upload_move_failed', 
                    "Failed to move file to: {$full_destination_path}");
            }
            throw new Exception($error_msg);
        }
        
        // Resize image if needed
        $resized = false;
        if (in_array($extension, $this->allowed_image_types)) {
            $resized = $this->resizeImageIfNeeded($full_destination_path, $extension);
        }
        
        // Log image resizing if it happened
        if ($resized && $this->db) {
            logActivity($this->db, 'image_resized', 
                "Image resized during upload: {$filename} in {$destination_folder}");
        }
        
        // Return relative path (without project root)
        return $destination_folder . '/' . $filename;
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
            return false;
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
                return false;
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
        
        return true;
    }
    
    /**
     * Delete file
     */
    public function deleteFile($file_path) {
        $full_path = $this->project_root . '/' . $file_path;
        
        if (file_exists($full_path)) {
            $file_size = filesize($full_path);
            $result = unlink($full_path);
            
            // Log file deletion
            if ($result && $this->db) {
                logActivity($this->db, 'file_deleted', 
                    "File deleted: {$file_path}. " .
                    "Size: " . $this->formatFileSize($file_size));
            }
            
            // Also delete thumbnail if exists
            $extension = pathinfo($file_path, PATHINFO_EXTENSION);
            $thumbnail_path = str_replace('.' . $extension, '_thumb.' . $extension, $file_path);
            $full_thumbnail_path = $this->project_root . '/' . $thumbnail_path;
            
            if (file_exists($full_thumbnail_path)) {
                unlink($full_thumbnail_path);
                if ($this->db) {
                    logActivity($this->db, 'file_deleted', 
                        "Thumbnail deleted: {$thumbnail_path}");
                }
            }
            
            return $result;
        }
        
        // Log failed deletion
        if ($this->db) {
            logActivity($this->db, 'file_delete_failed', 
                "Failed to delete file - not found: {$file_path}");
        }
        
        return false;
    }
    
    /**
     * Create thumbnail
     */
    public function createThumbnail($source_path, $width = 150, $height = 150) {
        $full_source_path = $this->project_root . '/' . $source_path;
        
        if (!file_exists($full_source_path)) {
            if ($this->db) {
                logActivity($this->db, 'thumbnail_creation_failed', 
                    "Thumbnail creation failed - source not found: {$source_path}");
            }
            throw new Exception('Source file not found');
        }
        
        $extension = strtolower(pathinfo($full_source_path, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowed_image_types)) {
            if ($this->db) {
                logActivity($this->db, 'thumbnail_creation_failed', 
                    "Thumbnail creation failed - not an image: {$source_path}");
            }
            throw new Exception('Not an image file');
        }
        
        list($orig_width, $orig_height) = getimagesize($full_source_path);
        
        // Create image resource
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = imagecreatefromjpeg($full_source_path);
                break;
            case 'png':
                $source = imagecreatefrompng($full_source_path);
                break;
            case 'gif':
                $source = imagecreatefromgif($full_source_path);
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
        $full_thumbnail_path = $this->project_root . '/' . $thumbnail_path;
        
        // Save
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnail, $full_thumbnail_path, 90);
                break;
            case 'png':
                imagepng($thumbnail, $full_thumbnail_path, 9);
                break;
            case 'gif':
                imagegif($thumbnail, $full_thumbnail_path);
                break;
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        // Log thumbnail creation
        if ($this->db) {
            logActivity($this->db, 'thumbnail_created', 
                "Thumbnail created: {$thumbnail_path} from source: {$source_path}");
        }
        
        return $thumbnail_path;
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
}