$(document).ready(function () {
    $('.item-set-select').chosen({
        allow_single_deselect: true,
        disable_search_threshold: 10,
        width: '100%',
        include_group_label_in_selected: true,
    });
    $('#advanced-search').on("o:value-created", function (event) {
        target = $(event.target)
        if (target.children('.item-set-select').length > 0) {
            target.children('.item-set-select').chosen({
                allow_single_deselect: true,
                disable_search_threshold: 10,
                width: '100%',
                include_group_label_in_selected: true,
            });
        }

    });
});