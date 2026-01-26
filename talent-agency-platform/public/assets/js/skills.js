// public/assets/js/skills.js

$(document).ready(function() {
    
    let addSkillModal;
    
    // Initialize modal
    if (document.getElementById('addSkillModal')) {
        addSkillModal = new bootstrap.Modal(document.getElementById('addSkillModal'));
    }
    
    // Add Skill Button Click
    $(document).on('click', '.add-skill-btn:not(.disabled)', function() {
        const skillId = $(this).data('skill-id');
        const skillName = $(this).data('skill-name');
        
        $('#selectedSkillId').val(skillId);
        $('#selectedSkillName').val(skillName);
        
        addSkillModal.show();
    });
    
    // Confirm Add Skill
    $('#confirmAddSkill').on('click', function() {
        const skillId = $('#selectedSkillId').val();
        const proficiencyLevel = $('select[name="proficiency_level"]').val();
        
        if (!skillId || !proficiencyLevel) {
            showToast('Please fill all fields', 'error');
            return;
        }
        
        $.ajax({
            url: '/talent-agency-platform/api/talents.php?action=add_skill',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                skill_id: skillId,
                proficiency_level: proficiencyLevel
            }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    addSkillModal.hide();
                    // Reload page to update lists
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function(xhr) {
                showToast('Failed to add skill. Please try again.', 'error');
            }
        });
    });
    
    // Remove Skill
    $(document).on('click', '.remove-skill-btn', function() {
        const skillId = $(this).data('skill-id');
        const skillItem = $(this).closest('.skill-item');
        
        confirmAction('Are you sure you want to remove this skill?', function() {
            $.ajax({
                url: '/talent-agency-platform/api/talents.php?action=remove_skill',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    skill_id: skillId
                }),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        skillItem.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Re-enable add button for this skill
                            $(`.add-skill-btn[data-skill-id="${skillId}"]`)
                                .removeClass('btn-success disabled')
                                .addClass('btn-outline-primary')
                                .prop('disabled', false)
                                .html('<i class="fas fa-plus"></i> ' + 
                                      $(`.add-skill-btn[data-skill-id="${skillId}"]`).data('skill-name'));
                            
                            // Check if no skills left
                            if ($('.skill-item').length === 0) {
                                $('#mySkillsList').html(`
                                    <div class="empty-state">
                                        <i class="fas fa-award"></i>
                                        <p>No skills added yet</p>
                                        <p class="small text-muted">Add skills from the list to improve your profile</p>
                                    </div>
                                `);
                            }
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function(xhr) {
                    showToast('Failed to remove skill. Please try again.', 'error');
                }
            });
        });
    });
    
    // Update Proficiency Level
    $(document).on('change', '.proficiency-select', function() {
        const skillId = $(this).data('skill-id');
        const proficiencyLevel = $(this).val();
        
        $.ajax({
            url: '/talent-agency-platform/api/talents.php?action=update_skill',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                skill_id: skillId,
                proficiency_level: proficiencyLevel
            }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Proficiency level updated', 'success');
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function(xhr) {
                showToast('Failed to update proficiency. Please try again.', 'error');
            }
        });
    });
    
    // Add Custom Skill
    $('#addCustomSkillForm').on('submit', function(e) {
        e.preventDefault();
        
        const skillName = $('#customSkillName').val();
        const category = $('#customSkillCategory').val();
        
        if (!skillName) {
            showToast('Please enter skill name', 'error');
            return;
        }
        
        $.ajax({
            url: '/talent-agency-platform/api/skills.php?action=create',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                name: skillName,
                category: category
            }),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Custom skill created successfully', 'success');
                    // Reload page to show new skill
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error('Full error:', xhr); // ADD THIS
                console.error('Response text:', xhr.responseText); // ADD THIS
                showToast('Failed to create skill. Please try again.', 'error');
            }
        });
    });
    
    // Search Skills
    let searchTimeout;
    $('#skillSearch').on('keyup', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val().toLowerCase();
        
        searchTimeout = setTimeout(function() {
            if (searchTerm === '') {
                $('.skill-category').show();
                $('.add-skill-btn').show();
            } else {
                $('.skill-category').each(function() {
                    let hasVisibleSkills = false;
                    
                    $(this).find('.add-skill-btn').each(function() {
                        const skillName = $(this).data('skill-name').toLowerCase();
                        
                        if (skillName.includes(searchTerm)) {
                            $(this).show();
                            hasVisibleSkills = true;
                        } else {
                            $(this).hide();
                        }
                    });
                    
                    if (hasVisibleSkills) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        }, 300);
    });
    
});