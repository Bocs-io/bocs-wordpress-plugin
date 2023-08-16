console.log("before jQuery");
jQuery( function ( $ ) {

    console.log("before clicking button");

    $('input#changeit').click(function (e){

        console.log("button clicked");
        console.log($('select#source').length);
        console.log($('input#changeit').length);

        if( $('select#source').length > 0 && $('input#changeit').length > 0){

            const selectedSource = $('select#source').val();

            console.log('selectedSource: ' + selectedSource);

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