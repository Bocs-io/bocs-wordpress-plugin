<?php

?>

<div class="wrap">
    <h2>Bocs Subscriptions</h2>
<?php
$table = new Bocs_List_Table();
$table->prepare_items();
$table->display();
?>
</div>