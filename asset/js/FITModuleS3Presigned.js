$(document).ready(function () {
  $('.more-meta-group #url').click(function () {
    var parent = $(this).parent()
    var file_type = $(this).data("file-type");
    $.post(this.href, {
      presigned: file_type
    },
      function (data, status) {
        var presignedURL = $(data).find("#presigned").data(file_type + "-presigned");
        if (presignedURL == "error") {
          var meta_value = `
            <div class="value error" style="display:none; border-top: 1px solid #dfdfdf;">
              <strong>Error</strong>: Unable to generate temporary link. The file could not be located. The S3 Object URL may be invalid.
            </div>
          `;
          $(meta_value).insertBefore(parent).fadeIn('slow');
        } else {
          var meta_value = `
            <div class="value" style="display:none; border-top: 1px solid #dfdfdf;">
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
        }
      });
  });
  $('.more-meta-group #download').click(function () {
    var parent = $(this).parent()
    var file_type = $(this).data("file-type");
    $.post(this.href, {
      presigned: file_type,
      download: true
    },
      function (data, status) {
        var presignedURL = $(data).find("#presigned").data(file_type + "-presigned");
        if (presignedURL == "error") {
          var meta_value = `
            <div class="value error" style="display:none; border-top: 1px solid #dfdfdf;">
              <strong>Error</strong>: Unable to download this file. The file could not be located. The S3 Object URL may be invalid.
            </div>
          `;
          $(meta_value).insertBefore(parent).fadeIn('slow');
        } else {
          window.location.href = presignedURL;
        }
      });
  });
});