$(document).ready(function () {
    //Page slider glider.js
    if ($('body').hasClass('page')) {
        if ($('.glider').length) {
            $('.glider').each(function (index) {
                var id = 'glider-' + index;
                $(this).parent().parent().attr('id', id);
                const gliderInstance = new Glider(this, {
                    slidesToShow: 1.5,
                    slidesToScroll: 1,
                    draggable: true,
                    arrows: {
                        prev: '#' + id + ' .btnPrevious',
                        next: '#' + id + ' .btnNext'
                    },
                    responsive: [{
                        // screens greater than >= 576px
                        breakpoint: 576,
                        settings: {
                            // Set to `auto` and provide item width to adjust to viewport
                            slidesToShow: 2,
                            slidesToScroll: 2
                        }
                    }, {
                        // screens greater than >= 768px
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 3,
                            slidesToScroll: 3
                        }
                    }, {
                        breakpoint: 992,
                        settings: {
                            slidesToShow: 4,
                            slidesToScroll: 4
                        }
                    }, {
                        breakpoint: 1400,
                        settings: {
                            slidesToShow: 5,
                            slidesToScroll: 5
                        }
                    }]
                });
                $(this).fadeIn();
                gliderInstance.refresh();
            });

        }
    }
});