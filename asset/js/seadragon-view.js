$(document).ready(function () {
  $('.openseadragon').each(function () {
    var currentViewer = $(this);
    var currentViewerID = currentViewer.attr('id');
    var iiifEndpoint = $(this).data('infojson');
    var authtoken = $(this).data('authtoken');
    var thumbnail = $(this).data('thumbnail');

    function removeThumbnail(tiledImage, viewer, currentViewer) {

      setTimeout(
        function () {
          viewer.world.removeItem(viewer.world.getItemAt(0));
          viewer.viewport.goHome(true);
          removeLoader(currentViewer);
        }, 1000);
      tiledImage.removeAllHandlers();
    }

    function removeLoader(currentViewer) {
      $(currentViewer).parent().children('.loader').remove();
    }

    function addErrorMessage(currentViewer) {
      var errorMessage = $(`
        <div class="toast mx-1 bg-white fade show iiif-error" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
          <div class="toast-header bg-danger text-white">
            <strong class="me-auto">Unable to Load High-Resolution Image</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
                          <div class="toast-body">
            It seems we've run into some issues loading this image. Please try reloading the page or contacting us at <a href="mailto:repository@fitnyc.edu" target="_blank">repository@fitnyc.edu</a> if you continue to receive this message.
          </div>
        </div>
      `);
      $(currentViewer).append(errorMessage);
      $('.media.show:not(.resource) .openseadragon .toast-header .btn-close').on("click", function () {
        $(this).parents('.toast').hide();
      });
    }

    if (thumbnail) {
      var options = {
        id: currentViewerID,
        prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@2.4/build/openseadragon/images/',
        showNavigator: true,
        navigatorSizeRatio: 0.1,
        minZoomImageRatio: 1,
        maxZoomPixelRatio: 10,
        controlsFadeDelay: 1000,
        tileSources: {
          type: 'image',
          url: thumbnail,
          x: 0,
          y: 0
        }
      }
      // if token exists, add ajax to the options
      if (authtoken) {
        options['loadTilesWithAjax'] = true;
        options['ajaxHeaders'] = {
          "Authorization": "Bearer " + authtoken
        }
      }

      var viewer = OpenSeadragon(
        options
      );

      //add the iiif tiles on top of the thumbnail and remove loader
      var iiifoptions = {
        tileSource: iiifEndpoint,
        x: 0,
        y: 0,
        success: function (event) {
          var tiledImage = event.item;
          tiledImage.addHandler('fully-loaded-change', removeThumbnail(tiledImage, viewer, currentViewer));
        },
        error: function (event) {
          removeLoader(currentViewer);
          addErrorMessage(currentViewer);
        }
      }

      viewer.addTiledImage(iiifoptions);
    } else {
      var options = {
        id: currentViewerID,
        prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@2.4/build/openseadragon/images/',
        showNavigator: true,
        navigatorSizeRatio: 0.1,
        minZoomImageRatio: 0.8,
        maxZoomPixelRatio: 10,
        controlsFadeDelay: 1000,
        tileSources: iiifEndpoint
      }
      // if token exists, add ajax to the options
      if (authtoken) {
        options['loadTilesWithAjax'] = true;
        options['ajaxHeaders'] = {
          "Authorization": "Bearer " + authtoken
        }
      }
      var viewer = OpenSeadragon(
        options
      );
      viewer.addHandler("add-item-failed", function (event) {
        removeLoader(currentViewer);
        addErrorMessage(currentViewer);
      });
      viewer.world.addHandler('add-item', function (event) {
        var tiledImage = event.item;
        tiledImage.addHandler('fully-loaded-change', function () {
          removeLoader(currentViewer);
        });
      });
    }
  });
});