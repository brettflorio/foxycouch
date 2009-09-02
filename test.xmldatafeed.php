<?
/*
	test.xmldatafeed.php
	
	The purpose of this file is to help you set up and debug your FoxyCart XML DataFeed scripts.
	It's designed to mimic FoxyCart.com and send encrypted and encoded XML to a URL of your choice.
	It will print out the response that your script gives back, which should be "foxy" if successful.
	
	NOTE: This script uses cURL, which isn't always enabled, especially on shared hosting.
	
*/

require "couchdb.class.inc.php";

$couchdb = new CouchDB('foxy-orders');
try {
    $response = $couchdb->getDoc('preferences');
} catch(CouchDBException $e) {
    die($e->getMessage()."\n");
}
$preferences = $response->getBodyAsObject();

if (!$preferences || !$preferences->shared_secret) {
    die("Foxycouch hasn't been set up properly -- please make sure there is a 'preferences' document with a 'shared_secret' field in the foxy-orders database");
}

// ======================================================================================
// CHANGE THIS DATA:
// Set the URL you want to post the XML to.
// Set the key you entered in your FoxyCart.com admin.
// Modify the XML below as necessary.  DO NOT modify the structure, just the data
// ======================================================================================
$myURL = 'http://localhost/~fred/foxycouch/index.php';
$myKey = $preferences->shared_secret;

// This is FoxyCart Version 0.6 XML.  See http://wiki.foxycart.com/docs:datafeed?s[]=xml
$XMLOutput = <<<XML
<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
<foxydata>
<datafeed_version>XML FoxyCart Version 0.8</datafeed_version>
<transactions>
	<transaction>
		<id>2429</id>
		<store_id>13</store_id>
		<transaction_date>2009-03-06 11:16:36</transaction_date>
		<processor_response>Authorize.net Transaction ID:2429</processor_response>
		<customer_id>62</customer_id>
		<customer_first_name>John</customer_first_name>
		<customer_last_name>Doe</customer_last_name>
		<customer_company></customer_company>
		<customer_address1>555 Mulberry Dr.</customer_address1>
		<customer_address2></customer_address2>
		<customer_city>Happyville</customer_city>
		<customer_state>CA</customer_state>
		<customer_postal_code>90740</customer_postal_code>
		<customer_country>US</customer_country>
		<customer_phone></customer_phone>
		<customer_email>example@example.com</customer_email>
		<customer_ip>55.55.22.190</customer_ip>
		<shipping_first_name></shipping_first_name>
		<shipping_last_name></shipping_last_name>
		<shipping_company></shipping_company>
		<shipping_address1></shipping_address1>
		<shipping_address2></shipping_address2>
		<shipping_city></shipping_city>
		<shipping_state></shipping_state>
		<shipping_postal_code></shipping_postal_code>
		<shipping_country></shipping_country>
		<shipping_phone></shipping_phone>
		<shipping_service_description>UPS: 2nd Day Air</shipping_service_description>
		<purchase_order></purchase_order>
		<product_total>21.19</product_total>
		<tax_total>0.00</tax_total>
		<shipping_total>66.06</shipping_total>
		<order_total>71.95</order_total>
		<payment_gateway_type>authorize</payment_gateway_type>
		<receipt_url>https://example.foxycart.tld/receipt?id=40880711ff2c02bc689b54f9b0159d1d</receipt_url>
		<taxes>
			<tax>
				<tax_rate>5.0000</tax_rate>
				<tax_name>US</tax_name>
				<tax_amount>0.0000</tax_amount>
			</tax>
			<tax>
				<tax_rate>3.0000</tax_rate>
				<tax_name>California</tax_name>
				<tax_amount>0.0000</tax_amount>
			</tax>
		</taxes>
		<discounts>
			<discount>
				<code>test1</code>
				<name>$5 off all orders over $5!</name>
				<amount>-5.00</amount>
				<display>-5.00</display>
				<coupon_discount_type>price_amount</coupon_discount_type>
				<coupon_discount_details>5-5</coupon_discount_details>
			</discount>
			<discount>
				<code>test2</code>
				<name>Testing Again</name>
				<amount>-1.00</amount>
				<display>-1.00</display>
				<coupon_discount_type>quantity_amount</coupon_discount_type>
				<coupon_discount_details>3-1</coupon_discount_details>
			</discount>
		</discounts>
		<customer_password>912ec803b2ce49e4a541068d495ab570</customer_password>
		<custom_fields>
			<custom_field>
				<custom_field_name>example_hidden</custom_field_name>
				<custom_field_value>value_1</custom_field_value>
			</custom_field>
		</custom_fields>
		<transaction_details>
			<transaction_detail>
				<product_name>Example Product</product_name>
				<product_price>10.00</product_price>
				<product_quantity>3</product_quantity>
				<product_weight>4.000</product_weight>
				<product_code>abc123zzz</product_code>
				<subscription_frequency></subscription_frequency>
				<subscription_startdate>0000-00-00</subscription_startdate>
				<next_transaction_date>0000-00-00</next_transaction_date>
				<shipto></shipto>
				<category_description>Tools</category_description>
				<category_code>tools</category_code>
				<product_delivery_type>shipped</product_delivery_type>
				<transaction_detail_options>
					<transaction_detail_option>
						<product_option_name>color</product_option_name>
						<product_option_value>red</product_option_value>
						<price_mod>-4.000</price_mod>
						<weight_mod>0.000</weight_mod>
					</transaction_detail_option>
					<transaction_detail_option>
						<product_option_name>Price Discount Amount</product_option_name>
						<product_option_value>-5%</product_option_value>
						<price_mod>-0.267</price_mod>
						<weight_mod>0.000</weight_mod>
					</transaction_detail_option>
					<transaction_detail_option>
						<product_option_name>Quantity Discount</product_option_name>
						<product_option_value>-$0.67</product_option_value>
						<price_mod>-0.667</price_mod>
						<weight_mod>0.000</weight_mod>
					</transaction_detail_option>
				</transaction_detail_options>
			</transaction_detail>
			<transaction_detail>
				<product_name>Example Subscription</product_name>
				<product_price>10.00</product_price>
				<product_quantity>1</product_quantity>
				<product_weight>4.000</product_weight>
				<product_code>xyz456</product_code>
				<subscription_frequency>1m</subscription_frequency>
				<subscription_startdate>2009-12-01</subscription_startdate>
				<next_transaction_date>2009-12-01</next_transaction_date>
				<sub_token_url>https://example.foxycart.tld/cart?sub_token=fb5dfbbb51c81bdc60b033665515884c</sub_token_url>
				<shipto></shipto>
				<category_description>Default for all products</category_description>
				<category_code>DEFAULT</category_code>
				<product_delivery_type>shipped</product_delivery_type>
				<transaction_detail_options>
					<transaction_detail_option>
						<product_option_name>color</product_option_name>
						<product_option_value>red</product_option_value>
						<price_mod>-4.000</price_mod>
						<weight_mod>0.000</weight_mod>
					</transaction_detail_option>
				</transaction_detail_options>
			</transaction_detail>
		</transaction_details>
		<shipto_addresses>
		</shipto_addresses>
	</transaction>
</transactions>
</foxydata>
XML;


// ======================================================================================
// ENCRYPT YOUR XML
// Modify the include path to go to the rc4crypt file.
// ======================================================================================
include 'class.rc4crypt.php';
$XMLOutput_encrypted = rc4crypt::encrypt($myKey,$XMLOutput);
$XMLOutput_encrypted = urlencode($XMLOutput_encrypted);


// ======================================================================================
// POST YOUR XML TO YOUR SITE
// Do not modify.
// ======================================================================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $myURL);
curl_setopt($ch, CURLOPT_POSTFIELDS, array("FoxyData" => $XMLOutput_encrypted));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);


header("content-type:text/plain");
print $response;

?>
