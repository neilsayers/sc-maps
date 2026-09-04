(function ($) {
    'use strict';

    $(document).on('click', '.scm-icon-choose', function (event) {
        event.preventDefault();

        var button = $(this);
        var input = $('#' + button.data('target'));
        var wrapper = button.closest('.scm-icon-picker');
        var preview = wrapper.find('.scm-icon-preview');
        var previewImg = preview.find('img');

        var frame = wp.media({
            title: 'Choose a marker icon',
            library: { type: 'image' },
            multiple: false,
            button: { text: 'Use this icon' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.url;

            input.val(url);
            previewImg.attr('src', url);
            preview.show();
        });

        frame.open();
    });

    $(document).on('click', '.scm-icon-remove', function (event) {
        event.preventDefault();

        var wrapper = $(this).closest('.scm-icon-picker');

        wrapper.find('input[type="hidden"]').val('');
        wrapper.find('.scm-icon-preview').hide();
    });
})(jQuery);
