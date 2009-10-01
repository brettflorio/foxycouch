<?php
require "couchdb.class.inc.php";
require "config.inc.php";

if (isset($_REQUEST['order'])) {
    $order_id = $_REQUEST['order'];
    $find_order = $couchdb->send("/{$order_id}");

    $order = $find_order->getBodyAsObject();
    if (!$order->error) {
        echo "This is what the processor responded with when processing order #{$order->id}:<br/>";
        foreach ($order->processed as $processor) {
            die($processor->processor_response_body);
        }
    }
}
?>
