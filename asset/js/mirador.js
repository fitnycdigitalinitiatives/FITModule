$(document).ready(function () {
    const manifest = $('#mirador-viewer').data('manifest');
    const authorization = $('#mirador-viewer').data('authorization');
    const canvas = $('#mirador-viewer').data('canvas');
    const miradorConfig = {
        id: "mirador-viewer",
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
    };
    if (authorization) {
        miradorConfig['requests'] = {
            preprocessors: [
                (url, options) => (url.match('info.json') && { ...options, headers: { ...options.headers, "Authorization": "Bearer " + authorization } }),
            ]
        };
        miradorConfig['osdConfig'] = {
            loadTilesWithAjax: true,
            ajaxHeaders: {
                'Authorization': `Bearer ${authorization}`
            }
        }
    }
    if (canvas) {
        miradorConfig['windows'][0]['canvasId'] = canvas;
    }
    Mirador.viewer(miradorConfig);
});