$(document).ready(function () {
    if (screen.width >= 768) {
        const videoURL = $("#image-video-transition").data('video-src');
        const videoHtml = $(`
        <div class="carousel-item ratio ratio-16x9">
            <video id="image-video-transition-video" autoplay="" loop="" muted="">
                <source src="` + videoURL + `" />
            </video>
        </div>
        `);
        const buttonList = $(`
        <ul class="list-inline button-list m-0"></ul>
        `);
        const videoButton = $(`
        <li class="list-inline-item">
            <button class="play-pause bg-transparent border-0 text-white" type="button" aria-label="Pause">
                <i aria-hidden="true" class="fas fa-pause"></i>
            </button>
        </li>
        `);
        $(buttonList).append(videoButton);
        const itemLink = $("#image-video-transition").data('video-link');
        if (itemLink) {
            const linkButton = $(`
            <li class="list-inline-item">
                <a href="` + itemLink + `" class="video-link bg-transparent border-0 text-white" aria-label="Link to full video">
                    <i aria-hidden="true" class="fas fa-video"></i>
                </a>
            </li>
            `);
            $(buttonList).append(linkButton);
        }
        $("#image-video-transition .carousel-inner").append(videoHtml);
        const video = $("#image-video-transition-video").get(0);
        const carousel = new bootstrap.Carousel('#image-video-transition', {
            pause: false,
            touch: false
        });
        $(video).one("canplay", function () {
            setTimeout(function () {
                carousel.to(1);
                $(buttonList).appendTo("#image-video-transition").hide().fadeIn(5000);
                $("#image-video-transition .play-pause").click(function () {
                    if (video.paused || video.ended) {
                        video.play();
                        $(this).children().removeClass('fa-play').addClass('fa-pause');
                    } else {
                        video.pause();
                        $(this).children().removeClass('fa-pause').addClass('fa-play');
                    }
                });
            }, 2500);
        });
    }
});
