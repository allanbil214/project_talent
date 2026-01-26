// public/assets/js/profile.js

$(document).ready(function() {
    
    // Get base URL from script location
    const getApiUrl = (endpoint) => {
        const path = window.location.pathname;
        const base = path.substring(0, path.indexOf('/public/'));
        return base + '/api/' + endpoint;
    };
    
    // Profile Form Submission
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        setButtonLoading('saveProfileBtn', true);
        
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => {
            if (key === 'preferred_work_type[]') {
                if (!data['preferred_work_type']) {
                    data['preferred_work_type'] = [];
                }
                data['preferred_work_type'].push(value);
            } else {
                data[key] = value;
            }
        });
        
        $.ajax({
            url: getApiUrl('talents.php?action=update'),
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                } else {
                    if (response.errors) {
                        for (let field in response.errors) {
                            showFieldError(field, response.errors[field][0]);
                        }
                    } else {
                        showToast(response.message, 'error');
                    }
                }
            },
            error: function(xhr) {
                console.error('Profile update error:', xhr);
                showToast('Failed to update profile. Please try again.', 'error');
            },
            complete: function() {
                setButtonLoading('saveProfileBtn', false);
            }
        });
    });
    
    // Upload Profile Photo
    $('#uploadPhotoForm').on('submit', function(e) {
        e.preventDefault();
        
        const fileInput = $('#profilePhoto')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            showToast('Please select a photo', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('profile_photo', fileInput.files[0]);
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
        
        $.ajax({
            url: getApiUrl('talents.php?action=upload_photo'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Photo upload response:', response);
                if (response.success) {
                    $('#profilePhotoPreview').attr('src', response.photo_url);
                    showToast(response.message, 'success');
                    fileInput.value = '';
                } else {
                    showToast(response.message || 'Failed to upload photo', 'error');
                }
            },
            error: function(xhr) {
                console.error('Photo upload error:', xhr);
                let errorMsg = 'Failed to upload photo. Please try again.';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 413) {
                    errorMsg = 'File is too large. Maximum size is 5MB.';
                } else if (xhr.status === 0) {
                    errorMsg = 'Network error. Please check your connection.';
                }
                
                showToast(errorMsg, 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Preview photo before upload
    $('#profilePhoto').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file size (5MB)
            if (file.size > 5242880) {
                showToast('File is too large. Maximum size is 5MB.', 'error');
                this.value = '';
                return;
            }
            
            // Validate file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                showToast('Invalid file type. Please select a JPG, PNG, or GIF image.', 'error');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#profilePhotoPreview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Upload Resume
    $('#uploadResumeForm').on('submit', function(e) {
        e.preventDefault();
        
        const fileInput = $('#resume')[0];
        
        if (!fileInput.files || !fileInput.files[0]) {
            showToast('Please select a resume file', 'error');
            return;
        }
        
        // Validate file type
        const file = fileInput.files[0];
        const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!validTypes.includes(file.type)) {
            showToast('Invalid file type. Please select a PDF or Word document.', 'error');
            return;
        }
        
        // Validate file size (10MB)
        if (file.size > 10485760) {
            showToast('File is too large. Maximum size is 10MB.', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('resume', file);
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
        
        $.ajax({
            url: getApiUrl('talents.php?action=upload_resume'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Resume upload response:', response);
                if (response.success) {
                    showToast(response.message, 'success');
                    fileInput.value = '';
                    // Reload page to show new resume
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message || 'Failed to upload resume', 'error');
                }
            },
            error: function(xhr) {
                console.error('Resume upload error:', xhr);
                let errorMsg = 'Failed to upload resume. Please try again.';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 413) {
                    errorMsg = 'File is too large. Maximum size is 10MB.';
                } else if (xhr.status === 0) {
                    errorMsg = 'Network error. Please check your connection.';
                }
                
                showToast(errorMsg, 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
});