jQuery(window).on('elementor/frontend/init', function ($) {
    'use strict';
    setTimeout(function () {
        $(document).trigger('viwse-suggestion-flexslider');
    },100);
});
jQuery(document).ready(function ($) {
    'use strict';
    setTimeout(function () {
        $(document).trigger('viwse-suggestion-flexslider');
    },100);
    window.onresize = function (){
        setTimeout(function () {
            $(document).trigger('viwse-suggestion-flexslider',true);
        },100);
    };
    jQuery(document).on('ajaxComplete', function (event, jqxhr, settings) {
        setTimeout(function () {
            $(document).trigger('viwse-suggestion-flexslider');
        },100);
    });
    $(document).on('viwse-suggestion-flexslider', function (e, reinit = false) {
        $('.viwse-slide').each(function (k, v) {
            let wrap = $(v);
            if (wrap.hasClass('viwse-flexslider')){
                if (!reinit || !wrap.find('.viwse-suggestion-products-wrap').length){
                    return true;
                }
                wrap.html(wrap.find('.viwse-suggestion-products-wrap'));
            }
            let rtl = wrap.closest('.rtl').length ? true :false,
                params = wrap.data('slide_options') || {};
            let cols = parseInt(params['cols'] || 4),
                cols_mobile = parseInt(params['cols_mobile'] || 1);
            let columns = cols;
            if (window.screen.width < 850) {
                columns = cols_mobile;
            }
            if (wrap.find('.viwse-suggestion-product-wrap').length <= columns){
                return true;
            }
            let width = wrap.outerWidth() / columns;
            wrap.removeData("flexslider");
            wrap.addClass('viwse-flexslider').flexslider({
                namespace: 'viwse-flexslider-',
                selector: '.viwse-suggestion-products-wrap .viwse-suggestion-product-wrap',
                animation: 'slide',
                itemWidth: width,
                itemMargin: 0,
                controlNav: false,
                maxItems: columns,
                reverse: false,
                rtl: rtl,
                move: columns,
                slideshow: false,
                touch: true,
                animationLoop: true,
            });
            let id_css = wrap.closest('.viwse-suggestion-wrap').attr('class').replace('viwse-suggestion-wrap','').trim();
            if (!$('#'+id_css).length){
                $('head').append(`<style id="${id_css}"></style>`);
            }
            $('#'+id_css).html(`.${id_css} .viwse-suggestion-product-wrap{width: ${width}px !important;}`);
        });
    });
});