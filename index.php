<?php

require "couchdb.class.inc.php";
require "class.rc4crypt.php";

$couchdb = new CouchDB('foxy-orders');
try {
    $response = $couchdb->getDoc('preferences');
} catch(CouchDBException $e) {
    die($e->getMessage()."\n");
}

$preferences = $response->getBodyAsObject();


function error_notify($msg) {
    die($msg);
}


$FieldsRenamed = array("id" => "foxy_order_id",
                "store_id" => "foxy_store_id",
                "transaction_date" => "order_placed_at",
                "customer_id" => "foxy_customer_id",
                "transaction_details" => "order_items");

$AddressFields = array("first_name", "last_name", "company",
                       "address1", "address2", "city",
                       "state", "postal_code", "country",
                       "phone");

$OrderItemsField = 'transaction_details';   // Name of the XML element that contains the order items.

$StaticFields = array("type" => "order");   // Static fields set on every order.

function prependString($prefix, $suffix) { return $prefix . $suffix; }
function customerPrefix($field) { return prependString("customer_", $field); }
function shippingPrefix($field) { return prependString("shipping_", $field); }

$CustomerAddressFields = array_map(customerPrefix, $AddressFields); // Customer billing address fields.
$ShippingAddressFields = array_map(shippingPrefix, $AddressFields); // Order shipping address fields.

$ProductPrefix = "product_";
$SubscriptionPrefix = "subscription_";
$ProductOptionsField = "transaction_detail_options";
$ProductOptionsPrefix = "product_option_";
$NextTransactionDateField = "next_transaction_date";

$CustomFieldsName = 'custom_fields';

function extractProducts($node) {
    global $ProductPrefix, $SubscriptionPrefix, $ProductOptionsField, $ProductOptionsPrefix, $NextTransactionDateField;

    $products = array();
    foreach ($node->transaction_detail as $detail => $fields) {
        $product = array();
        foreach ($fields as $field => $value) {
            if (strncmp($field, $SubscriptionPrefix, strlen($SubscriptionPrefix)) == 0) {
                $product['subscription'] = (isset($product['subscription']) ? $product['subscription'] : array());

                if (!empty($value))
                    $product['subscription'][substr($field, strlen($SubscriptionPrefix))] = (string)$value;
            }
            else if (strcmp($field, $NextTransactionDateField) == 0) {
                $product['subscription'][$field] = (string)$value;
            }
            else if(strncmp($field, $ProductPrefix, strlen($ProductPrefix)) == 0) {
                $product[substr($field, strlen($ProductPrefix))] = (string)$value;
            }
            else if (strncmp($field, $ProductOptionsField, strlen($ProductOptionsField)) == 0) {
                $product['options'] = (isset($product['options']) ? $product['options'] : array());

                $option = array();
                foreach ($value->transaction_detail_option as $product_option) {
                    foreach ($product_option as $option_name => $option_value) {
                        if (strncmp($option_name, $ProductOptionsPrefix, strlen($ProductOptionsPrefix)) == 0) {
                            $option[substr($option_name, strlen($ProductOptionsPrefix))] = (string)$option_value;
                        }
                        else {
                            $option[$option_name] = (string)$option_value;
                        }
                    }
                }

                $product['options'][$option['name']] = $option;
            }
            else if (!empty($value)) {
                $product[$field] = (string)$value;
            }
            //echo substr($field, strlen($ProductPrefix)) . ': ' . serialize($value)."\n";
        }
        $products[] = $product;
    }

    return $products;
}

function fu_error_handler($errno, $message, $file, $line) {
    if ($errno < E_WARNING) {   // This is stupid, E_ERROR  < E_WARNING
        throw new Exception("$errno $message at $file:$line");
    }
}

set_error_handler(fu_error_handler);


if (isset($_POST['FoxyData'])) { // Receiving transmission from FoxyCart...
    $FoxyData = rc4crypt::decrypt($preferences->shared_secret, urldecode($_POST["FoxyData"]));
    $document = new SimpleXMLElement($FoxyData);

    if ($document->datafeed_version != "XML FoxyCart Version 0.8")
        error_notify("Wrong FoxyCart XML Version -- please set version 0.8 in your store configuration.");

    $order_index = 0;

    foreach ($document->transactions->transaction as $transaction) {
        $order = array();
        $shipToBilling = $order['ship_to_billing'] = empty($transaction->shipping_address1);


        try {
            foreach ($transaction as $field => $value) {
                if ($field == $OrderItemsField) {
                    $order[$FieldsRenamed[$field]] = extractProducts($value);
                }
                else if (in_array($field, $FieldsRenamed) && !empty($value)) {
                    $order[$FieldsRenamed[$field]] = (string)$value;
                }
                else if (in_array($field, $ShippingAddressFields) && !$order['ship_to_billing'] && !empty($value)) {
                    $order[$field] = (string)$value;
                }
                else if (count($value) >= 1) {
                    $order[$field] = array();

                    foreach ($value as $element) {
                        $order[$field][] = $element;
                    }
                }
                else if (!empty($value)) {
                    $order[$field] = (string)$value;
                }
            }
        }
        catch (Exception $err) {
            $order['errors'] = array('message' => $err->getMessage());   // Gracefully fail, record the error for later.
        }

        $duplicate_check = $couchdb->send("/_design/manager_screen/_view/orders_by_id?key=%22{$order['id']}%22");
        $duplicates = $duplicate_check->getBodyAsObject();
        if (count($duplicates->rows)) {
            $order['errors'] = (isset($order['errors']) ? $order['errors'] : array());
            $order['errors'][] = array('message' => "There is already an order with ID {$order['id']}.  Please manually reconcile.");
        }


        $order['raw_xml'] = array('data' => $FoxyData, 'transaction_index' => $order_index++);  // Store raw XML.
        $order = array_merge($order, $StaticFields);

        $response = $couchdb->send("/", "POST", json_encode($order));
    }
    die("foxy");
}
else if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'process') {
    $order_id = $_REQUEST['order'];
    $find_order = $couchdb->send("/{$order_id}");

    $order = $find_order->getBodyAsObject();
    if (!$order->error) {
        try {
            $order->errors = array();
            $order->processed = isset($order->processed) ? $order->processed : array();

            foreach ($preferences->processors as $processor) {
                $processor_name = $processor->name;
                $order_processed = isset($order->processed->$processor_name);

                if (!$order_processed || ($order_processed && $order->processed->$processor_name->error)) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $processor->endpoint);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, array("FoxyData" => $XMLOutput_encrypted));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $response_body = curl_exec($ch);
                    $response = curl_getinfo($ch);
                    curl_close($ch);

                    if ($response["http_code"] != 200) {
                        $order->errors[] = array("response_code" => $response['http_code'],
                         "message" => "Expected 200 response, got {$response['http_code']}",
                         "response_body" => $response_body,
                         "processor" => $processor_name);
                    }
                    else {
                        $order->errors = null;
                        $order->processed = array($processor_name => array("processed_at" => time(),
                        "processor_response_body" => $response_body));
                    }
                }
            }
        }
        catch (Exception $err) {
            $order->errors[] = array("message" => $err->getMessage());
            $order_processing_message = "Error processing order #{$order->id}";
        }

        $response = $couchdb->send("/{$order->_id}", "PUT", json_encode($order));
    }
    else {
        $order_processing_message = "Couldn't find that order, reason: {$order->error}";
    }
}


$orders_result = $couchdb->send("/_design/manager_screen/_view/orders_by_id");
$orders_summary = $orders_result->getBodyAsObject();
$orders = $orders_summary->rows;

echo '<'.'?xml version="1.0" encoding="UTF-8"'.'?>';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
	"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<title>foxy on the couch</title>
<link rel="stylesheet" href="css/reset.css" />
<link rel="stylesheet" href="css/text.css" />
<link rel="stylesheet" href="css/960.css" />
<style>

body {
	background: #999;
	border-top: 5px solid #000;
	color: #333;
	font-size: 11px;
	padding: 10px 0 40px;
}

a {
	color: #00f;
	text-decoration: none;
}

a:hover {
	text-decoration: underline;
}

h1 {
	font-family: Helvetica, Arial, sans-serif;
	font-weight: normal;
	text-align: left;
}

h2 {
	padding: 20px 0 0;
}

p {
	border: 1px solid #666;
	overflow: hidden;
	padding: 10px 0;
	text-align: center;
}

.container_12 {
	background: #fff url(img/12_col.gif) repeat-y;
	margin-bottom: 20px;
}

.container_16 {
	background: #fff url(img/16_col.gif) repeat-y;
}

table {
    border-collapse: none;
    width: 100%;
}
table thead th {
    text-align: left;
    background-color: #ccc;
    padding-top: 3em;
}
table tbody tr {
    line-height: 5em;
}

table tbody tr td {
    padding: 8px;
}

table tbody tr.error td {
    background-color: #fcc;
}

h3.notice {
    background-color: #acb;
    font-weight: normal;
}

</style>
    
</head>

<body>
<div class="container_12">
<h1>Foxy on the Couch</h1>
	<h2>
		Orders
	</h2>
    <?php if (!empty($order_processing_message)) { ?>
        <h3 class="notice"><?php echo $order_processing_message ?></h3>
    <?php } ?>
	<div class="grid_12 orders">
    <table>
        <thead>
            <tr>
                <th>&nbsp;</th>
                <th>Name</th>
                <th>Total</th>
                <th>Processed?</th>
                <th>Errors</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($orders as $order_row) {
             $order = $order_row->value;
    ?>
        <tr class="<?php echo isset($order->errors) ? " error" : "" ?>">
           <td><?php echo $order->id; ?></td>
           <td><?php echo $order->customer_first_name . ' ' . $order->customer_last_name; ?></td>
           <td><?php echo $order->order_total; ?></td>
           <td><?php echo (isset($order->processed) ? "yes <a href='processor_response.php?order={$order->_id}'>View Response</a>" : "no"); ?></td>
           <td><?php foreach ($order->errors as $error) {
                echo (isset($error->processor) ? "<strong>Processor:</strong> {$error->processor}<br/>\n" : "");
                echo "<strong>Message:</strong> {$error->message}<br/>\n";
                echo (isset($error->response_body) ? "<strong>Response Body:</strong> <a href='error_response.php?order={$order->_id}' target='_blank'>Click to View</a><br/>\n" : "");
                } ?>
           </td>
           <td><?php echo (!isset($order->processed) || count($order->errors) ? "<a href='?action=process&order={$order->_id}' class='cmd process'>[process now]</a>" : "") ?></td>
        </tr>
    <? } ?>
        </tbody>
    </table>
	</div>
	<h2>
		Preferences
	</h2>
    <div class="grid_12 preferences">
    <ul>
    <?php foreach($preferences as $key => $value) {
        if (is_array($value)) {
            echo "<li>{$key}: <ul>";
                foreach ($value as $sub_obj) {
                    foreach ($sub_obj as $sub_key => $sub_value)
                        echo "<li>{$sub_key}: \"{$sub_value}\"</li>";
                }
            echo "</ul></li>";
        }
        else {
            echo "<li>{$key}: \"{$value}\"</li>";
        }
    } ?>
    </ul>
    </div>
    <div class="clear"></div>
</div>

</body>
</html>
