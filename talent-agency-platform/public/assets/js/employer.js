// public/assets/js/employer.js
// Handles all employer-side interactions:
// post-job, edit-job, jobs list, job-detail (application management), profile

$(function () {

    // =========================================================
    // SHARED: Location type toggle (show/hide address field)
    // =========================================================
    function initLocationToggle() {
        const $locType = $('#location_type');
        const $wrapper = $('#location_address_wrapper');

        function toggle(val) {
            if (val === 'remote') {
                $wrapper.slideUp();
            } else {
                $wrapper.slideDown();
            }
        }

        $locType.on('change', function () { toggle($(this).val()); });
        if ($locType.length) toggle($locType.val());
    }
    initLocationToggle();


    // =========================================================
    // SHARED: Skill selector (post-job & edit-job pages)
    // =========================================================
    let selectedSkills = []; // [{id, name, required}]

    function initSkillSelector() {
        const $container = $('#skillsContainer');
        if (!$container.length) return;

        // Preload skills for edit-job page
        if (window.preloadedSkills && window.preloadedSkills.length) {
            selectedSkills = window.preloadedSkills;
            renderSelectedSkills();
            syncSkillButtons();
        }

        // Click skill option button
        $(document).on('click', '.skill-option-btn', function () {
            const id = parseInt($(this).data('skill-id'));
            const name = $(this).data('skill-name');

            const idx = selectedSkills.findIndex(s => s.id === id);
            if (idx === -1) {
                selectedSkills.push({ id, name, required: 1 });
            } else {
                selectedSkills.splice(idx, 1);
            }
            renderSelectedSkills();
            syncSkillButtons();
        });

        // Toggle required on selected skill tag
        $(document).on('click', '.skill-tag-toggle', function () {
            const id = parseInt($(this).data('skill-id'));
            const skill = selectedSkills.find(s => s.id === id);
            if (skill) {
                skill.required = skill.required ? 0 : 1;
                renderSelectedSkills();
            }
        });

        // Remove selected skill tag
        $(document).on('click', '.skill-tag-remove', function (e) {
            e.stopPropagation();
            const id = parseInt($(this).data('skill-id'));
            selectedSkills = selectedSkills.filter(s => s.id !== id);
            renderSelectedSkills();
            syncSkillButtons();
        });

        // Skill search filter
        $('#skillSearchInput').on('input', function () {
            const q = $(this).val().toLowerCase();
            $('.skill-category').each(function () {
                let visible = 0;
                $(this).find('.skill-option-btn').each(function () {
                    const match = $(this).data('skill-name').toLowerCase().includes(q);
                    $(this).toggle(match);
                    if (match) visible++;
                });
                $(this).toggle(visible > 0);
            });
        });
    }

    function renderSelectedSkills() {
        const $preview = $('#selectedSkillsPreview');
        $('#noSkillsHint').toggle(selectedSkills.length === 0);
        $preview.find('.skill-tag').remove();

        selectedSkills.forEach(function (s) {
            const tagClass = s.required ? 'bg-primary text-white' : 'bg-warning text-dark';
            const $tag = $(`
                <span class="skill-tag badge ${tagClass} d-inline-flex align-items-center gap-1 p-2" style="cursor:pointer">
                    <span class="skill-tag-toggle" data-skill-id="${s.id}" title="Toggle required">
                        ${escapeHtml(s.name)}
                        <small class="opacity-75">${s.required ? '★' : '☆'}</small>
                    </span>
                    <button type="button" class="btn-close btn-close-${s.required ? 'white' : ''} skill-tag-remove"
                            data-skill-id="${s.id}" style="font-size:0.6rem"></button>
                </span>
            `);
            $preview.append($tag);
        });

        // Update hidden input
        $('#skills_json').val(JSON.stringify(selectedSkills));
    }

    function syncSkillButtons() {
        const selectedIds = selectedSkills.map(s => s.id);
        $('.skill-option-btn').each(function () {
            const id = parseInt($(this).data('skill-id'));
            if (selectedIds.includes(id)) {
                $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
            } else {
                $(this).removeClass('btn-primary').addClass('btn-outline-secondary');
            }
        });
    }

    initSkillSelector();


    // =========================================================
    // POST JOB FORM
    // =========================================================
    $('#postJobForm').on('submit', function (e) {
        e.preventDefault();
        submitJobForm($(this), false);
    });

    $('#saveDraftBtn').on('click', function () {
        submitJobForm($('#postJobForm'), true);
    });


    // =========================================================
    // EDIT JOB FORM
    // =========================================================
    $('#editJobForm').on('submit', function (e) {
        e.preventDefault();

        const jobId = $(this).data('job-id');
        const data = buildJobPayload($(this));
        data.id = jobId;

        const $btn = $('#updateJobBtn');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: SITE_URL + '/api/jobs.php?action=update',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(() => {
                        window.location.href = SITE_URL + '/public/employer/jobs.php';
                    }, 1500);
                } else {
                    showFormErrors(res.errors);
                    showToast(res.message, 'danger');
                }
            },
            error: function () {
                showToast('Failed to update job. Please try again.', 'danger');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save & Re-submit');
            }
        });
    });


    // =========================================================
    // CLOSE JOB (jobs list + job-detail)
    // =========================================================
    $(document).on('click', '.close-job-btn', function () {
        const jobId = $(this).data('job-id');
        const title = $(this).data('job-title') || 'this job';

        if (!confirm(`Are you sure you want to close "${title}"? This cannot be undone.`)) return;

        $.ajax({
            url: SITE_URL + '/api/jobs.php?action=delete',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: jobId }),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast(res.message, 'danger');
                }
            },
            error: function () {
                showToast('Failed to close job.', 'danger');
            }
        });
    });


    // =========================================================
    // MARK JOB AS FILLED (job-detail page)
    // =========================================================
    $(document).on('click', '.mark-filled-btn', function () {
        const jobId = $(this).data('job-id');
        if (!confirm('Mark this position as filled? The job will be removed from active listings.')) return;

        $.ajax({
            url: SITE_URL + '/api/jobs.php?action=update_status',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: jobId, status: 'filled' }),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast(res.message, 'danger');
                }
            }
        });
    });


    // =========================================================
    // APPLICATION STATUS UPDATE (job-detail)
    // =========================================================
    $(document).on('click', '.update-app-btn', function () {
        const $btn = $(this);
        const appId = $btn.data('app-id');
        const status = $btn.data('status');

        const labels = { shortlisted: 'shortlist', accepted: 'accept', rejected: 'reject' };
        if (!confirm('Are you sure you want to ' + (labels[status] || status) + ' this applicant?')) return;

        $btn.prop('disabled', true);

        $.ajax({
            url: SITE_URL + '/api/applications.php?action=update_status',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: appId, status: status }),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast(res.message, 'danger');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                showToast('Failed to update application.', 'danger');
                $btn.prop('disabled', false);
            }
        });
    });


    // =========================================================
    // APPLICATION FILTER TABS (job-detail)
    // =========================================================
    $('#appFilterTabs').on('click', 'button', function () {
        $('#appFilterTabs button').removeClass('active');
        $(this).addClass('active');

        const filter = $(this).data('filter');

        $('.application-row').each(function () {
            if (filter === 'all' || $(this).data('status') === filter) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });


    // =========================================================
    // COVER LETTER READ MORE (job-detail)
    // =========================================================
    $(document).on('click', '.read-more-link', function (e) {
        e.preventDefault();
        const full = $(this).data('full');
        $('#coverLetterContent').text(full);
        const modal = new bootstrap.Modal(document.getElementById('coverLetterModal'));
        modal.show();
    });


    // =========================================================
    // COMPANY PROFILE FORM
    // =========================================================
    $('#companyProfileForm').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#saveProfileBtn');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: SITE_URL + '/api/employers.php?action=update',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                } else {
                    showFormErrors(res.errors);
                    showToast(res.message, 'danger');
                }
            },
            error: function () {
                showToast('Failed to update profile.', 'danger');
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Changes');
            }
        });
    });


    // =========================================================
    // LOGO UPLOAD
    // =========================================================
    $('#uploadLogoForm').on('submit', function (e) {
        e.preventDefault();

        const file = $('#companyLogo')[0].files[0];
        if (!file) {
            showToast('Please select a file first.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('company_logo', file);

        $.ajax({
            url: SITE_URL + '/api/employers.php?action=upload_logo',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    $('#logoPreview').attr('src', res.logo_url + '?t=' + Date.now()).removeClass('d-none');
                    $('#logoPlaceholder').hide();
                } else {
                    showToast(res.message, 'danger');
                }
            },
            error: function () {
                showToast('Upload failed.', 'danger');
            }
        });
    });

    // Preview logo before upload
    $('#companyLogo').on('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#logoPreview').attr('src', e.target.result).removeClass('d-none');
                $('#logoPlaceholder').hide();
            };
            reader.readAsDataURL(file);
        }
    });


    // =========================================================
    // HELPERS
    // =========================================================

    function submitJobForm($form, isDraft) {
        const data = buildJobPayload($form);

        if (isDraft) {
            data._draft = true;
        }

        const $btn = isDraft ? $('#saveDraftBtn') : $('#submitJobBtn');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (isDraft ? 'Saving...' : 'Submitting...'));

        $.ajax({
            url: SITE_URL + '/api/jobs.php?action=create',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    setTimeout(() => {
                        window.location.href = SITE_URL + '/public/employer/jobs.php';
                    }, 1500);
                } else {
                    showFormErrors(res.errors);
                    showToast(res.message || 'Failed to post job.', 'danger');
                }
            },
            error: function () {
                showToast('Failed to submit job. Please try again.', 'danger');
            },
            complete: function () {
                $btn.prop('disabled', false);
                if (isDraft) {
                    $btn.html('<i class="fas fa-save"></i> Save as Draft');
                } else {
                    $btn.html('<i class="fas fa-paper-plane"></i> Submit for Review');
                }
            }
        });
    }

    function buildJobPayload($form) {
        const skillsJson = $('#skills_json').val();
        let skills = [];
        try { skills = JSON.parse(skillsJson); } catch (e) {}

        const data = {};
        $form.serializeArray().forEach(function (item) {
            if (item.name !== 'skills_json') {
                data[item.name] = item.value;
            }
        });
        data.skills = skills;
        return data;
    }

    function showFormErrors(errors) {
        if (!errors) return;
        // Clear previous errors
        $('.invalid-feedback').text('');
        $('.form-control, .form-select').removeClass('is-invalid');

        Object.keys(errors).forEach(function (field) {
            const $input = $('[name="' + field + '"]');
            const $error = $('#' + field + '-error');
            $input.addClass('is-invalid');
            $error.text(errors[field]);
        });
    }

    function showToast(message, type) {
        type = type || 'info';
        const bgClass = {
            success: 'bg-success',
            danger: 'bg-danger',
            warning: 'bg-warning text-dark',
            info: 'bg-info',
        }[type] || 'bg-secondary';

        const id = 'toast-' + Date.now();
        const $toast = $(`
            <div id="${id}" class="toast align-items-center text-white ${bgClass} border-0"
                 role="alert" style="min-width:280px">
                <div class="d-flex">
                    <div class="toast-body">${escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);

        let $container = $('#toast-container');
        if (!$container.length) {
            $container = $('<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999"></div>');
            $('body').append($container);
        }
        $container.append($toast);

        const bsToast = new bootstrap.Toast(document.getElementById(id), { delay: 4000 });
        bsToast.show();
        $toast.on('hidden.bs.toast', function () { $(this).remove(); });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Make showToast available globally
    window.showEmployerToast = showToast;

});
