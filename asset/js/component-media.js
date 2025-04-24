$(document).ready(function () {
    // Media page edit
    if ($('body').hasClass('media')) {
        new Sortable($('.remote-components .inputs')[0], {
            draggable: '.component',
            handle: '.remote-sortable-handle'
        });
        $('.add-component').on('click', function () {
            const lastComponent = $(this).parent().children('.component').last();
            let index = lastComponent.data('key');
            const componentTemplate = $('#remote-component-template').data('template').replace(/__componentIndex__/g, index + 1);
            lastComponent.after(componentTemplate);
        });
        // Remove button
        $('.remote-components').on('click', '.remove-component', function (e) {
            e.preventDefault();
            $(this).parents('.component').remove();
        });
    }
    // Item page edit 
    else {
        $("#media-selector button[data-media-type='remoteCompoundObject']").on('click', function () {
            // Select the node that will be observed for mutations
            const targetNode = document.getElementById("media-list");

            // Options for the observer (which mutations to observe)
            const config = { childList: true };

            // Callback function to execute when mutations are observed
            const callback = (mutationList, observer) => {
                for (const mutation of mutationList) {
                    if (mutation.addedNodes.length > 0) {
                        const remoteComponents = mutation.addedNodes[0].getElementsByClassName('remote-components');
                        if (remoteComponents.length > 0) {
                            new Sortable(remoteComponents[0].getElementsByClassName('inputs')[0], {
                                draggable: '.component',
                                handle: '.remote-sortable-handle'
                            });
                        }
                        observer.disconnect();
                    }
                }
            };

            // Create an observer instance linked to the callback function
            const observer = new MutationObserver(callback);

            // Start observing the target node for configured mutations
            observer.observe(targetNode, config);

        });

        $('#media-list').on('click', '.add-component', function () {
            const lastComponent = $(this).parent().children('.component').last();
            let index = lastComponent.data('key');
            // Each added media gets a new template, need to make sure the template for this specific remote compound media is grabbed
            const componentTemplate = $(this).parents('.media').children('#remote-component-template').data('template').replace(/__componentIndex__/g, index + 1);
            lastComponent.after(componentTemplate);
        });
        // Remove button
        $('#media-list').on('click', '.remove-component', function (e) {
            e.preventDefault();
            $(this).parents('.component').remove();
        });
    }
});