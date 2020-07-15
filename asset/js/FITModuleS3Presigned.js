$(document).ready(function() {
  $('.more-meta-group #url').click(function() {
    var parent = $(this).parent()
    var file_type = $(this).data("file-type");
    $.post(this.href, {
        presigned: file_type
      },
      function(data, status) {
        var presignedURL = $(data).find("#presigned").data(file_type + "-presigned");
        var meta_value = `
          <div class="value" style="display:none;">
            <small>
              S3 Presigned URL (Valid for 1 hour)
            </small>
            <div class="copyPresigned">
              <input class="presignedURLbox" type="text" value="` + presignedURL + `" id="` + file_type + `presigned" readonly>
              <button class="button presignedURLcopybutton" data-clipboard-target="#` + file_type + `presigned">
                <i class="fas fa-copy" title="Copy URL to clipboard"><span class="sr-only">Copy URL to clipboard</span></i>
              </button>
            </div>
          </div>
        `;
        $(meta_value).insertBefore(parent).fadeIn('slow');
        new ClipboardJS('.presignedURLcopybutton');
      });
  });
  $('.more-meta-group #download').click(function() {
    var file_type = $(this).data("file-type");
    $.post(this.href, {
        presigned: file_type
      },
      function(data, status) {
        var presignedURL = $(data).find("#presigned").data(file_type + "-presigned");
        window.location.href = presignedURL;
      });
  });
});