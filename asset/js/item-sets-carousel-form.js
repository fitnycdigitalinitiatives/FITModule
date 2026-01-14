$(document).ready(function () {
    $('.block').each(function () {
        if ($(this).data('block-layout') == 'itemSetShowcaseHeroCarousel') {
            watchForChosen($(this));
        }
    });
    $('#blocks').on("o:block-added", function (event) {
        const target = $(event.target);
        if (target.data('block-layout') == 'itemSetShowcaseHeroCarousel') {
            watchForChosen(targetBlock);
        }
    });

    function watchForChosen(targetBlock) {
        // Select the node that will be observed for mutations
        const targetNode = targetBlock.find('.attachments.slides')[0];

        // Options for the observer (which mutations to observe)
        const config = { childList: true };

        // Callback function to execute when mutations are observed
        const callback = (mutationList, observer) => {
            for (const mutation of mutationList) {
                if (mutation.addedNodes.length > 0) {
                    for (const node of mutation.addedNodes) {
                        if (node.className == "attachment slide new selecting") {
                            $(node).find('.chosen-select').chosen({
                                allow_single_deselect: true,
                                disable_search_threshold: 10,
                                width: '100%',
                                include_group_label_in_selected: true,
                            });
                        }
                    }
                }
            }
        };

        // Create an observer instance linked to the callback function
        const observer = new MutationObserver(callback);

        // Start observing the target node for configured mutations
        observer.observe(targetNode, config);
    }

    // Get the element to observe
    const assetSidebar = document.getElementById('asset-options');

    // Define the callback function
    const observerCallback = function (mutationsList, observer) {
        for (const mutation of mutationsList) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                if (assetSidebar.classList.contains('active')) {
                    const attachment = document.querySelector(".attachment.selecting");
                    if (attachment.classList.contains('slide')) {
                        assetSidebar.querySelector('.sidebar-content').classList.add('slide');
                    }
                } else {
                    assetSidebar.querySelector('.sidebar-content').classList.remove('slide');
                }
            }
        }
    };

    // Create a MutationObserver instance
    const observer = new MutationObserver(observerCallback);

    // Configure the observer to watch for attribute changes, specifically the 'class' attribute
    const observerConfig = { attributes: true, attributeFilter: ['class'], attributeOldValue: true };

    // Start observing the target element
    observer.observe(assetSidebar, observerConfig);
});