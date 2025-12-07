(function($){
    $(document).on('click', '#rpg-add-conversion', function(){
        var row = '<tr>'+
            '<td><input type="number" min="1" max="4" name="conversion_from[]" value="1" /></td>'+
            '<td><input type="number" min="1" max="4" name="conversion_to[]" value="2" /></td>'+
            '<td><input type="number" step="0.01" name="conversion_ratio[]" value="1" /></td>'+
            '<td><button class="button rpg-remove-row" type="button">&times;</button></td>'+
            '</tr>';
        $('#rpg-conversion-table tbody').append(row);
    });

    $(document).on('click', '.rpg-remove-row', function(){
        $(this).closest('tr').remove();
    });

    $(document).on('click', '.rpg-upload-icon', function(e){
        e.preventDefault();
        var target = $('#' + $(this).data('target'));
        var frame = wp.media({
            title: rpgSkillTreesAdmin.upload_title || 'Select Icon',
            button: { text: rpgSkillTreesAdmin.upload_button || 'Use icon' },
            multiple: false
        });
        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();
            target.val(attachment.url);
        });
        frame.open();
    });
})(jQuery);
