$(document).ready(function () {
    if ($('.splide').length) {
        new Splide('.splide', {
            type: 'slide',
            focus: 0,
            omitEnd: true,
            autoWidth: true,
            height: '65px',
            gap: '0.5rem',
            pagination: false,
            breakpoints: {
                767: {
                    height: '50px',
                },
            }
        }).mount();
    }
    $('#media-slider-container .splide .splide__slide button').click(function () {
        if (!$(this).hasClass("selected")) {
            $('#media-slider-container .splide .splide__slide button').removeClass('selected').attr('aria-selected', 'false');
            $(this).addClass('selected').attr('aria-selected', 'true');
            let activeTab = $(this).data('target');
            $('#mediaTabContent .tab-pane').removeClass('show active');
            $(activeTab).addClass('show active');
        }
    });
    if (window.matchMedia('(min-width: 768px)').matches) {
        //tooltips
        let tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        let tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }
    // Pause video when changing tabs
    $('#media-slider-container .splide .splide__slide button').click(function () {
        let selectedTab = $(this).data('target');
        $('#mediaTabContent .tab-pane:not(' + selectedTab + ') .youtube').each(function () {
            $(this)[0].contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
        });
        $('#mediaTabContent .tab-pane:not(' + selectedTab + ') .vimeo').each(function () {
            $(this)[0].contentWindow.postMessage('{"method":"pause"}', '*');
        });
        $('#mediaTabContent .tab-pane:not(' + selectedTab + ') .vjs-tech').each(function () {
            $(this).get(0).pause();
        });
    });
});