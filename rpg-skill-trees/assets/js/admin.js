(function($){
    $(function(){
        $('#rst-add-conversion').on('click', function(){
            var idx = $('#rst-conversions .rst-conversion-row').length;
            var row = '<div class="rst-conversion-row">'
                + '<label>From <input type="number" name="settings[conversions]['+idx+'][from]" min="1" max="4" value="1" /></label>'
                + '<label>To <input type="number" name="settings[conversions]['+idx+'][to]" min="1" max="4" value="1" /></label>'
                + '<label>Ratio <input type="number" step="0.01" name="settings[conversions]['+idx+'][ratio]" value="1" /></label>'
                + '</div>';
            $('#rst-conversions').append(row);
        });
    });
})(jQuery);
