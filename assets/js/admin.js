jQuery(function ($) {

    $('input#changeit').click(function (e) {

        if ($('select#source').length > 0 && $('input#changeit').length > 0) {

            const selectedSource = $('select#source').val();
            if (selectedSource.length) {
                if (selectedSource === 'bocs' || selectedSource === 'wordpress' || selectedSource === 'both') {
                    e.preventDefault();

                    if (selectedSource === 'bocs' || selectedSource === 'wordpress') {
                        window.location.href = window.location.origin + window.location.pathname + '?source=' + selectedSource;
                    } else {
                        window.location.href = window.location.origin + window.location.pathname;
                    }

                }
            }

        }

    });


    if ($('select#source').length > 0 && $('input#changeit').length == 0) {

        $('select#source').change(function (e) {
            e.preventDefault();
            const selectedSource = $(this).val();

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

let collection_options = [];
let bocs_options = [];

jQuery(window).on("load", function () {
    if (jQuery("#bocs-page-sidebar").length > 0) {
        // then we will insert the getting of the list of collections and bocs

        jQuery(async function ($) {

            try {

                jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                    value: '',
                    text: 'Please wait...'
                }));

                jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                    value: '',
                    text: 'Please wait...'
                }));

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
                                text: collection['name'] == '' ? collection['id'] : collection['name']
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
                                    text: collection.name == '' ? collection.id : collection.name
                                }));
                            });
                        }


                    });
                }


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
                                text: bocs['name'] == '' ? bocs['id'] : bocs['name']
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
                            'Authorization': s.Authorization
                        }
                    });

                    console.log(bocs_list);

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
                                    text: boc.name == '' ? boc.id : boc.name
                                }));

                            });
                        }

                    });
                }

            } catch (error) {
                console.error(error);
            }
        });


    }


    if (jQuery("#bocs-page-sidebar-bocs").length > 0) {
        jQuery('#bocs-page-sidebar-bocs').on('change', function () {

            if (this.value != '') {
                jQuery("#bocs-page-sidebar-collections").prop('selectedIndex', 0);
                jQuery('#bocs-shortcode-copy').html("[bocs widget='" + this.value + "']");
            } else jQuery('#bocs-shortcode-copy').html("");
        });
    }

    if (jQuery("#bocs-page-sidebar-collections").length > 0) {
        jQuery('#bocs-page-sidebar-collections').on('change', function () {

            if (this.value != '') {
                jQuery("#bocs-page-sidebar-bocs").prop('selectedIndex', 0);
                jQuery('#bocs-shortcode-copy').html("[bocs collection='" + this.value + "']");
            } else jQuery('#bocs-shortcode-copy').html("");

        });
    }

});