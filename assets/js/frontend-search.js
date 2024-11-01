jQuery(document).ready(function ($) {
    "use strict";
    if (typeof viwse_param === "undefined") {
        return false;
    }
    viwse_param.autocomplete = {
        containerClass: 'viwse-search-results-wrap',
        maxHeight: 500,
        zIndex: 99999999,
        serviceUrl: viwse_param.search_url,
        minChars: viwse_param.min_chars,
        paramName: 's',
        type: 'post',
        dataType: 'JSON',
        triggerSelectOnValidInput: false,
        currentRequest: viwse_param.pre_searched || null,
        onSelect: function (index) {
            if (typeof index.is_product !== "undefined" && index.id &&
                typeof viwse_param.search_key !== "undefined" && viwse_param.search_key) {
                $.ajax({
                    url: viwse_param.search_history,
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        product_id: index.id,
                        key: viwse_param.search_key,
                    }
                })
            }
            if (typeof index.url !== "undefined") {
                location.href = index.url;
            }
        },
        onSearchStart: function () {
            if (viwse_param.loading_icon) {
                $(this).parent().addClass('viwse-input-searching-wrap');
            }
        },
        onSearchComplete: function () {
            viwse_param['search_key'] = $(this).val();
            $('.viwse-input-searching-wrap').removeClass('viwse-input-searching-wrap');
        },
        formatResult: function (suggestion, currentValue) {
            let html = '', wrap_class = ['viwse-search-result'];
            if (suggestion.type) {
                $.each(suggestion.type, function (k, v) {
                    wrap_class.push('viwse-search-result-' + v);
                });
            }
            wrap_class = wrap_class.join(' ');
            if (suggestion.message) {
                html += `<div class="${wrap_class}">${suggestion.message}</div>`;
            } else if (suggestion.is_product) {
                let pattern = '(' + $.Autocomplete.utils.escapeRegExChars(currentValue) + ')';
                html += `<div class="${wrap_class}">`;
                if (viwse_param.suggestion_info && viwse_param.suggestion_info.includes('image')) {
                    html += `<div class="viwse-result-img"><img src="${suggestion.img_src || viwse_param.placeholder_img_src}" loading="lazy"></div>`;
                }
                html += `<div class="viwse-result-info-wrap">`;
                html += `<div class="viwse-result-info"><div class="viwse-result-title viwse-product-name">${suggestion.name.replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>')}</div>`;
                if (viwse_param.suggestion_info.includes('price')) {
                    html += `<div class="viwse-product-price">${suggestion.price}</div>`;
                }
                html += `</div>`;
                if (viwse_param.suggestion_info.includes('sku')) {
                    html += `<div class="viwse-result-info"><div class="viwse-product-sku">${suggestion.sku}</div></div>`;
                }
                html += `</div></div>`;
            } else if (suggestion.is_taxonomy) {
                let pattern = '(' + $.Autocomplete.utils.escapeRegExChars(currentValue) + ')';
                html += `<div class="${wrap_class}">`;
                html += `<div class="viwse-result-info-wrap">`;
                html += `<div class="viwse-result-info"><div class="viwse-result-title viwse-tax-name">${suggestion.name.replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>')}</div>`;
                html += `</div>`;
                html += `</div></div>`;
            }
            return html;
        }
    }
    $(document).on('focus', 'input[name="s"]', function () {
        if ($(this).hasClass('viwse-input-search')) {
            return false;
        }
        let form = $(this).closest('form[role="search"]');
        if (!form.length) {
            return false;
        }
        let post_type = form.find('[name="post_type"]').val();
        if (post_type !== 'product') {
            return false;
        }
        $(this).addClass('viwse-input-search').viwseAutocomplete(viwse_param.autocomplete);
    });
});
function viwse_ajax (options) {
    "use strict";
    const controller = new AbortController();
    window.fetch(options.url, {
        method: options.type,
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: options.data,
        signal: controller.signal
    }).then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw {statusText: response.statusText, responseText: text}
            });
        }
        return response.json();
    }).then(options.success).catch(error => options.error && options.error(error)).finally(() => options.complete && options.complete());
    return controller;
}