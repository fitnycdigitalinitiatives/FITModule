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
    Mirador.viewer(miradorConfig);
});