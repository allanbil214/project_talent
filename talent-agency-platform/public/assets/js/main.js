// public/assets/js/main.js

$(document).ready(function() {
    
    // Sidebar Toggle (Mobile)
    $('#sidebarToggle').on('click', function() {
        $('.sidebar').toggleClass('active');
    });
    
    // Close sidebar when clicking outside (mobile)
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sidebar, #sidebarToggle').length) {
            $('.sidebar').removeClass('active');
        }
    });
    
    // Password Toggle
    $('.btn-group input[type="radio"]').on('change', function() {
        const role = $(this).val();
        $('#selectedRole').val(role);
        
        if (role === 'talent') {
            $('#talentFields').show();
            $('#employerFields').hide();
            $('#full_name').prop('required', true);
            $('#company_name').prop('required', false);
        } else {
            $('#talentFields').hide();
            $('#employerFields').show();
            $('#full_name').prop('required', false);
            $('#company_name').prop('required', true);
        }
    });
    
    // Toggle Password Visibility
    $('#togglePassword').on('click', function() {
        const passwordInput = $('#password');
        const icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Auto-hide alerts after 5 seconds
    $('.alert').delay(5000).fadeOut(300);
    
    // Confirm delete actions
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Notification Bell
    $('#notificationBell').on('click', function(e) {
        e.preventDefault();
        // Load notifications via AJAX
        loadNotifications();
    });
    
    // Load notifications
    function loadNotifications() {
        // This will be implemented with actual API call
        console.log('Loading notifications...');
    }
    
    // Auto-update notification badge
    function updateNotificationBadge() {
        $.ajax({
            url: '/talent-agency-platform/api/notifications.php?action=get_unread_count',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.count > 0) {
                    $('#notification-badge').text(response.count).show();
                } else {
                    $('#notification-badge').hide();
                }
            }
        });
    }
    
    // Check notifications every 30 seconds if logged in
    if ($('#notification-badge').length) {
        updateNotificationBadge();
        setInterval(updateNotificationBadge, 30000);
    }
    
    // Form validation helper
    window.showFieldError = function(field, message) {
        const input = $(`#${field}`);
        const errorDiv = $(`#${field}-error`);
        
        input.addClass('is-invalid');
        errorDiv.text(message);
    };
    
    window.clearFieldError = function(field) {
        const input = $(`#${field}`);
        const errorDiv = $(`#${field}-error`);
        
        input.removeClass('is-invalid');
        errorDiv.text('');
    };
    
    window.clearAllErrors = function() {
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    };
    
    // Loading button state
    window.setButtonLoading = function(buttonId, loading = true) {
        const button = $(`#${buttonId}`);
        
        if (loading) {
            button.prop('disabled', true);
            button.data('original-text', button.html());
            button.html('<span class="spinner-border spinner-border-sm me-2"></span>Loading...');
        } else {
            button.prop('disabled', false);
            button.html(button.data('original-text'));
        }
    };
    
    // Show success toast
    window.showToast = function(message, type = 'success') {
        const icon = type === 'success' ? 'success' : 'error';
        const title = type === 'success' ? 'Success' : 'Error';
        
        Swal.fire({
            icon: icon,
            title: title,
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    };
    
    // Confirm dialog
    window.confirmAction = function(message, callback) {
        Swal.fire({
            title: 'Are you sure?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed && typeof callback === 'function') {
                callback();
            }
        });
    };
    
    // File input preview
    $('input[type="file"]').on('change', function(e) {
        const file = e.target.files[0];
        const previewId = $(this).data('preview');
        
        if (file && previewId) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                $(`#${previewId}`).attr('src', e.target.result).show();
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Search with debounce
    let searchTimeout;
    $('.search-input').on('keyup', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val();
        const searchCallback = $(this).data('search-callback');
        
        searchTimeout = setTimeout(function() {
            if (typeof window[searchCallback] === 'function') {
                window[searchCallback](searchTerm);
            }
        }, 500);
    });
    
    // Number formatting
    window.formatCurrency = function(amount, currency = 'IDR') {
        if (currency === 'IDR') {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };
    
    // Date formatting
    window.formatDate = function(dateString) {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(date);
    };
    
    // Time ago
    window.timeAgo = function(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        let interval = Math.floor(seconds / 31536000);
        if (interval >= 1) return interval + ' year' + (interval > 1 ? 's' : '') + ' ago';
        
        interval = Math.floor(seconds / 2592000);
        if (interval >= 1) return interval + ' month' + (interval > 1 ? 's' : '') + ' ago';
        
        interval = Math.floor(seconds / 86400);
        if (interval >= 1) return interval + ' day' + (interval > 1 ? 's' : '') + ' ago';
        
        interval = Math.floor(seconds / 3600);
        if (interval >= 1) return interval + ' hour' + (interval > 1 ? 's' : '') + ' ago';
        
        interval = Math.floor(seconds / 60);
        if (interval >= 1) return interval + ' minute' + (interval > 1 ? 's' : '') + ' ago';
        
        return 'just now';
    };
    
});