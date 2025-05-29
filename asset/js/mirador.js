$(document).ready(function () {
    $('.mirador-viewer').each(function () {
        const currentViewer = $(this);
        const currentViewerID = currentViewer.attr('id');
        const manifest = currentViewer.data('manifest');
        const authorization = currentViewer.data('authorization');
        const canvas = currentViewer.data('canvas');
        const miradorConfig = currentViewer.data('options');
        loadViewer(currentViewer, currentViewerID, manifest, authorization, canvas, miradorConfig);
    });

    function loadViewer(currentViewer, currentViewerID, manifest, authorization, canvas, miradorConfig) {
        miradorConfig['id'] = currentViewerID;
        miradorConfig['windows'] = [{ manifestId: manifest }];

        if (authorization) {
            miradorConfig['requests'] = {
                preprocessors: [
                    (url, options) => (url.match('info.json') && { ...options, headers: { ...options.headers, "Authorization": "Bearer " + authorization } }),
                ]
            };
            miradorConfig['osdConfig']['loadTilesWithAjax'] = true;
            miradorConfig['osdConfig']['ajaxHeaders'] = {
                'Authorization': `Bearer ${authorization}`
            };
        } else {
            miradorConfig['osdConfig']['crossOriginPolicy'] = 'Anonymous'
        }
        if (canvas) {
            miradorConfig['windows'][0]['canvasId'] = canvas;
        }
        let thisViewer = null;
        // Don't load the mirador viewer until it's visible on the page or the initial Zoom will derp. Also need to unmount the viewer when it is set to display to work with multiple viewers
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Element is visible
                    if (!thisViewer) {
                        thisViewer = Mirador.viewer(miradorConfig);
                    }
                }
                else {
                    // Check if there already is a viewer and the element is actually display none rather than just not in the viewport
                    if (thisViewer && !currentViewer[0].offsetParent) {
                        thisViewer.unmount();
                        thisViewer = null;
                    }
                }
            });
        });
        observer.observe(currentViewer[0]);
    }
});

