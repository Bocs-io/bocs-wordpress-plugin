jQuery(function ($) {

    // Event handler for the "change" button
    $('input#changeit').click(function (e) {
        if ($('select#source').length > 0 && $('input#changeit').length > 0) {
            const selectedSource = $('select#source').val();
            if (selectedSource.length) {
                if (selectedSource === 'bocs' || selectedSource === 'wordpress' || selectedSource === 'both') {
                    e.preventDefault();

                    // Redirect based on selected source
                    if (selectedSource === 'bocs' || selectedSource === 'wordpress') {
                        window.location.href = window.location.origin + window.location.pathname + '?source=' + selectedSource;
                    } else {
                        window.location.href = window.location.origin + window.location.pathname;
                    }
                }
            }
        }
    });

    // Event handler for changing the source selection
    if ($('select#source').length > 0 && $('input#changeit').length == 0) {
        $('select#source').change(function (e) {
            e.preventDefault();
            const selectedSource = $(this).val();

            // Redirect based on selected source
            if (selectedSource === 'bocs' || selectedSource === 'wordpress') {
                window.location.href = window.location.origin + window.location.pathname + '?source=' + selectedSource;
            } else {
                window.location.href = window.location.origin + window.location.pathname;
            }
        });
    }
});

let collections_list = [];
let bocs_list = [];
let widgets_list = [];

let collection_options = [];
let bocs_options = [];
let widgets_options = [];

jQuery(window).on("load", function () {
    if (jQuery("#bocs-page-sidebar").length > 0) {

        jQuery(async function ($) {

            try {
                // Commented out: Populate the Collections dropdown
                /*
                jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                    value: '',
                    text: 'Please wait...'
                }));
                */

                // Commented out: Populate the Bocs dropdown
                /*
                jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                    value: '',
                    text: 'Please wait...'
                }));
                */

                // Populate the Widgets dropdown
                jQuery('#bocs-page-sidebar-widgets').append(jQuery('<option>', {
                    value: '',
                    text: 'Please wait...'
                }));

                // Commented out: Handle collections
                /*
                if (bocsAjaxObject.bocs_collections) {
                    if (bocsAjaxObject.bocs_collections.length === 0) {
                        jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                            value: '',
                            text: 'No Collections...'
                        }));
                    } else {
                        jQuery('#bocs-page-sidebar-collections').empty();
                        jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                            value: '',
                            text: 'Please select...'
                        }));
                        bocsAjaxObject.bocs_collections.forEach((collection) => {
                            jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                                value: collection['id'],
                                text: collection['name'] === '' ? collection['id'] : collection['name']
                            }));
                        });
                    }
                } else {
                    collections_list = $.ajax({
                        url: bocsAjaxObject.collectionsURL,
                        type: "GET",
                        contentType: "application/json; charset=utf-8",
                        headers: {
                            'Organization': bocsAjaxObject.Organization,
                            'Store': bocsAjaxObject.Store,
                            'Authorization': bocsAjaxObject.Authorization
                        }
                    });

                    await collections_list.then((collections) => {
                        jQuery('#bocs-page-sidebar-collections').empty();

                        if (collections.data.length === 0) {
                            jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                                value: '',
                                text: 'No Collections...'
                            }));
                        } else {
                            jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                                value: '',
                                text: 'Please select...'
                            }));

                            collections.data.forEach((collection) => {
                                jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                                    value: collection.id,
                                    text: collection.name === '' ? collection.id : collection.name
                                }));
                            });
                        }
                    });
                }
                */

                // Commented out: Handle Bocs
                /*
                if (bocsAjaxObject.bocs_widgets) {
                    if (bocsAjaxObject.bocs_widgets.length === 0) {
                        jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                            value: '',
                            text: 'No Bocs...'
                        }));
                    } else {
                        jQuery('#bocs-page-sidebar-bocs').empty();
                        jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                            value: '',
                            text: 'Please select...'
                        }));
                        bocsAjaxObject.bocs_widgets.forEach((bocs) => {
                            jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                                value: bocs['id'],
                                text: bocs['name'] === '' ? bocs['id'] : bocs['name']
                            }));
                        });
                    }
                } else {
                    bocs_list = $.ajax({
                        url: bocsAjaxObject.bocsURL,
                        type: "GET",
                        contentType: "application/json; charset=utf-8",
                        headers: {
                            'Organization': bocsAjaxObject.Organization,
                            'Store': bocsAjaxObject.Store,
                            'Authorization': bocsAjaxObject.Authorization
                        }
                    });

                    await bocs_list.then((bocs) => {
                        jQuery('#bocs-page-sidebar-bocs').empty();

                        if (bocs.data.length === 0) {
                            jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                                value: '',
                                text: 'No Bocs...'
                            }));
                        } else {
                            jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                                value: '',
                                text: 'Please select...'
                            }));

                            bocs.data.forEach((boc) => {
                                jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                                    value: boc.id,
                                    text: boc.name === '' ? boc.id : boc.name
                                }));
                            });
                        }
                    });
                }
                */

                // Handle Widgets
                widgets_list = $.ajax({
                    url: bocsAjaxObject.widgetsURL,
                    type: "GET",
                    contentType: "application/json; charset=utf-8",
                    headers: {
                        'Organization': bocsAjaxObject.Organization,
                        'Store': bocsAjaxObject.Store,
                        'Authorization': bocsAjaxObject.Authorization
                    }
                });

                await widgets_list.then((widgets) => {
                    jQuery('#bocs-page-sidebar-widgets').empty();

                    if (widgets.data.data.length === 0) {
                        jQuery('#bocs-page-sidebar-widgets').append(jQuery('<option>', {
                            value: '',
                            text: 'No Widgets...'
                        }));
                    } else {
                        jQuery('#bocs-page-sidebar-widgets').append(jQuery('<option>', {
                            value: '',
                            text: 'Please select...'
                        }));

                        widgets.data.data.forEach((widget) => {
                            jQuery('#bocs-page-sidebar-widgets').append(jQuery('<option>', {
                                value: widget.id,
                                text: widget.title === '' ? widget.id : widget.title
                            }));
                        });
                    }
                });

            } catch (error) {
                console.error(error);
            }
        });
    }

    // Commented out: Event handler for changing Bocs selection
    /*
    if (jQuery("#bocs-page-sidebar-bocs").length > 0) {
        jQuery('#bocs-page-sidebar-bocs').on('change', function () {
            if (this.value !== '') {
                jQuery("#bocs-page-sidebar-collections").prop('selectedIndex', 0);
                jQuery("#bocs-page-sidebar-widgets").prop('selectedIndex', 0);
                jQuery('#bocs-shortcode-copy').html("[bocs widget='" + this.value + "']");
            } else {
                jQuery('#bocs-shortcode-copy').html("");
            }
        });
    }
    */

    // Commented out: Event handler for changing Collections selection
    /*
    if (jQuery("#bocs-page-sidebar-collections").length > 0) {
        jQuery('#bocs-page-sidebar-collections').on('change', function () {
            if (this.value !== '') {
                jQuery("#bocs-page-sidebar-bocs").prop('selectedIndex', 0);
                jQuery("#bocs-page-sidebar-widgets").prop('selectedIndex', 0);
                jQuery('#bocs-shortcode-copy').html("[bocs collection='" + this.value + "']");
            } else {
                jQuery('#bocs-shortcode-copy').html("");
            }
        });
    }
    */

    // Event handler for changing Widgets selection
    if (jQuery("#bocs-page-sidebar-widgets").length > 0) {
        jQuery('#bocs-page-sidebar-widgets').on('change', function () {
            if (this.value !== '') {
                // Commented out: Reset other dropdowns
                /*
                jQuery("#bocs-page-sidebar-collections").prop('selectedIndex', 0);
                jQuery("#bocs-page-sidebar-bocs").prop('selectedIndex', 0);
                */
                jQuery('#bocs-shortcode-copy').html("[bocs widget_id='" + this.value + "']");
            } else {
                jQuery('#bocs-shortcode-copy').html("");
            }
        });
    }

});
