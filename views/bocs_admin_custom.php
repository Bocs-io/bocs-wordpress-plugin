<?php
if ('product' === get_post_type()) {
    ?>

    <script type='text/javascript'>
        jQuery(document).ready(function () {
            //for Price tab
	 jQuery('.product_data_tabs .general_tab').addClass('show_if_bocs').show();
            jQuery('#general_product_data .pricing').addClass('show_if_bocs').show();
            //for Inventory tab
            jQuery('.inventory_options').addClass('show_if_bocs').show();
            jQuery('#inventory_product_data ._manage_stock_field').addClass('show_if_bocs').show();
            jQuery('#inventory_product_data ._sold_individually_field').parent().addClass('show_if_bocs').show();
            jQuery('#inventory_product_data ._sold_individually_field').addClass('show_if_bocs').show();
        });
    </script>

    <?php
}
?>