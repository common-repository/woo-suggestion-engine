jQuery(document).ready(function ($) {
    'use strict';
    $('.vi-ui.tabular.menu .item').vi_tab({history: true, historyType: 'hash'});
    $('.vi-ui.accordion').addClass('viwse-accordion-init').villatheme_accordion('refresh');
    $('.vi-ui.dropdown').addClass('viwse-dropdown-init').off().dropdown();
    $('.vi-ui.checkbox').addClass('viwse-dropdown-init').off().checkbox();
    $(document).on('change','input[type="checkbox"]' ,function () {
        if ($(this).prop('checked')) {
            $(this).parent().find('input[type="hidden"]').val('1');
        } else {
            $(this).parent().find('input[type="hidden"]').val('');
        }
    });
    $('.search_no_result_suggestion').off().dropdown({
        onChange:function (val) {
            if (val && val != 0){
                $('.search_no_result_suggestion_enable').removeClass('viwse-hidden');
            }else {
                $('.search_no_result_suggestion_enable').addClass('viwse-hidden');
            }
        }
    });
    $(document.body).on('viwse_background_processing_status', function (){
        $.ajax({
            url: "admin-ajax.php?action=viwse_background_processing_status",
            type: "POST",
            data: {
                nonce: $('#_viwse_nonce').val() ,
            },
            success: function (response) {
                if (response.bg_processing){
                    setTimeout(function (){
                        $(document.body).trigger('viwse_background_processing_status');
                    },5000);
                }
                if (response.onlyajax_visible){
                    $('.viwse-search-ajax-button').removeClass('viwse-hidden');
                }else {
                    $('.viwse-search-ajax-button').addClass('viwse-hidden');
                }
                if (response.bg_bt_text){
                    $('.viwse-background-render-button').data({bg_complete: response.bg_complete || '',bg_processing:response.bg_processing || ''}).html(response.bg_bt_text);
                }
                if (response.bg_text){
                    $('.viwse-background-render-status').html(response.bg_text);
                }
                if (response.message) {
                    $(document.body).trigger('villatheme_show_message', [response.status, [response.status, 'background_settings'], response.message, false, 4500]);
                }
            },
            error: function (err) {
                console.log(err);
                setTimeout(function (){
                    $(document.body).trigger('viwse_background_processing_status');
                },5000);
            }
        });
    });
    if (viwse_params.background_render_processing){
        setTimeout(function (){
            $(document.body).trigger('viwse_background_processing_status');
        },1000);
    }
    $(document).on('click','.viwse-background-render-button:not(.loading)', function (){
        let button = $(this);
        $.ajax({
            url: "admin-ajax.php?action=viwse_background_settings",
            type: "POST",
            data: {
                build_data: (button.data('bg_processing') || '') ? '' : 1 ,
                nonce: $('#_viwse_nonce').val() ,
            },
            beforeSend: function () {
                button.addClass('loading');
                $('.viwse-button-save').attr('type', 'button');
            },
            success: function (response) {
                button.removeClass('loading');
                $('.viwse-button-save').attr('type', 'submit');
                if (response.bg_processing){
                    setTimeout(function (){
                        $(document.body).trigger('viwse_background_processing_status');
                    },5000);
                }
                if (response.onlyajax_visible){
                    $('.viwse-search-ajax-button').removeClass('viwse-hidden');
                }else {
                    $('.viwse-search-ajax-button').addClass('viwse-hidden');
                }
                if (response.bg_bt_text){
                    $('.viwse-background-render-button').data({bg_complete: response.bg_complete || '',bg_processing:response.bg_processing || ''}).html(response.bg_bt_text);
                }
                if (response.bg_text){
                    $('.viwse-background-render-status').html(response.bg_text);
                }
                if (response.message) {
                    $(document.body).trigger('villatheme_show_message', [response.status, [response.status, 'background_settings'], response.message, false, 4500]);
                }
            },
            error: function (err) {
                console.log(err)
                button.removeClass('loading');
                $('.viwse-button-save').attr('type', 'submit');
            }
        });
    });
    $(document).on('click','.viwse-search-ajax-button:not(.loading)', function (){
        let button = $(this);
        if (!confirm(viwse_params.search_ajax_enable)){
            return false;
        }
        $.ajax({
            url: "admin-ajax.php?action=viwse_search_ajax_enable",
            type: "POST",
            data: {
                search_ajax_enable: 1 ,
                nonce: $('#_viwse_nonce').val() ,
            },
            beforeSend: function () {
                button.addClass('loading');
                $('.viwse-button-save').attr('type', 'button');
            },
            success: function (response) {
                button.removeClass('loading');
                $('.viwse-button-save').attr('type', 'submit')
                button.addClass('viwse-hidden');
                if (response.bg_bt_text){
                    $('.viwse-background-render-button').removeClass('loading').data({bg_complete: '',bg_processing:''}).html(response.bg_bt_text);
                }
                if (response.bg_text){
                    $('.viwse-background-render-status').html(response.bg_text);
                }
                if (response.message) {
                    $(document.body).trigger('villatheme_show_message', [response.message, [response.status, 'search_ajax_enable'], '', false, 4500]);
                }
            },
            error: function (err) {
                console.log(err)
                button.removeClass('loading');
                $('.viwse-button-save').attr('type', 'submit');
            }
        })
    });
    $('.viwse-search-result-position').sortable({
        connectWith: ".viwse-search-result-position",
        handle: ".viwse-search-result-item",
        placeholder: "viwse-placeholder",
        axis: "y",
    });
    if (viwse_params.current_page ==='viwse-suggestion'){
        setTimeout(function () {
            jQuery(document).trigger('vib2bc-suggested_products-html');
        }, 100);
    }
    $(document).on('click', '.viwse-suggestion-save', function () {
        let button = $(this);
        $.ajax({
            url: "admin-ajax.php?action=viwse_save_suggestion",
            type: "POST",
            data: {
                suggested_products: JSON.parse($('[name=suggested_products]').val()),
                suggest_related_product: $('#suggest_related_product').val() ,
                nonce: $('#_viwse_nonce').val() ,
            },
            beforeSend: function () {
                button.addClass('loading');
            },
            success: function (response) {
                button.removeClass('loading');
                if (response.message) {
                    $(document.body).trigger('villatheme_show_message', [response.message, [response.status, 'edit-field'], '', false, 4500]);
                }
            },
            error: function (err) {
                console.log(err)
                button.removeClass('loading');
            }
        })
    });
    $(document).on('click','.viwse-shortcode-info',function (e){
        navigator.clipboard.writeText($(this).html());
        $(document.body).trigger('villatheme_show_message', ['Copied to Clipboard', ['success'], '', false, 1000]);
    });
    $(document).on('click','.viwse-button-create-shortcode',function (){
        if (!$('.viwse-popup-wrap-suggestion-info').length) {
            $(document).trigger('viwse-suggestion-popup-html',[true]);
        }
    });
    $(document).on('click','.viwse-button-add-suggested-products, .viwse-suggestion-edit',function (){
        $(this).closest('.viwse-suggestion-wrap').addClass('viwse-suggestion-wrap-editing');
        if (!$('.viwse-popup-wrap-suggestion-info').length) {
            $(document).trigger('viwse-suggestion-popup-html');
        }
    });
    $(document).on('click', '.viwse-suggestion-enable, .viwse-suggestion-disable', function (e) {
        $(this).closest('.viwse-suggestion-wrap').addClass('viwse-suggestion-wrap-editing');
        let suggested_products = JSON.parse($('[name=suggested_products]').val()),
            sug_id = $(this).closest('.viwse-suggestion-wrap').data('id');
        let data = suggested_products[sug_id] || {};
        if ($(this).hasClass('viwse-suggestion-enable')) {
            data['enable'] = '';
            $('.viwse-suggestion-wrap-editing').removeClass('viwse-suggestion-wrap-enable');
            $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-disable').removeClass('viwse-hidden');
            $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-enable').addClass('viwse-hidden');
        } else {
            data['enable'] = 1;
            $('.viwse-suggestion-wrap-editing').addClass('viwse-suggestion-wrap-enable');
            $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-disable').addClass('viwse-hidden');
            $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-enable').removeClass('viwse-hidden');
        }
        suggested_products[sug_id] = data;
        $('.viwse-suggestion-wrap-editing').data('data', data).removeClass('viwse-suggestion-wrap-editing');
        $('[name=suggested_products]').val(JSON.stringify(suggested_products));
    });
    $(document).on('click', '.viwse-suggestion-remove', function (e) {
        if (!confirm(viwse_params.remove_suggestion_title)) {
            return false;
        }
        let suggested_products = JSON.parse($('[name=suggested_products]').val()),
            sug_id = $(this).closest('.viwse-suggestion-wrap').data('id');
        if (suggested_products[sug_id]){
            delete  suggested_products[sug_id];
        }
        $('[name=suggested_products]').val(JSON.stringify(suggested_products));
        $(this).closest('.viwse-suggestion-wrap').remove();
    });
    $(document).on('viwse-suggestion-popup-html', function (e, create_shortcode = false) {
        let data = $('.viwse-suggestion-wrap-editing').data('data') || {},
            products= JSON.parse($('[name=products_info]').val()),
            categories= JSON.parse($('[name=categories_info]').val());
        let title = data['title'] ?? '',
            is_slide=data['is_slide'] ??'1',
            limit=data['limit'] ??20,
            cols=data['cols'] ??4,
            cols_mobile=data['cols_mobile'] ??2,
            suggestion=data['suggestion'] ??['top_searched'],
            include_products=data['include_products'] ??[],
            include_categories=data['include_categories'] ??[],
            out_of_stock =data['out_of_stock'] ??1;
        let html = '<div class="viwse-popup-wrap viwse-popup-wrap-suggestion-info">';
        html += '<div class="viwse-popup"><div class="viwse-overlay viwse-overlay-loading"></div><div class="viwse-overlay"></div>';
        html += '<div class="viwse-popup-container-wrap"><span class="viwse-popup-close">&#43;</span>';
        html += '<div class="viwse-popup-container">';
        html += '<div class="viwse-popup-header-wrap">'+(create_shortcode ? viwse_params.popup_shortcode_header :viwse_params.popup_suggestion_header)+'</div>';
        html += '<div class="viwse-popup-content-wrap"><div class="viwse-popup-content-container">';
        if (!create_shortcode){
            let suggestion_id = $('.viwse-suggestion-wrap-editing').data('id') || new Date().getTime(),
                enable = data['enable'] ?? 1;
            html += `<div class="viwse-popup-content">`;
            html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
            html += `<div class="viwse-popup-content-title">${viwse_params.enable_title}</div>`;
            html += '<div class="viwse-popup-content-value">';
            html += `<input type="hidden" class="viwse-sug-id" value="${suggestion_id}">`;
            html += '<div class="vi-ui checkbox toggle"><input type="hidden" value="'+enable+'" name="enable">';
            html += '<input type="checkbox" '+(enable ? 'checked':'')+'></div>';
            html += '</div></div></div>';
        }
        html += '<div class="viwse-popup-content">';
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_title}</div>`;
        html += '<div class="viwse-popup-content-value">';
        html += `<input type="text" name="title" class="viwse-shortcode-title" value="${title}" placeholder="You may also like…">`;
        html += '</div></div></div>';
        html += `<div class="viwse-popup-content">`;
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_suggestion}</div>`;
        html += '<div class="viwse-popup-content-value">';
        html += `<select class="viwse-shortcode-suggestion viwse-shortcode-suggestion-from vi-ui fluid dropdown viwse-shortcode-multi" name="suggestion" multiple>`;
        $.each(viwse_params.shortcode_suggestion_arg, function (k, v) {
            html += `<option value="${k}" >${v}</option>`;
        });
        html += '</select>';
        html += '</div></div></div>';
        html += `<div class="viwse-popup-content">`;
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_include_products}</div>`;
        html += '<div class="viwse-popup-content-value"><div class="viwse-shortcode-suggestion-from">';
        html += `<select class="viwse-search-select2 viwse-shortcode-multi" name="include_products" data-type_select2="product" multiple>`;
        $.each(include_products, function (k, v) {
            html += `<option value="${v}" selected >${(products[v]||v)}</option>`;
        });
        html += '</select>';
        html += '</div></div></div></div>';
        html += `<div class="viwse-popup-content">`;
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_include_categories}</div>`;
        html += '<div class="viwse-popup-content-value"><div class="viwse-shortcode-suggestion-from">';
        html += `<select class="viwse-search-select2 viwse-shortcode-multi" name="include_categories" data-type_select2="category" multiple>`;
        $.each(include_categories, function (k, v) {
            html += `<option value="${v}" selected >${(categories[v]||v)}</option>`;
        });
        html += '</select>';
        html += '</div></div></div></div>';
        html += `<div class="viwse-popup-content">`;
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_out_of_stock}</div>`;
        html += '<div class="viwse-popup-content-value">';
        html += '<div class="vi-ui checkbox toggle"><input type="hidden" value="'+out_of_stock+'" name="out_of_stock">';
        html += '<input type="checkbox" '+(out_of_stock ? 'checked':'')+'></div>';
        html += '</div></div></div>';
        html += `<div class="viwse-popup-content">`;
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_cols}</div>`;
        html += '<div class="viwse-popup-content-value viwse-shortcode-cols-wrap">';
        html += `<div class="vi-ui right labeled input"><input type="number" name="cols" class="viwse-shortcode-cols" min="2" max="6" value="${cols}"><label class="vi-ui label">${viwse_params.shortcode_cols_desktop}</label></div>`;
        html += `<div class="vi-ui right labeled input"><input type="number" name="cols_mobile" class="viwse-shortcode-cols" min="1" max="3" value="${cols_mobile}"><label class="vi-ui label">${viwse_params.shortcode_cols_mobile}</label></div>`;
        html += '</div></div></div>';
        html += `<div class="viwse-popup-content">`;
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_limit}</div>`;
        html += '<div class="viwse-popup-content-value">';
        html += `<input type="number" min="1" value="${limit}" name="limit">`;
        html += '</div></div></div>';
        html += `<div class="viwse-popup-content">`;
        html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
        html += `<div class="viwse-popup-content-title">${viwse_params.shortcode_is_slide}</div>`;
        html += '<div class="viwse-popup-content-value">';
        html += '<div class="vi-ui checkbox toggle"><input type="hidden" value="'+is_slide+'" name="is_slide">';
        html += '<input type="checkbox" '+(is_slide ? 'checked':'')+'></div>';
        html += '</div></div></div>';
        if (!create_shortcode){
            let search_page_position = data['search_page_position'] ?? 0,
                shop_page_position = data['shop_page_position'] ?? 0,
                cat_page_position = data['cat_page_position'] ?? 0,
                single_page_position = data['single_page_position'] ?? 'after_summary',
                cart_page_position = data['cart_page_position'] ?? 0,
                checkout_page_position = data['checkout_page_position'] ?? 0;
            html += `<div class="viwse-popup-content">`;
            html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
            html += `<div class="viwse-popup-content-title">${viwse_params.popup_sug_search_page}</div>`;
            html += '<div class="viwse-popup-content-value">';
            html += `<select class="viwse-sug-search-page-position viwse-sug-page-position vi-ui fluid dropdown" name="search_page_position">`;
            $.each(viwse_params.popup_sug_loop_arg, function (k, v) {
                let selected = k === search_page_position ? 'selected="selected"' :'';
                html += `<option value="${k}" ${selected}>${v}</option>`;
            });
            html += '</select>';
            html += '</div></div></div>';
            html += `<div class="viwse-popup-content">`;
            html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
            html += `<div class="viwse-popup-content-title">${viwse_params.popup_sug_shop_page}</div>`;
            html += '<div class="viwse-popup-content-value">';
            html += `<select class="viwse-sug-shop-page-position viwse-sug-page-position vi-ui fluid dropdown" name="shop_page_position">`;
            $.each(viwse_params.popup_sug_loop_arg, function (k, v) {
                let selected = k ===shop_page_position ? 'selected="selected"' :'';
                html += `<option value="${k}" ${selected}>${v}</option>`;
            });
            html += '</select>';
            html += '</div></div></div>';
            html += `<div class="viwse-popup-content">`;
            html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
            html += `<div class="viwse-popup-content-title">${viwse_params.popup_sug_cat_page}</div>`;
            html += '<div class="viwse-popup-content-value">';
            html += `<select class="viwse-sug-cat-page-position viwse-sug-page-position vi-ui fluid dropdown" name="cat_page_position">`;
            $.each(viwse_params.popup_sug_loop_arg, function (k, v) {
                let selected = k ===cat_page_position ? 'selected="selected"' :'';
                html += `<option value="${k}" ${selected}>${v}</option>`;
            });
            html += '</select>';
            html += '</div></div></div>';
            html += `<div class="viwse-popup-content">`;
            html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
            html += `<div class="viwse-popup-content-title">${viwse_params.popup_sug_single_page}</div>`;
            html += '<div class="viwse-popup-content-value">';
            html += `<select class="viwse-sug-single-page-position viwse-sug-page-position vi-ui fluid dropdown" name="single_page_position">`;
            $.each(viwse_params.popup_sug_single_arg, function (k, v) {
                let selected = k ===single_page_position ? 'selected="selected"' :'';
                html += `<option value="${k}" ${selected}>${v}</option>`;
            });
            html += '</select>';
            html += '</div></div></div>';
            html += `<div class="viwse-popup-content">`;
            html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
            html += `<div class="viwse-popup-content-title">${viwse_params.popup_sug_cart_page}</div>`;
            html += '<div class="viwse-popup-content-value">';
            html += `<select class="viwse-sug-cart-page-position viwse-sug-page-position vi-ui fluid dropdown" name="cart_page_position">`;
            $.each(viwse_params.popup_sug_cart_arg, function (k, v) {
                let selected = k ===cart_page_position ? 'selected="selected"' :'';
                html += `<option value="${k}" ${selected}>${v}</option>`;
            });
            html += '</select>';
            html += '</div></div></div>';
            html += `<div class="viwse-popup-content">`;
            html += `<div class="viwse-popup-content-horizontal viwse-popup-content-full-row">`;
            html += `<div class="viwse-popup-content-title">${viwse_params.popup_sug_checkout_page}</div>`;
            html += '<div class="viwse-popup-content-value">';
            html += `<select class="viwse-sug-checkout-page-position viwse-sug-page-position vi-ui fluid dropdown" name="checkout_page_position">`;
            $.each(viwse_params.popup_sug_checkout_arg, function (k, v) {
                let selected = k ===checkout_page_position ? 'selected="selected"' :'';
                html += `<option value="${k}" ${selected}>${v}</option>`;
            });
            html += '</select>';
            html += '</div></div></div>';
        }
        html += '</div></div>';
        html += '<div class="viwse-popup-footer-wrap">';
        if (create_shortcode) {
            html += `<span class="vi-ui button primary small viwse-popup-bt viwse-popup-bt-create-shortcode">Create shortcode</span>`;
        }else {
            html += `<span class="vi-ui button primary small viwse-popup-bt viwse-popup-bt-save-sug-info">Save</span>`;
        }
        html += '</div>';
        html += '</div></div></div></div>';
        html = $(html);
        html.find('.vi-ui.dropdown:not(.viwse-dropdown-init)').addClass('viwse-dropdown-init').dropdown();
        html.find('.vi-ui.checkbox:not(.viwse-checkbox-init)').addClass('viwse-checkbox-init').checkbox();
        setTimeout(function (wrap) {
            $(wrap).find('.viwse-shortcode-suggestion').dropdown('set selected', suggestion);
            wrap.find('.viwse-search-select2').each(function (){
                $(document).trigger('viwse-search-select2',[$(this)]);
            });
        }, 100, html);
        $('body').append(html);
        setTimeout(function () {
            $('.viwse-popup-wrap-suggestion-info').removeClass('viwse-popup-wrap-hidden').addClass('viwse-popup-wrap-show');
        }, 100);
        jQuery(document).trigger('viwse-disable-scroll');
    });
    $(document).on('click','.viwse-popup-bt-save-sug-info',function (){
        let position= [], suggestion=false,
            suggestion_arg = ['include_products','include_categories','suggestion'],
            position_arg = ['search_page_position','shop_page_position','cat_page_position','single_page_position','cart_page_position','checkout_page_position'];
        let wrap = $(this).closest('.viwse-popup-wrap'),
            suggested_products = JSON.parse($('[name=suggested_products]').val());
        wrap.find('.viwse-warning-wrap').removeClass('viwse-warning-wrap');
        let sug_id = wrap.find('.viwse-sug-id').val();
        let data = suggested_products[sug_id] || {};
        wrap.find('input, select').each(function (k,v) {
            let name = $(v).attr('name'),
                val = $(v).val();
            if (!name){
                return true;
            }
            if (suggestion_arg.includes(name) && val.length){
                suggestion = true;
            }
            if (position_arg.includes(name) && val && val !=='0'){
                switch (name){
                    case 'search_page_position':
                        position.push(`<span>Search results page: ${(viwse_params.popup_sug_loop_arg[val] || val)}</span>`);
                        break;
                    case 'shop_page_position':
                        position.push(`<span>Shop page: ${(viwse_params.popup_sug_loop_arg[val] || val)}</span>`);
                        break;
                    case 'cat_page_position':
                        position.push(`<span>Categories page: ${(viwse_params.popup_sug_loop_arg[val] || val)}</span>`);
                        break;
                    case 'single_page_position':
                        position.push(`<span>Single product page: ${(viwse_params.popup_sug_single_arg[val] || val)}</span>`);
                        break;
                    case 'cart_page_position':
                        position.push(`<span>Cart page: ${(viwse_params.popup_sug_cart_arg[val] || val)}</span>`);
                        break;
                    case 'checkout_page_position':
                        position.push(`<span>Checkout page: ${(viwse_params.popup_sug_checkout_arg[val] || val)}</span>`);
                        break;
                }
            }
            data[name]=val;
        });
        if (!suggestion){
            wrap.find('.viwse-shortcode-suggestion-from').addClass('viwse-warning-wrap');
            $(document.body).trigger('villatheme_show_message', ['The condition for the suggested products can not be empty. Please fill in at least one of the warning fields.', ['error'], '', false, 4500]);
            return false;
        }
        if (!position.length){
            wrap.find('.viwse-sug-page-position ').addClass('viwse-warning-wrap');
            $(document.body).trigger('villatheme_show_message', ['Please choose at least one position to show products suggestion.', ['error'], '', false, 4500]);
            return false;
        }
        $(this).addClass('loading');
        position = position.join('<br>');
        let suggest_from = 'Selected products';
        if (data['suggestion'] && data['suggestion'].length){
            suggest_from = [];
            $.each(data['suggestion'], function (k,v){
                suggest_from.push(viwse_params.shortcode_suggestion_arg[v] || v);
            });
            suggest_from = suggest_from.join(', ');
        }
        if (suggested_products[sug_id]){
            $('.viwse-suggestion-wrap-editing').data('data', data);
            if (data['enable']){
                $('.viwse-suggestion-wrap-editing').addClass('viwse-suggestion-wrap-enable');
                $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-disable').addClass('viwse-hidden');
                $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-enable').removeClass('viwse-hidden');
            }else {
                $('.viwse-suggestion-wrap-editing').removeClass('viwse-suggestion-wrap-enable');
                $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-disable').removeClass('viwse-hidden');
                $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-enable').addClass('viwse-hidden');
            }
            $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-title').html(data['title'] || '-');
            $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-suggest_from').html(suggest_from);
            $('.viwse-suggestion-wrap-editing').find('.viwse-suggestion-position').html(position);
        }else {
            let  html= `<tr class="viwse-suggestion-wrap${(data['enable'] ? ' viwse-suggestion-wrap-enable' : '')}">`;
            html += `<td class="viwse-suggestion-title">${data['title'] || '-'}</td>`;
            html += `<td class="viwse-suggestion-suggest_from">${suggest_from}</td>`;
            html += `<td class="viwse-suggestion-position">${position}</td>`;
            html += '<td><div class="viwse-suggestion-action-wrap">';
            html += `<span class="viwse-suggestion-edit" data-tooltip="${viwse_params.edit_title}"><i class="icon edit outline"></i></span>`;
            html += `<span class="viwse-suggestion-remove" data-tooltip="${viwse_params.remove_title}"><i class="icon trash alternate outline"></i></span>`;
            html += `<span class="viwse-suggestion-enable${(!data['enable'] ? ' viwse-hidden' : '')}" data-tooltip="${viwse_params.disable_title}"><i class="dashicons dashicons-visibility"></i></span>`;
            html += `<span class="viwse-suggestion-disable${(data['enable'] ? ' viwse-hidden' : '')}" data-tooltip="${viwse_params.enable_title}"><i class="dashicons dashicons-hidden"></i></span>`;
            html += '</div></td>';
            html += '</tr>';
            html = $(html);
            html.data({id: sug_id, data: data});
            $('.viwse-suggestion-for-pages').append(html);
        }
        suggested_products[sug_id] = data;
        $('[name=suggested_products]').val(JSON.stringify(suggested_products));
        setTimeout(function () {
            $('.viwse-popup-close').trigger('click');
        }, 100);
    });
    $(document).on('click','.viwse-popup-bt-create-shortcode',function (){
        let shortcode = '[viwse_suggestion ';
        let term=[], suggestion=false;
        $('.viwse-warning-wrap').removeClass('viwse-warning-wrap');
        $(this).closest('.viwse-popup-wrap-suggestion-info').find('input, select').each(function (k,v) {
            let name = $(v).attr('name'),
                val = $(v).val();
            if (!name){
                return true;
            }
            if ($(v).hasClass('viwse-shortcode-multi')|| $(v).closest('.viwse-shortcode-multi').length){
                val =val.join(',');
            }
            if ((name ==='include_products' || name==='include_categories' || name==='suggestion') && val){
                suggestion = true;
            }
            term.push(`${name}='${val}'`);
        });
        shortcode += term.join(' ') + ']';
        if (!suggestion){
            $('.viwse-shortcode-suggestion-from').addClass('viwse-warning-wrap');
            $(document.body).trigger('villatheme_show_message', ['The condition for the suggested products can not be empty. Please fill in at least one of the warning fields.', ['error'], '', false, 4500]);
            return false;
        }
        navigator.clipboard.writeText(shortcode);
        $(document.body).trigger('villatheme_show_message', ['A new shortcode is created and copied to Clipboard', ['success'], '', false, 1200]);
    });
    $(document).on('click', '.viwse-popup-close, .viwse-overlay:not(.viwse-overlay-loading)', function (e) {
        jQuery(document).trigger('viwse-enable-scroll');
        let wrap = $(this).closest('.viwse-popup-wrap');
        if (wrap.hasClass('viwse-popup-wrap-show')) {
            wrap.removeClass('viwse-popup-wrap-show').addClass('viwse-popup-wrap-hidden');
        }
        $('.viwse-suggestion-wrap-editing').removeClass('viwse-suggestion-wrap-editing');
        setTimeout(function (popup) {
            $(popup).remove();
        }, 100, wrap);
    });
    $(document).on('viwse-search-select2', function (e, select) {
        let placeholder = '', action = '', close_on_select = false, min_input = 2, type_select2 = select.data('type_select2');
        switch (type_select2) {
            case 'product':
                placeholder = 'Please fill in your product title';
                action = 'viwse_search_product';
                break;
            case 'category':
                placeholder = 'Please fill in your category title';
                action = 'viwse_search_category';
                break;
        }
        let select2_param = {
            closeOnSelect: close_on_select,
            placeholder: placeholder,
            cache: true
        };
        if (action) {
            select2_param['minimumInputLength'] = min_input;
            select2_param['escapeMarkup'] = function (markup) {
                return markup;
            };
            select2_param['ajax'] = {
                url: "admin-ajax.php?action=" + action,
                dataType: 'json',
                type: "GET",
                quietMillis: 50,
                delay: 250,
                data: function (params) {
                    return {
                        keyword: params.term,
                        nonce: jQuery('#_viwse_nonce').val() ,
                    };
                },
                processResults: function (data) {
                    if (data.length) {
                        let input = action === 'viwse_search_product' ? $('[name=products_info]') : $('[name=categories_info]');
                        let input_data = JSON.parse(input.val());
                        $.each(data, function (k, v){
                            input_data[v['id']] = v['text'];
                        });
                        input.val(JSON.stringify(input_data));
                    }
                    return {
                        results: data
                    };
                },
                cache: true
            };
        }
        select.select2(select2_param);
    });
    $(document).on('viwse-enable-scroll', function () {
        let scrollTop = parseInt($('html').css('top'));
        $('html').removeClass('viwse-noscroll');
        window.scrollTo({top: -scrollTop, behavior: 'instant'});
    });
    $(document).on('viwse-disable-scroll', function () {
        if ($(document).height() > $(window).height()) {
            let scrollTop = ($('html').scrollTop()) ? $('html').scrollTop() : $('body').scrollTop(); // Works for Chrome, Firefox, IE...
            $('html').addClass('viwse-noscroll').css('top', -scrollTop);
        }
    });
    $(document).on('vib2bc-suggested_products-html', function () {
        let suggested_products = JSON.parse($('[name=suggested_products]').val());
        if (!Object.keys(suggested_products).length) {
            return false;
        }
        $.each(suggested_products,function (sug_id, data){
            let suggest_from = 'Selected products',position= [];
            if (data['suggestion'] && data['suggestion'].length){
                suggest_from = [];
                $.each(data['suggestion'], function (k,v){
                    suggest_from.push(viwse_params.shortcode_suggestion_arg[v] || v);
                });
                suggest_from = suggest_from.join(', ');
            }
            if (data['search_page_position'] && data['search_page_position'] !== '0'){
                position.push(`<span>Search results page: ${(viwse_params.popup_sug_loop_arg[data['search_page_position']] || data['search_page_position'])}</span>`);
            }
            if (data['shop_page_position'] && data['shop_page_position'] !== '0'){
                position.push(`<span>Shop page: ${(viwse_params.popup_sug_loop_arg[data['shop_page_position']] || data['shop_page_position'])}</span>`);
            }
            if (data['cat_page_position'] && data['cat_page_position'] !== '0'){
                position.push(`<span>Categories page: ${(viwse_params.popup_sug_loop_arg[data['cat_page_position']] || data['cat_page_position'])}</span>`);
            }
            if (data['single_page_position'] && data['single_page_position'] !== '0'){
                position.push(`<span>Single product page: ${(viwse_params.popup_sug_single_arg[data['single_page_position']] || data['single_page_position'])}</span>`);
            }
            if (data['cart_page_position'] && data['cart_page_position'] !== '0'){
                position.push(`<span>Cart page: ${(viwse_params.popup_sug_cart_arg[data['cart_page_position']] || data['cart_page_position'])}</span>`);
            }
            if (data['checkout_page_position'] && data['checkout_page_position'] !== '0'){
                position.push(`<span>Checkout page: ${(viwse_params.popup_sug_checkout_arg[data['checkout_page_position']] || data['checkout_page_position'])}</span>`);
            }
            position = position.join('<br>');
            let  html= `<tr class="viwse-suggestion-wrap${(data['enable'] ? ' viwse-suggestion-wrap-enable' : '')}">`;
            html += `<td class="viwse-suggestion-title">${data['title'] || '-'}</td>`;
            html += `<td class="viwse-suggestion-suggest_from">${suggest_from}</td>`;
            html += `<td class="viwse-suggestion-position">${position}</td>`;
            html += '<td><div class="viwse-suggestion-action-wrap">';
            html += `<span class="viwse-suggestion-edit" data-tooltip="${viwse_params.edit_title}"><i class="icon edit outline"></i></span>`;
            html += `<span class="viwse-suggestion-remove" data-tooltip="${viwse_params.remove_title}"><i class="icon trash alternate outline"></i></span>`;
            html += `<span class="viwse-suggestion-enable${(!data['enable'] ? ' viwse-hidden' : '')}" data-tooltip="${viwse_params.disable_title}"><i class="dashicons dashicons-visibility"></i></span>`;
            html += `<span class="viwse-suggestion-disable${(data['enable'] ? ' viwse-hidden' : '')}" data-tooltip="${viwse_params.enable_title}"><i class="dashicons dashicons-hidden"></i></span>`;
            html += '</div></td>';
            html += '</tr>';
            html = $(html);
            html.data({id: sug_id, data: data});
            $('.viwse-suggestion-for-pages').append(html);
        })
    });
});