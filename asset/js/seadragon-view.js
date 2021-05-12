$(document).ready(function() {
  $('.openseadragon').each(function() {
    var currentViewer = $(this);
    var currentViewerID = currentViewer.attr('id');
    var recordID = $(this).data('record_id');
    var recordName = $(this).data('record_name');
    var iiifEndpoint = $(this).data('infojson');;
    var viewer = OpenSeadragon({
      id: currentViewerID,
      prefixUrl: 'https://cdn.jsdelivr.net/npm/openseadragon@2.4/build/openseadragon/images/',
      showNavigator: true,
      navigatorSizeRatio: 0.1,
      minZoomImageRatio: 0.8,
      maxZoomPixelRatio: 10,
      controlsFadeDelay: 1000,
      tileSources: iiifEndpoint
    });
    viewer.addHandler("add-item-failed", function(event) {
      $(currentViewer).parent().replaceWith("<b>This IIIF resource failed to load. The info.json file may be invalid.</b>");
    });
    viewer.world.addHandler('add-item', function(event) {
      var tiledImage = event.item;
      tiledImage.addHandler('fully-loaded-change', function() {
        $(currentViewer).parent().children('.loader').remove();
      });
    });
  });
});