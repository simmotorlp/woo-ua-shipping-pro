(function ($) {
    'use strict';

    const settings = window.uaShippingPro || {};

    const $carrierSelect = $('select[name="ua_shipping_carrier"]');
    const $citySelect = $('select[name="ua_shipping_city"]');
    const $cityLabel = $('input[name="ua_shipping_city_label"]');
    const $warehouseSelect = $('select[name="ua_shipping_warehouse"]');
    const $warehouseLabel = $('input[name="ua_shipping_warehouse_label"]');
    let $carrierNotice = null;

    const placeholders = settings.strings || {};
    const directoryCarriers = settings.directoryCarriers || [];
    let currentCarrier = settings.carrier || settings.defaultCarrier || '';

    function getSelectedCarrier() {
        if ($carrierSelect.length) {
            const value = $carrierSelect.val();
            if (value) {
                return value;
            }
        }

        return currentCarrier || '';
    }

    function updateCurrentCarrier() {
        currentCarrier = getSelectedCarrier();
    }

    function supportsDirectories(carrier) {
        return directoryCarriers.indexOf(carrier) !== -1;
    }

    function setSelectDisabled($select, disabled) {
        if (!$select.length) {
            return;
        }

        $select.prop('disabled', disabled);

        if (settings.enableSelect && typeof $select.selectWoo === 'function') {
            $select.trigger('change');
        }
    }

    function handleCarrierAvailability(carrier) {
        const hasDirectories = supportsDirectories(carrier);

        setSelectDisabled($citySelect, !hasDirectories);
        setSelectDisabled($warehouseSelect, !hasDirectories);

        if ($carrierNotice === null && $carrierSelect.length) {
            $carrierNotice = $('<p class="ua-shipping-carrier-notice"></p>').hide();
            $carrierSelect.closest('.form-row').append($carrierNotice);
        }

        if ($carrierNotice) {
            if (hasDirectories) {
                $carrierNotice.hide();
            } else if (placeholders.carrierUnsupported) {
                $carrierNotice.text(placeholders.carrierUnsupported).show();
            }
        }

        if (!hasDirectories) {
            clearCity();
            clearWarehouse();
        }
    }

    function fetchOptions(action, extraParams) {
        const params = Object.assign({
            action,
            nonce: settings.nonce,
            carrier: getSelectedCarrier(),
        }, extraParams);

        return $.ajax({
            url: settings.ajaxUrl,
            method: 'GET',
            dataType: 'json',
            data: params,
        });
    }

    function processResults(response) {
        if (!response || !response.success || !response.data) {
            return { results: [] };
        }

        return response.data;
    }

    function initSelect($select, type) {
        if (!settings.enableSelect || !$select.length || typeof $select.selectWoo !== 'function') {
            return;
        }

        const placeholder = type === 'city' ? placeholders.cityPlaceholder : placeholders.warehousePlaceholder;

        $select.selectWoo({
            width: '100%',
            allowClear: true,
            placeholder,
            language: {
                inputTooShort: () => placeholders.cityPlaceholder || '',
                noResults: () => placeholders.noResults || '',
            },
            ajax: {
                transport: function (params, success, failure) {
                    const query = params.data || {};
                    const transportParams = {
                        term: query.term || '',
                    };

                    if (type === 'warehouse') {
                        transportParams.city_ref = $citySelect.val();
                        if (!transportParams.city_ref) {
                            success({ success: true, data: { results: [] } });
                            return;
                        }
                    }

                    fetchOptions(type === 'city' ? settings.actions.searchCities : settings.actions.getWarehouses, transportParams)
                        .done(success)
                        .fail(failure);
                },
                processResults: function (data) {
                    return processResults(data);
                },
                delay: 250,
            },
        });
    }

    function syncLabel($select, $label) {
        const value = $select.val();
        if (!value) {
            $label.val('');
            return;
        }

        if (settings.enableSelect && typeof $select.selectWoo === 'function') {
            const selected = $select.selectWoo('data');
            if (selected && selected.length) {
                $label.val(selected[0].text || '');
                return;
            }
        }

        const text = $select.find('option:selected').text();
        $label.val(text || '');
    }

    function clearCity() {
        if (!$citySelect.length) {
            return;
        }

        $citySelect.find('option').not(':first').remove();

        if (settings.enableSelect && typeof $citySelect.selectWoo === 'function') {
            $citySelect.val(null).trigger('change');
        } else {
            $citySelect.val('').trigger('change');
        }

        $cityLabel.val('');
    }

    function clearWarehouse() {
        if (!$warehouseSelect.length) {
            return;
        }

        $warehouseSelect.find('option').not(':first').remove();

        if (settings.enableSelect && typeof $warehouseSelect.selectWoo === 'function') {
            $warehouseSelect.val(null).trigger('change');
        } else {
            $warehouseSelect.val('').trigger('change');
        }

        $warehouseLabel.val('');
    }

    function populateSelect($select, action, params) {
        fetchOptions(action, params).done((response) => {
            const data = processResults(response).results || [];
            const currentValue = $select.val();
            let hasCurrent = false;

            $select.find('option').not(':first').remove();

            data.forEach((item) => {
                const selected = currentValue && currentValue === item.id;
                if (selected) {
                    hasCurrent = true;
                }

                const option = $('<option></option>')
                    .attr('value', item.id)
                    .text(item.text || item.id);

                $select.append(option);
            });

            if (!hasCurrent) {
                $select.val('').trigger('change');
            }
        });
    }

    function bootstrap() {
        if (!$citySelect.length) {
            return;
        }

        settings.actions = Object.assign({
            searchCities: 'ua_shipping_pro_search_cities',
            getWarehouses: 'ua_shipping_pro_get_warehouses',
        }, settings.actions || {});

        updateCurrentCarrier();
        handleCarrierAvailability(currentCarrier);

        initSelect($citySelect, 'city');
        initSelect($warehouseSelect, 'warehouse');

        if (!settings.enableSelect) {
            $citySelect.off('.uaShippingPro').on('focus.uaShippingPro', function () {
                populateSelect($citySelect, settings.actions.searchCities, { term: '' });
            });

            $warehouseSelect.off('.uaShippingPro').on('focus.uaShippingPro', function () {
                const cityRef = $citySelect.val();
                if (!cityRef) {
                    return;
                }

                populateSelect($warehouseSelect, settings.actions.getWarehouses, {
                    city_ref: cityRef,
                    term: '',
                });
            });
        }

        $carrierSelect.off('.uaShippingPro').on('change.uaShippingPro', function () {
            updateCurrentCarrier();
            handleCarrierAvailability(currentCarrier);
            clearCity();
            clearWarehouse();
        });

        $citySelect.off('change.uaShippingPro').on('change.uaShippingPro', function () {
            syncLabel($citySelect, $cityLabel);
            clearWarehouse();
        });

        $warehouseSelect.off('change.uaShippingPro').on('change.uaShippingPro', function () {
            syncLabel($warehouseSelect, $warehouseLabel);
        });

        // Sync initial values on load
        updateCurrentCarrier();
        syncLabel($citySelect, $cityLabel);
        syncLabel($warehouseSelect, $warehouseLabel);
    }

    $(document).on('ready', bootstrap);
    $(document.body).on('updated_checkout', bootstrap);
})(jQuery);
