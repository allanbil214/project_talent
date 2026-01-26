// public/assets/js/profile.js

$(document).ready(function() {
    
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
            url: '/talent-agency-platform/api/talents.php?action=update',
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
        
        $.ajax({
            url: '/talent-agency-platform/api/talents.php?action=upload_photo',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#profilePhotoPreview').attr('src', response.photo_url);
                    showToast(response.message, 'success');
                    fileInput.value = '';
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function(xhr) {
                showToast('Failed to upload photo. Please try again.', 'error');
            }
        });
    });
    
    // Preview photo before upload
    $('#profilePhoto').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
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
        
        const formData = new FormData();
        formData.append('resume', fileInput.files[0]);
        
        $.ajax({
            url: '/talent-agency-platform/api/talents.php?action=upload_resume',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    fileInput.value = '';
                    // Reload page to show new resume
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function(xhr) {
                showToast('Failed to upload resume. Please try again.', 'error');
            }
        });
    });
    
});