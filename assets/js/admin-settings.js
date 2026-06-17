/**
 * Admin settings JS for AI Providers.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var $button = $('#ai-providers-validate-key');
        var $result = $('#ai-providers-validation-result');
        var originalText = $button.text();

        if (!$button.length) {
            return;
        }

        $button.on('click', function(e) {
            e.preventDefault();

            var apiKey = $('#openai_api_key').val();

            $button.prop('disabled', true).text(aiProvidersAdmin.i18n.validating);
            $result.text('').css('color', '');

            $.ajax({
                url: aiProvidersAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_providers_for_translatepress_validate_openai_key',
                    security: aiProvidersAdmin.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $result.text(response.data.message).css('color', '#46b450');
                    } else {
                        var msg = (response.data && response.data.message)
                            ? response.data.message
                            : aiProvidersAdmin.i18n.invalid;
                        $result.text(msg).css('color', '#dc3232');
                    }
                },
                error: function(xhr) {
                    var msg = aiProvidersAdmin.i18n.error;
                    if (xhr.status === 403) {
                        msg = aiProvidersAdmin.i18n.error + ' (Forbidden)';
                    } else if (xhr.status === 429) {
                        msg = aiProvidersAdmin.i18n.error + ' (Rate limited)';
                    } else if (xhr.status === 0) {
                        msg = aiProvidersAdmin.i18n.networkError;
                    }
                    $result.text(msg).css('color', '#dc3232');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });
})(jQuery);
