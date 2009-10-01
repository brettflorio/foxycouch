<?php
require "couchdb.class.inc.php";
require "config.inc.php";

if (isset($_REQUEST['order'])) {
    $order_id = $_REQUEST['order'];
    $find_order = $couchdb->send("/{$order_id}");

    $order = $find_order->getBodyAsObject();
    if (!$order->error) {
        echo "This is the error response recorded while processing Order #$order->id:";
        die($order->errors[0]->response_body);
    }
}
?>
