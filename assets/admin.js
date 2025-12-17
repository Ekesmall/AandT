(function ($) {
    'use strict';

    const AmeliaTutorMapping = {
        
        init: function() {
            this.bindEvents();
            this.loadMappings();
        },

        bindEvents: function() {
            // Add new mapping row
            $(document).on('click', '#ameliatutor-add-mapping', this.addMappingRow.bind(this));
            
            // Remove mapping row
            $(document).on('click', '.ameliatutor-remove-mapping', this.removeMappingRow.bind(this));
            
            // Course selection change - load lessons
            $(document).on('change', '.ameliatutor-course-select', this.loadLessons.bind(this));
            
            // Save mappings
            $(document).on('click', '#ameliatutor-save-mappings', this.saveMappings.bind(this));
        },

        loadMappings: function() {
            const existingMappings = AmeliaTutor.existing_mappings || {};
            const mappingsContainer = $('#ameliatutor-mappings-container');
            
            if (Object.keys(existingMappings).length === 0) {
                // Show empty state
                mappingsContainer.html('<p class="ameliatutor-empty-state">No mappings configured yet. Click "Add Mapping" to create your first mapping.</p>');
                return;
            }

            mappingsContainer.empty();
            
            // Render existing mappings
            $.each(existingMappings, function(serviceId, mapping) {
                AmeliaTutorMapping.addMappingRow(null, {
                    serviceId: serviceId,
                    courseId: mapping.course_id,
                    lessonId: mapping.lesson_id
                });
            });
        },

        addMappingRow: function(e, existingData = null) {
            if (e) e.preventDefault();

            const container = $('#ameliatutor-mappings-container');
            
            // Remove empty state if exists
            container.find('.ameliatutor-empty-state').remove();

            const services = AmeliaTutor.services || [];
            const courses = AmeliaTutor.courses || [];

            const rowHtml = `
                <div class="ameliatutor-mapping-row">
                    <div class="ameliatutor-mapping-field">
                        <label>Amelia Service</label>
                        <select class="ameliatutor-service-select" name="mapping_service[]" required>
                            <option value="">Select Service...</option>
                            ${services.map(service => `
                                <option value="${service.id}" ${existingData && existingData.serviceId == service.id ? 'selected' : ''}>
                                    ${service.name}
                                </option>
                            `).join('')}
                        </select>
                    </div>

                    <div class="ameliatutor-mapping-arrow">→</div>

                    <div class="ameliatutor-mapping-field">
                        <label>TutorLMS Course</label>
                        <select class="ameliatutor-course-select" name="mapping_course[]" required>
                            <option value="">Select Course...</option>
                            ${courses.map(course => `
                                <option value="${course.id}" ${existingData && existingData.courseId == course.id ? 'selected' : ''}>
                                    ${course.title}
                                </option>
                            `).join('')}
                        </select>
                    </div>

                    <div class="ameliatutor-mapping-arrow">→</div>

                    <div class="ameliatutor-mapping-field">
                        <label>TutorLMS Lesson</label>
                        <select class="ameliatutor-lesson-select" name="mapping_lesson[]">
                            <option value="">Select Lesson...</option>
                        </select>
                    </div>

                    <div class="ameliatutor-mapping-actions">
                        <button type="button" class="button ameliatutor-remove-mapping" title="Remove Mapping">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;

            container.append(rowHtml);

            // If existing data, load lessons for the selected course
            if (existingData && existingData.courseId) {
                const newRow = container.find('.ameliatutor-mapping-row:last');
                this.loadLessonsForRow(newRow, existingData.courseId, existingData.lessonId);
            }
        },

        removeMappingRow: function(e) {
            e.preventDefault();
            
            const row = $(e.currentTarget).closest('.ameliatutor-mapping-row');
            
            if (confirm('Are you sure you want to remove this mapping?')) {
                row.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Show empty state if no mappings left
                    if ($('#ameliatutor-mappings-container .ameliatutor-mapping-row').length === 0) {
                        $('#ameliatutor-mappings-container').html(
                            '<p class="ameliatutor-empty-state">No mappings configured yet. Click "Add Mapping" to create your first mapping.</p>'
                        );
                    }
                });
            }
        },

        loadLessons: function(e) {
            const $select = $(e.currentTarget);
            const courseId = $select.val();
            const row = $select.closest('.ameliatutor-mapping-row');
            
            this.loadLessonsForRow(row, courseId);
        },

        loadLessonsForRow: function(row, courseId, selectedLessonId = null) {
            if (!courseId) {
                row.find('.ameliatutor-lesson-select').html('<option value="">Select Course First...</option>');
                return;
            }

            const lessonSelect = row.find('.ameliatutor-lesson-select');
            lessonSelect.html('<option value="">Loading lessons...</option>').prop('disabled', true);

            $.ajax({
                url: AmeliaTutor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ameliatutor_get_lessons',
                    nonce: AmeliaTutor.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        let options = '<option value="">None (Complete course only)</option>';
                        
                        response.data.forEach(function(lesson) {
                            const selected = selectedLessonId && selectedLessonId == lesson.id ? 'selected' : '';
                            options += `<option value="${lesson.id}" ${selected}>${lesson.title}</option>`;
                        });
                        
                        lessonSelect.html(options).prop('disabled', false);
                    } else {
                        lessonSelect.html('<option value="">No lessons found</option>').prop('disabled', false);
                    }
                },
                error: function() {
                    lessonSelect.html('<option value="">Error loading lessons</option>').prop('disabled', false);
                    alert('Failed to load lessons. Please try again.');
                }
            });
        },

        saveMappings: function(e) {
            e.preventDefault();

            const button = $(e.currentTarget);
            const originalText = button.text();
            
            // Collect all mappings
            const mappings = {};
            let isValid = true;

            $('#ameliatutor-mappings-container .ameliatutor-mapping-row').each(function() {
                const serviceId = $(this).find('.ameliatutor-service-select').val();
                const courseId = $(this).find('.ameliatutor-course-select').val();
                const lessonId = $(this).find('.ameliatutor-lesson-select').val();

                if (!serviceId || !courseId) {
                    isValid = false;
                    return false; // break loop
                }

                mappings[serviceId] = {
                    course_id: courseId,
                    lesson_id: lessonId || ''
                };
            });

            if (!isValid) {
                alert('Please select both a service and course for all mappings.');
                return;
            }

            // Disable button and show loading
            button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: AmeliaTutor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ameliatutor_save_mappings',
                    nonce: AmeliaTutor.nonce,
                    mappings: mappings
                },
                success: function(response) {
                    if (response.success) {
                        button.text('✓ Saved!').addClass('button-primary');
                        
                        setTimeout(function() {
                            button.text(originalText).removeClass('button-primary').prop('disabled', false);
                        }, 2000);
                    } else {
                        alert('Failed to save mappings. Please try again.');
                        button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while saving. Please try again.');
                    button.text(originalText).prop('disabled', false);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#ameliatutor-mapping-app').length) {
            AmeliaTutorMapping.init();
        }
    });

})(jQuery);