// public/assets/js/dashboard.js

$(document).ready(function() {
    
    // Auto-refresh stats every 30 seconds (optional)
    function refreshStats() {
        // This can be implemented if you want live updates
        // For now, stats are loaded from PHP on page load
    }
    
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Smooth scroll to sections
    $('a[href^="#"]').on('click', function(e) {
        const target = $(this.getAttribute('href'));
        if (target.length) {
            e.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 100
            }, 500);
        }
    });
    
    // Profile completion animation
    const progressBar = $('.progress-bar');
    if (progressBar.length) {
        const targetWidth = progressBar.attr('aria-valuenow');
        progressBar.css('width', '0%');
        
        setTimeout(() => {
            progressBar.animate({
                width: targetWidth + '%'
            }, 1000);
        }, 300);
    }
    
    // Job card hover effects
    $('.job-card').hover(
        function() {
            $(this).addClass('shadow-sm');
        },
        function() {
            $(this).removeClass('shadow-sm');
        }
    );
    
    // Quick apply functionality (if needed in future)
    $(document).on('click', '.quick-apply-btn', function(e) {
        e.preventDefault();
        const jobId = $(this).data('job-id');
        
        Swal.fire({
            title: 'Quick Apply',
            text: 'Do you want to apply to this job with your current profile?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, apply now',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                applyToJob(jobId);
            }
        });
    });
    
    function applyToJob(jobId) {
        $.ajax({
            url: '/talent-agency-platform/api/applications.php?action=create',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                job_id: jobId
            }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Your application has been submitted',
                        timer: 2000
                    }).then(() => {
                        location.reload();
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
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to submit application. Please try again.'
                });
            }
        });
    }
    
    // Notification polling (optional - for real-time updates)
    function checkNotifications() {
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
            },
            error: function() {
                // Silently fail
            }
        });
    }
    
    // Check notifications on page load
    checkNotifications();
    
    // Auto-refresh notifications every 30 seconds
    setInterval(checkNotifications, 30000);
    
    // Mark notification as read
    $(document).on('click', '.notification-item', function() {
        const notificationId = $(this).data('notification-id');
        
        if (notificationId) {
            $.ajax({
                url: '/talent-agency-platform/api/notifications.php?action=mark_read',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    id: notificationId
                }),
                dataType: 'json',
                success: function() {
                    checkNotifications();
                }
            });
        }
    });
    
    // Animate stat cards on scroll
    function animateStats() {
        $('.stat-card').each(function(index) {
            const $this = $(this);
            
            if (isElementInViewport($this[0])) {
                setTimeout(() => {
                    $this.addClass('fade-in');
                }, index * 100);
            }
        });
    }
    
    function isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    // Trigger animation on scroll
    $(window).on('scroll', animateStats);
    animateStats(); // Initial check
    
    // Status badge click - show details
    $(document).on('click', '.badge', function(e) {
        const status = $(this).text().trim();
        const statusDescriptions = {
            'Pending': 'Your application is under review by the employer.',
            'Reviewed': 'Your application has been reviewed by the employer.',
            'Shortlisted': 'Congratulations! You have been shortlisted for this position.',
            'Accepted': 'Your application has been accepted! Check your contracts.',
            'Rejected': 'Unfortunately, your application was not successful this time.'
        };
        
        if (statusDescriptions[status]) {
            const tooltip = new bootstrap.Tooltip(this, {
                title: statusDescriptions[status],
                trigger: 'manual'
            });
            tooltip.show();
            
            setTimeout(() => {
                tooltip.hide();
            }, 3000);
        }
    });
    
});