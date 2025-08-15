/**
 * SEO Auto-Optimizer Admin JavaScript
 * Handles bulk optimization progress and UI interactions
 */

jQuery(document).ready(function($) {
    
    let bulkOptimizationActive = false;
    let currentOffset = 0;
    let totalPosts = 0;
    
    /**
     * Start bulk optimization process
     */
    $('#start-bulk-optimization').on('click', function() {
        if (bulkOptimizationActive) {
            return;
        }
        
        if (!confirm(seoAutoOptimizer.strings.confirm_bulk)) {
            return;
        }
        
        const postType = $('#post_type').val();
        const batchSize = parseInt($('#batch_size').val());
        const startDate = $('#start_date').val();
        const endDate = $('#end_date').val();
        
        // Validate inputs
        if (batchSize < 1 || batchSize > 100) {
            alert('Batch size must be between 1 and 100');
            return;
        }
        
        // Initialize progress
        bulkOptimizationActive = true;
        currentOffset = 0;
        totalPosts = 0;
        
        $('#start-bulk-optimization').prop('disabled', true).text(seoAutoOptimizer.strings.processing);
        $('#bulk-progress').show();
        $('#progress-fill').css('width', '0%');
        $('#progress-text').text('0%');
        $('#current-status').text('Initializing...');
        
        // Start processing
        processBatch(postType, batchSize, startDate, endDate);
    });
    
    /**
     * Process a single batch
     */
    function processBatch(postType, batchSize, startDate, endDate) {
        $.post(seoAutoOptimizer.ajax_url, {
            action: 'seo_auto_optimizer_bulk_process',
            nonce: seoAutoOptimizer.nonce,
            post_type: postType,
            batch_size: batchSize,
            offset: currentOffset,
            start_date: startDate,
            end_date: endDate
        })
        .done(function(response) {
            if (response.success) {
                totalPosts = response.total;
                const processed = response.processed;
                const progress = response.progress;
                
                // Update progress bar
                $('#progress-fill').css('width', progress + '%');
                $('#progress-text').text(progress + '%');
                $('#processed-count').text(processed);
                $('#total-count').text(totalPosts);
                $('#current-status').text(response.message);
                
                if (response.completed) {
                    // Optimization complete
                    bulkOptimizationComplete();
                } else {
                    // Continue with next batch
                    currentOffset = processed;
                    setTimeout(function() {
                        processBatch(postType, batchSize, startDate, endDate);
                    }, 1000); // Brief pause between batches
                }
            } else {
                bulkOptimizationError(response.data || 'Unknown error occurred');
            }
        })
        .fail(function(xhr, status, error) {
            bulkOptimizationError('AJAX request failed: ' + error);
        });
    }
    
    /**
     * Handle completion of bulk optimization
     */
    function bulkOptimizationComplete() {
        bulkOptimizationActive = false;
        $('#start-bulk-optimization').prop('disabled', false).text('Optimize Now');
        $('#progress-fill').css('width', '100%');
        $('#progress-text').text('100%');
        $('#current-status').html('<strong style="color: green;">' + seoAutoOptimizer.strings.completed + '</strong>');
        
        // Show success message
        showNotice('Bulk optimization completed successfully!', 'success');
    }
    
    /**
     * Handle bulk optimization errors
     */
    function bulkOptimizationError(message) {
        bulkOptimizationActive = false;
        $('#start-bulk-optimization').prop('disabled', false).text('Optimize Now');
        $('#current-status').html('<strong style="color: red;">Error: ' + message + '</strong>');
        
        showNotice('Bulk optimization failed: ' + message, 'error');
    }
    
    /**
     * View optimization logs
     */
    $('#view-logs').on('click', function() {
        const $button = $(this);
        const $container = $('#logs-container');
        const $content = $('#logs-content');
        
        if ($container.is(':visible')) {
            $container.hide();
            $button.text('View Optimization History');
            return;
        }
        
        $button.prop('disabled', true).text('Loading...');
        
        $.post(seoAutoOptimizer.ajax_url, {
            action: 'seo_auto_optimizer_get_logs',
            nonce: seoAutoOptimizer.nonce
        })
        .done(function(response) {
            if (response.success) {
                $content.html(response.data.html);
                $container.show();
                $button.text('Hide Optimization History');
            } else {
                showNotice('Failed to load logs', 'error');
            }
        })
        .fail(function() {
            showNotice('Failed to load logs', 'error');
        })
        .always(function() {
            $button.prop('disabled', false);
        });
    });
    
    /**
     * Clear old logs
     */
    $('#clear-logs').on('click', function() {
        if (!confirm('Are you sure you want to clear old logs (90+ days)?')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).text('Clearing...');
        
        $.post(seoAutoOptimizer.ajax_url, {
            action: 'seo_auto_optimizer_clear_logs',
            nonce: seoAutoOptimizer.nonce
        })
        .done(function(response) {
            if (response.success) {
                showNotice(response.data.message, 'success');
                // Refresh logs if visible
                if ($('#logs-container').is(':visible')) {
                    $('#view-logs').click().click();
                }
            } else {
                showNotice('Failed to clear logs', 'error');
            }
        })
        .fail(function() {
            showNotice('Failed to clear logs', 'error');
        })
        .always(function() {
            $button.prop('disabled', false).text('Clear Old Logs (90+ days)');
        });
    });
    
    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Real-time validation for batch size
     */
    $('#batch_size').on('input', function() {
        const value = parseInt($(this).val());
        if (value < 1) {
            $(this).val(1);
        } else if (value > 100) {
            $(this).val(100);
        }
    });
    
    /**
     * Estimate processing time
     */
    function updateProcessingEstimate() {
        const postType = $('#post_type').val();
        const batchSize = parseInt($('#batch_size').val()) || 20;
        
        // This would need an AJAX call to get actual post count
        // For now, just show a general estimate message
        const estimateText = 'Estimated time depends on the number of posts to process.';
        
        if ($('#processing-estimate').length === 0) {
            $('#bulk-optimization-form').append('<p id="processing-estimate" class="description">' + estimateText + '</p>');
        } else {
            $('#processing-estimate').text(estimateText);
        }
    }
    
    // Update estimate when form fields change
    $('#post_type, #batch_size, #start_date, #end_date').on('change', updateProcessingEstimate);
    
    // Initialize estimate
    updateProcessingEstimate();
    
    /**
     * Handle keyboard shortcuts
     */
    $(document).on('keydown', function(e) {
        // Ctrl+Shift+O for quick optimize
        if (e.ctrlKey && e.shiftKey && e.which === 79) {
            e.preventDefault();
            if (!bulkOptimizationActive) {
                $('#start-bulk-optimization').click();
            }
        }
        
        // Escape to stop optimization (if needed in future)
        if (e.which === 27 && bulkOptimizationActive) {
            // Could implement stop functionality here
        }
    });
    
    /**
     * Progress bar animation
     */
    function animateProgressBar(targetWidth) {
        $('#progress-fill').animate({
            width: targetWidth + '%'
        }, 300);
    }
    
    /**
     * Form validation
     */
    function validateBulkForm() {
        const postType = $('#post_type').val();
        const batchSize = parseInt($('#batch_size').val());
        const startDate = $('#start_date').val();
        const endDate = $('#end_date').val();
        
        let isValid = true;
        let errors = [];
        
        if (!postType) {
            errors.push('Please select a post type');
            isValid = false;
        }
        
        if (batchSize < 1 || batchSize > 100) {
            errors.push('Batch size must be between 1 and 100');
            isValid = false;
        }
        
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
            errors.push('Start date must be before end date');
            isValid = false;
        }
        
        if (!isValid) {
            showNotice('Form validation errors: ' + errors.join(', '), 'error');
        }
        
        return isValid;
    }
    
    /**
     * Auto-save form state
     */
    function saveFormState() {
        const formState = {
            post_type: $('#post_type').val(),
            batch_size: $('#batch_size').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val()
        };
        
        localStorage.setItem('seo_auto_optimizer_form_state', JSON.stringify(formState));
    }
    
    /**
     * Restore form state
     */
    function restoreFormState() {
        const savedState = localStorage.getItem('seo_auto_optimizer_form_state');
        if (savedState) {
            try {
                const formState = JSON.parse(savedState);
                $('#post_type').val(formState.post_type);
                $('#batch_size').val(formState.batch_size);
                $('#start_date').val(formState.start_date);
                $('#end_date').val(formState.end_date);
            } catch (e) {
                // Ignore invalid saved state
            }
        }
    }
    
    // Save form state on change
    $('#bulk-optimization-form input, #bulk-optimization-form select').on('change', saveFormState);
    
    // Restore form state on page load
    restoreFormState();
});