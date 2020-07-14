$(document).ready(function() {
  $('.more-meta-group #url').click(function() {
    $.post(this.href, {
        presigned: "url"
      },
      function(data, status) {
        presignedURL = $(data).find("#presigned").data("presigned");
        console.log(presignedURL);
      });
  });
  $('.more-meta-group #download').click(function() {
    $.post(this.href, {
        presigned: "download"
      },
      function(data, status) {
        presignedURL = $(data).find("#presigned").data("presigned");
        window.location.href = presignedURL;
      });
  });
});