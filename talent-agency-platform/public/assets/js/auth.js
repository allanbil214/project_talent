// public/assets/js/auth.js

$(document).ready(function() {
    
    // Login Form
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        setButtonLoading('loginBtn', true);
        
        const formData = {
            email: $('#email').val(),
            password: $('#password').val()
        };
        
        $.ajax({
            url: '/talent-agency-platform/api/auth.php?action=login',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful',
                        text: 'Redirecting to dashboard...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = response.redirect;
                    });
                } else {
                    setButtonLoading('loginBtn', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                setButtonLoading('loginBtn', false);
                
                if (xhr.responseJSON) {
                    const response = xhr.responseJSON;
                    
                    if (response.errors) {
                        // Show field-specific errors
                        $.each(response.errors, function(field, errors) {
                            showFieldError(field, errors[0]);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Login Failed',
                            text: response.message || 'An error occurred. Please try again.'
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error. Please try again.'
                    });
                }
            }
        });
    });
    
    // Register Form
    $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        setButtonLoading('registerBtn', true);
        
        const formData = {
            role: $('#selectedRole').val(),
            email: $('#email').val(),
            password: $('#password').val(),
            password_confirmation: $('#password_confirmation').val()
        };
        
        // Add role-specific fields
        if (formData.role === 'talent') {
            formData.full_name = $('#full_name').val();
        } else if (formData.role === 'employer') {
            formData.company_name = $('#company_name').val();
        }
        
        // Validate password match
        if (formData.password !== formData.password_confirmation) {
            showFieldError('password_confirmation', 'Passwords do not match');
            setButtonLoading('registerBtn', false);
            return;
        }
        
        $.ajax({
            url: '/talent-agency-platform/api/auth.php?action=register',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registration Successful',
                        text: 'Welcome! Redirecting to dashboard...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = response.redirect;
                    });
                } else {
                    setButtonLoading('registerBtn', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Registration Failed',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                setButtonLoading('registerBtn', false);
                
                if (xhr.responseJSON) {
                    const response = xhr.responseJSON;
                    
                    if (response.errors) {
                        // Show field-specific errors
                        $.each(response.errors, function(field, errors) {
                            showFieldError(field, errors[0]);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Registration Failed',
                            text: response.message || 'An error occurred. Please try again.'
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Connection error. Please try again.'
                    });
                }
            }
        });
    });
    
    // Forgot Password Form
    $('#forgotPasswordForm').on('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        setButtonLoading('forgotPasswordBtn', true);
        
        const formData = {
            email: $('#email').val()
        };
        
        $.ajax({
            url: '/talent-agency-platform/api/auth.php?action=forgot-password',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                setButtonLoading('forgotPasswordBtn', false);
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Sent',
                        text: response.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = '/public/login.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                setButtonLoading('forgotPasswordBtn', false);
                
                const response = xhr.responseJSON || {};
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'An error occurred. Please try again.'
                });
            }
        });
    });
    
    // Reset Password Form
    $('#resetPasswordForm').on('submit', function(e) {
        e.preventDefault();
        
        clearAllErrors();
        setButtonLoading('resetPasswordBtn', true);
        
        const formData = {
            email: $('#email').val(),
            token: $('#token').val(),
            password: $('#password').val(),
            password_confirmation: $('#password_confirmation').val()
        };
        
        // Validate password match
        if (formData.password !== formData.password_confirmation) {
            showFieldError('password_confirmation', 'Passwords do not match');
            setButtonLoading('resetPasswordBtn', false);
            return;
        }
        
        $.ajax({
            url: '/talent-agency-platform/api/auth.php?action=reset-password',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                setButtonLoading('resetPasswordBtn', false);
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Reset Successful',
                        text: response.message,
                        confirmButtonText: 'Login Now'
                    }).then(() => {
                        window.location.href = response.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                setButtonLoading('resetPasswordBtn', false);
                
                const response = xhr.responseJSON || {};
                
                if (response.errors) {
                    $.each(response.errors, function(field, errors) {
                        showFieldError(field, errors[0]);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'An error occurred. Please try again.'
                    });
                }
            }
        });
    });
    
    // Clear errors on input
    $('.form-control').on('input', function() {
        const fieldId = $(this).attr('id');
        clearFieldError(fieldId);
    });
    
});