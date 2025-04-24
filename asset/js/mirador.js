$(document).ready(function () {
    const manifest = $('#mirador-viewer').data('manifest');
    const authorization = $('#mirador-viewer').data('authorization');
    const canvas = $('#mirador-viewer').data('canvas');
    const options = $('#mirador-viewer').data('options');
    const miradorConfig = {
        id: "mirador-viewer",
        workspace: {
            showZoomControls: true,
        },
        workspaceControlPanel: {
            enabled: false
        },
        window: {
            allowClose: false,
            allowFullscreen: true,
            allowMaximize: false,
        },
        windows: [
            {
                manifestId: manifest,
                thumbnailNavigationPosition: 'far-right',
            }
        ],
        osdConfig: {
            preserveViewport: false,
        }
    };
    if ('window' in options) {
        Object.keys(options['window']).forEach(key => {
            miradorConfig['window'][key] = options['window'][key];
        });
    }
    if ('themes' in options) {
        miradorConfig['themes'] = options['themes']
    }

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
    // Don't load the mirador viewer until it's visible on the page or the initial Zoom will derp
    function onVisible(element, callback) {
        new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.intersectionRatio > 0) {
                    callback(element);
                    observer.disconnect();
                }
            });
        }).observe(element);
        if (!callback) return new Promise(r => callback = r);
    }
    onVisible(document.querySelector("#mirador-viewer"), () => Mirador.viewer(miradorConfig));
});