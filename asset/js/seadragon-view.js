$(document).ready(function () {
  OpenSeadragon.setString("Tooltips.Home", "Reset zoom");
  $('.openseadragon-frame .openseadragon').each(function () {
    const currentViewer = $(this);
    const currentViewerID = currentViewer.attr('id');
    const iiifEndpoint = $(this).data('infojson');
    const authtoken = $(this).data('authtoken');
    const thumbnail = $(this).data('thumbnail');
    const expiration = $(this).data('expiration');

    let thisViewer = null;
    // Don't load the viewer until it's visible on the page or the initial Zoom will derp. Also need to unmount the viewer when it is set to display to work with multiple viewers
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // Element is visible
          if (!thisViewer) {
            thisViewer = loadViewer(currentViewer, currentViewerID, iiifEndpoint, authtoken, thumbnail, expiration);
          }
        } else {
          // Check if there already is a viewer and the element is actually display none rather than just not in the viewport
          if (thisViewer && !currentViewer[0].offsetParent) {
            thisViewer.destroy();
            thisViewer = null;
          }
        }
      });
    });
    observer.observe(currentViewer[0]);

  });

  function loadViewer(currentViewer, currentViewerID, iiifEndpoint, authtoken, thumbnail, expiration) {
    const options = {
      id: currentViewerID,
      showNavigator: true,
      showRotationControl: true,
      navigatorSizeRatio: 0.1,
      navigatorPosition: 'TOP_LEFT',
      minZoomImageRatio: 1,
      maxZoomPixelRatio: 10,
      controlsFadeDelay: 1000,
      controlsFadeLength: 1500,
      navigatorDisplayRegionColor: '#0036f9',
      zoomInButton: currentViewerID + '-zoom-in-button',
      zoomOutButton: currentViewerID + '-zoom-out-button',
      fullPageButton: currentViewerID + '-fullscreen-button',
      rotateRightButton: currentViewerID + '-rotate-right-button',
      rotateLeftButton: currentViewerID + '-rotate-left-button',
      homeButton: currentViewerID + '-home-button',
      tileSources: {
        type: 'image',
        url: thumbnail,
        x: 0,
        y: 0,
      }
    }
    currentViewer.append(`
      <div id="${currentViewerID}-toolbar" class="osd-toolbar">
          <button id="${currentViewerID}-zoom-in-button" class="osd-toolbar-button" aria-label="Zoom in">
              <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="bi bi-plus-lg" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>
              </svg>
          </button>
          <button id="${currentViewerID}-zoom-out-button" class="osd-toolbar-button" aria-label="Zoom out">
              <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="bi bi-dash-lg" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M2 8a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11A.5.5 0 0 1 2 8"/>
              </svg>
          </button>
          <button id="${currentViewerID}-home-button" class="osd-toolbar-button" aria-label="Reset zoom">
              <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="bi bi-house" viewBox="0 0 16 16">
              <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z"/>
              </svg>
          </button>
          <button id="${currentViewerID}-fullscreen-button" class="osd-toolbar-button" aria-label="Toggle full page">
              <svg xmlns="http://www.w3.org/2000/svg" class="bi bi-arrows-fullscreen" viewBox="0 0 16 16" aria-hidden="true">
              <path fill-rule="evenodd" d="M5.828 10.172a.5.5 0 0 0-.707 0l-4.096 4.096V11.5a.5.5 0 0 0-1 0v3.975a.5.5 0 0 0 .5.5H4.5a.5.5 0 0 0 0-1H1.732l4.096-4.096a.5.5 0 0 0 0-.707m4.344 0a.5.5 0 0 1 .707 0l4.096 4.096V11.5a.5.5 0 1 1 1 0v3.975a.5.5 0 0 1-.5.5H11.5a.5.5 0 0 1 0-1h2.768l-4.096-4.096a.5.5 0 0 1 0-.707m0-4.344a.5.5 0 0 0 .707 0l4.096-4.096V4.5a.5.5 0 1 0 1 0V.525a.5.5 0 0 0-.5-.5H11.5a.5.5 0 0 0 0 1h2.768l-4.096 4.096a.5.5 0 0 0 0 .707m-4.344 0a.5.5 0 0 1-.707 0L1.025 1.732V4.5a.5.5 0 0 1-1 0V.525a.5.5 0 0 1 .5-.5H4.5a.5.5 0 0 1 0 1H1.732l4.096 4.096a.5.5 0 0 1 0 .707"/>
              </svg>
          </button>
          <button id="${currentViewerID}-rotate-right-button" class="osd-toolbar-button" aria-label="Rotate right">
              <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/>
              <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/>
              </svg>
          </button>
          <div id="${currentViewerID}-rotate-left-button" style="display:none" aria-hidden="true"></div>
      </div>
      `);
    if (thumbnail) {

      options.tileSources = {
        type: 'image',
        url: thumbnail,
        x: 0,
        y: 0,
      }

      const viewer = OpenSeadragon(
        options
      );
      // move the toolbar inside of the container
      currentViewer.children(".osd-toolbar").appendTo(currentViewer.children(".openseadragon-container"));

      //add the iiif tiles on top of the thumbnail and remove loader
      const iiifoptions = {
        tileSource: iiifEndpoint,
        x: 0,
        y: 0,
        success: function (event) {
          const tiledImage = event.item;
          tiledImage.addHandler('fully-loaded-change', removeThumbnail(tiledImage, viewer, currentViewer));
          // Set fade settings
          viewer.addHandler('container-enter', function () {
            $(".osd-toolbar").stop(true, true).fadeTo(0, 1);
          });
          viewer.addHandler('container-exit', function () {
            $(".osd-toolbar").stop(true, true).delay(options.controlsFadeDelay).fadeOut(options.controlsFadeLength);
          });
          if (!currentViewer.children(".openseadragon-container").filter(':hover').length) {
            $(".osd-toolbar").delay(options.controlsFadeDelay).fadeOut(options.controlsFadeLength);
          }
        },
        error: function (event) {
          removeLoader(currentViewer);
          addErrorMessage(currentViewer);
        }
      }

      // if token exists, add ajax to the options
      if (authtoken) {
        iiifoptions['loadTilesWithAjax'] = true;
        iiifoptions['ajaxHeaders'] = {
          "Authorization": "Bearer " + authtoken
        }
      } else {
        iiifoptions['crossOriginPolicy'] = 'Anonymous'
      }

      viewer.addTiledImage(iiifoptions);
      if (authtoken) {
        viewer.addHandler("tile-load-failed", function (event) {
          handleExpiration(viewer, currentViewer, expiration);
        });
      }
      return viewer;
    } else {

      options.tileSources = { tileSource: iiifEndpoint }
      // if token exists, add ajax to the options
      if (authtoken) {
        options.tileSources['loadTilesWithAjax'] = true;
        options.tileSources['ajaxHeaders'] = {
          "Authorization": "Bearer " + authtoken
        }
      } else {
        options.tileSources['crossOriginPolicy'] = 'Anonymous'
      }
      const viewer = OpenSeadragon(
        options
      );
      // move the toolbar inside of the container
      currentViewer.children(".osd-toolbar").appendTo(currentViewer.children(".openseadragon-container"));
      viewer.addHandler("add-item-failed", function (event) {
        removeLoader(currentViewer);
        addErrorMessage(currentViewer);
      });
      viewer.world.addHandler('add-item', function (event) {
        const tiledImage = event.item;
        tiledImage.addHandler('fully-loaded-change', function () {
          removeLoader(currentViewer);
          // Set fade settings
          viewer.addHandler('container-enter', function () {
            $(".osd-toolbar").stop(true, true).fadeTo(0, 1);
          });
          viewer.addHandler('container-exit', function () {
            $(".osd-toolbar").stop(true, true).delay(options.controlsFadeDelay).fadeOut(options.controlsFadeLength);
          });
          if (!currentViewer.children(".openseadragon-container").filter(':hover').length) {
            $(".osd-toolbar").delay(options.controlsFadeDelay).fadeOut(options.controlsFadeLength);
          }
        });
      });
      if (authtoken) {
        viewer.addHandler("tile-load-failed", function (event) {
          handleExpiration(viewer, currentViewer, expiration);
        });
      }
      return viewer;
    }
  }

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
    const errorMessage = $(`
      <div class="toast mx-1 bg-white fade show iiif-error" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
        <div class="toast-header bg-danger text-white">
          <strong class="me-auto">Unable to Load High-Resolution Image</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
                        <div class="toast-body text-dark">
          It seems we've run into some issues loading this image. Please try reloading the page or contacting us at <a href="mailto:repository@fitnyc.edu" target="_blank">repository@fitnyc.edu</a> if you continue to receive this message.
        </div>
      </div>
    `);
    $(currentViewer).append(errorMessage);
    $('.media.show:not(.resource) .openseadragon .toast-header .btn-close').on("click", function () {
      $(this).parents('.toast').hide();
    });
  }

  function handleExpiration(viewer, currentViewer, expiration) {
    if (Date.now() >= expiration) {
      viewer.removeAllHandlers("tile-load-failed");
      const errorMessage = $(`
        <div class="toast mx-1 bg-white fade show iiif-error" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
          <div class="toast-header bg-danger text-white">
            <strong class="me-auto">Unable to Load High-Resolution Image</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
                          <div class="toast-body text-dark">
            Your session has recently expired. Please try reloading the page so that you can access this image at the highest resolution. If you continue to receive this message, contact us at <a href="mailto:repository@fitnyc.edu" target="_blank">repository@fitnyc.edu</a>.
          </div>
        </div>
      `);
      $(currentViewer).append(errorMessage);
    }
  }
});

