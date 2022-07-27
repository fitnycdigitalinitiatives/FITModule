$(document).ready(function() {
  $('.openseadragon').each(function() {
    var currentViewer = $(this);
    var currentViewerID = currentViewer.attr('id');
    var recordID = $(this).data('record_id');
    var recordName = $(this).data('record_name');
    var iiifEndpoint = $(this).data('infojson');
    var authtoken = $(this).data('authtoken');
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
    viewer.addHandler("add-item-failed", function(event) {
      $(currentViewer).parent().children('.loader').remove();
      $(currentViewer).css({
        "display": "flex",
        "align-items": "center",
        "justify-content": "center",
        "color": '#fff'
      });
      $(currentViewer).empty().append("<b>This resource failed to load. For more assistance, please contact an administrator.</b>");
    });
    viewer.world.addHandler('add-item', function(event) {
      var tiledImage = event.item;
      tiledImage.addHandler('fully-loaded-change', function() {
        $(currentViewer).parent().children('.loader').remove();
      });
    });
  });
});