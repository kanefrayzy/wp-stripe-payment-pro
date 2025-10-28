jQuery(document).ready(function () {
    var $ = jQuery.noConflict();

    // Selectable table row handler
    jQuery('.selectable-table tbody tr').click(function () {
        jQuery('.selectable-table').find('.active').removeClass('active');
        jQuery(this).addClass('active');
        clearError();
    });

});