$(document).ready(function () {
  $(".more-meta-group .url-sidebar").click(function () {
    $("#url").data("filetype", $(this).data("filetype"));
    $("#presigned-sidebar").toggleClass("active");
  });
  $("#url").on("click", function () {
    const duration = $("#presigned-expiration").val();
    const filetype = $(this).data("filetype");

    $.post(
      this.href,
      {
        presigned: filetype,
        duration: duration,
      },
      function (data, status) {
        let presignedURL = $(data)
          .find("#presigned")
          .data(filetype + "-presigned");
        if (presignedURL == "error") {
          let meta_value = `
          <div class="value error" style="display:none; border-top: 1px solid #dfdfdf;">
            <strong>Error</strong>: Unable to generate temporary link. The file could not be located. The S3 Object URL may be invalid.
          </div>
        `;
          $("#presigned-result").empty();
          $(meta_value).appendTo("#presigned-result").fadeIn("slow");
        } else {
          let directions = "";
          if (filetype == "archival") {
            directions =
              "<br/>This URL will download a zipped file in the BagIt format, which is used for archival purposes. To access the digital assets you requested, unzip the downloaded file and access the data/objects directory.";
          }
          const expirationTime = new Date(Date.now() + duration * 60 * 1000);
          let meta_value =
            `
          <div class="value" style="display:none; padding-top: 1rem;">
            <label for="${filetype}presigned">
              Temporary Link
            </label>
            <div class="copyPresigned">
              <input class="presignedURLbox" type="text" value="` +
            presignedURL +
            `" id="` +
            filetype +
            `presigned" readonly>
              <button class="button presignedURLcopybutton" data-clipboard-target="#` +
            filetype +
            `presigned">
                <i class="fas fa-copy" title="Copy URL to clipboard"><span class="sr-only">Copy URL to clipboard</span></i>
              </button>
            </div>
            <small>Expires on ${expirationTime.toLocaleString("en-US", {
              year: "numeric",
              month: "long",
              day: "numeric",
              hour: "numeric",
              minute: "numeric",
              timeZoneName: "short",
            })}</small>
            <small>${directions}</small>
          </div>
        `;
          $("#presigned-result").empty();
          $(meta_value).appendTo("#presigned-result").fadeIn("slow");
          new ClipboardJS(".presignedURLcopybutton");
        }
      },
    );
  });
  $("#presigned-sidebar .sidebar-close").on("click", function () {
    $("#presigned-result").empty();
  });
  $(".more-meta-group #download").click(function () {
    let parent = $(this).parent();
    let filetype = $(this).data("filetype");
    $.post(
      this.href,
      {
        presigned: filetype,
        download: true,
        duration: 10,
      },
      function (data, status) {
        let presignedURL = $(data)
          .find("#presigned")
          .data(filetype + "-presigned");
        if (presignedURL == "error") {
          let meta_value = `
            <div class="value error" style="display:none; border-top: 1px solid #dfdfdf;">
              <strong>Error</strong>: Unable to download this file. The file could not be located. The S3 Object URL may be invalid.
            </div>
          `;
          $(meta_value).insertBefore(parent).fadeIn("slow");
        } else {
          window.location.href = presignedURL;
        }
      },
    );
  });
});
