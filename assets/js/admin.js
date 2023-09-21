jQuery( function ( $ ) {

    $('input#changeit').click(function (e){

        if( $('select#source').length > 0 && $('input#changeit').length > 0){

            const selectedSource = $('select#source').val();
            if( selectedSource.length ){
                if( selectedSource === 'bocs' || selectedSource === 'wordpress' || selectedSource === 'both'){
                    e.preventDefault();

                    if( selectedSource === 'bocs' || selectedSource === 'wordpress' ){
                        window.location.href = window.location.origin + window.location.pathname + '?source=' + selectedSource;
                    } else {
                        window.location.href = window.location.origin + window.location.pathname;
                    }

                }
            }

        }

    });


    if( $('select#source').length > 0 && $('input#changeit').length == 0){

        $('select#source').change(function (e){
            e.preventDefault();
            const selectedSource = $(this).val();

            if( selectedSource === 'bocs' || selectedSource === 'wordpress' ){
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

jQuery(window).on("load", function() {
    if( jQuery("#bocs-page-sidebar").length > 0 ){
        // then we will insert the getting of the list of collections and bocs

        jQuery( async function ($){

            try {

                collections_list = $.ajax({
                    url: ajax_object.collectionsURL,
                    type: "GET",
                    contentType: "application/json; charset=utf-8",
                    headers: {
                        'Organization': ajax_object.Organization,
                        'Store': ajax_object.Store,
                        'Authorization': ajax_object.Authorization
                    }
                });

                bocs_list = $.ajax({
                    url: ajax_object.bocsURL,
                    type: "GET",
                    contentType: "application/json; charset=utf-8",
                    headers: {
                        'Organization': ajax_object.Organization,
                        'Store': ajax_object.Store,
                        'Authorization': ajax_object.Authorization
                    }
                });

                jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                    value: '',
                    text: 'Please wait...'
                }));

                jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                    value: '',
                    text: 'Please wait...'
                }));

                await collections_list.then( (collections) => {
                    jQuery('#bocs-page-sidebar-collections').empty();

                    if(collections.data.length === 0){
                        jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                            value: '',
                            text: 'No Collections...'
                        }));
                    } else {

                        collections.data.forEach( (collection) => {
                            jQuery('#bocs-page-sidebar-collections').append(jQuery('<option>', {
                                value: collection.collectionId,
                                text: collection.name == '' ? collection.collectionId : collection.name
                            }));
                        });

                        jQuery('#bocs-page-sidebar-collections').on('change', function() {

                            jQuery('#bocs-shortcode-copy').html("[bocs collection='" + this.value + "']");

                        });
                    }


                });

                await bocs_list.then( (bocs) => {

                    jQuery('#bocs-page-sidebar-bocs').empty();

                    if( bocs.data.length === 0  ){
                        jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                            value: '',
                            text: 'No Bocs...'
                        }));
                    } else {

                        bocs.data.forEach( (boc) => {

                            jQuery('#bocs-page-sidebar-bocs').append(jQuery('<option>', {
                                value: boc.bocsId,
                                text: boc.name == '' ? boc.bocsId : boc.name
                            }));

                        });

                        jQuery('#bocs-page-sidebar-bocs').on('change', function() {

                            jQuery('#bocs-shortcode-copy').html("[bocs widget='" + this.value + "']");

                        });
                    }

                });

            } catch (error){
                console.error(error);
            }
        });


    }
});