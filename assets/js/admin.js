jQuery( function ( $ ) {

    console.log($('select#source').val());

    if( $('select#source').length > 0 && $('input#changeit').length > 0){

        const selectedSource = $('select#source').val();

        if( selectedSource.length ){
            if( selectedSource === 'bocs' || selectedSource === 'wordpress' || selectedSource === 'both'){
                $('input#changeit').click(function (e){
                    e.preventDefault();
                    window.location.href = window.location.origin + window.location.pathname + '?source=' + selectedSource;
                });
            }
        }

    }

});