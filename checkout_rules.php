<?php /* Template Name: Checkout Rules */ 
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

// log the raw request -- this makes debugging much easier
$filename = 'logs/'.time();
$input = file_get_contents('php://input');
//file_put_contents($filename.'-input', $input);

// parse the request
$rates = json_decode($input, true);

if (isset($_GET['test']) && $_GET['test'] == 'ship') {
    $rates = getTestData();
}

// log the array format for easier interpreting
file_put_contents($filename.'-debug', print_r($rates, true));

include_once(get_stylesheet_directory() . '/shipping-rules-class1.php');
include_once(get_stylesheet_directory() . '/shipping-options-class.php');

$ship = new shippingRules1();

//  check if exist carrier service
//$services = $ship->getServices();

$shippingOptions = new shippingOptions;

$output = $shippingOptions->getShippingRates($rates);

//echo '!!<pre>'; print_r($output); echo '</pre>'; exit();

/***   Apply shipping rules BOF   ***/
    
    //if ($order->delivery['country']['id'] == 223) {
    //if (!in_array($order->delivery['state'], array('Hawaii','Alaska','Puerto Rico','Guam'))) {
    if (!in_array($rates['rate']['destination']['province'], array('HI','AK','PR','GU'))) {
        $need_apply = false;

        $qty_array = array();

        //  loop products in cart
        //for ($jj=0; $jj<count($order->products); $jj++) {
        foreach ($rates['rate']['items'] as $key => $value) {
            //echo '<pre>'; print_r($value); echo '</pre>'; exit();
            $ship_cats = array();
            
            $product_found = false;
            
            //$prod_info = $ship->getProduct($value['product_id']);
            //echo '<pre>'; print_r($prod_info); echo '</pre>'; exit();
        
            //if (strpos($order->products[$jj]['id'], 'sample') > 0) {
                //  do nothing
            //} else {
                //  loop categories
                $sql = "SELECT category_id FROM wp_products_to_categories
                        WHERE product_id = ".$value['product_id'];
                //echo $sql; exit();
                //  https://admin.shopify.com/store/d9f499/collections/436839547187
                $p2c = $wpdb->get_results($sql);
                //echo '<pre>'; print_r($p2c); echo '</pre>'; exit();
                foreach ($p2c as $cat_res) {
                    $ship_cats[] = $cat_res->category_id;
                }

                //echo '<pre>'; print_r($ship_cats); echo '</pre>'; exit();

                foreach ($ship_cats as $ship_cat) {

                    foreach ($output as $opt_key => $ship_option) { 
                        //echo '<pre>'; print_r($ship_option); echo '</pre>'; //exit();
                    
                        //if (is_array($ship_option['methods'])) foreach ($ship_option['methods'] as $method_key => $ship_method) {
                            
                            $opt_id = strtok(htmlentities($ship_option['service_code']), '&');
                            
                            switch ($opt_id) {
                                    case 'fedex_first_overnight': $opt_id1 = 'FIRST_OVERNIGHT';  break;
                                    case 'fedex_priority_overnight': $opt_id1 = 'PRIORITY_OVERNIGHT';  break;
                                    case 'fedex_standard_overnight': $opt_id1 = 'STANDARD_OVERNIGHT';  break;
                                    case 'fedex_2day_am': $opt_id1 = 'FEDEX_2_DAY_AM';  break;
                                    case 'fedex_2day': $opt_id1 = 'FEDEX_2_DAY';  break;
                                    case 'fedex_express_saver': $opt_id1 = 'FEDEX_EXPRESS_SAVER';  break;
                                    case 'fedex_home_delivery': $opt_id1 = 'GROUND_HOME_DELIVERY';  break;
                                    case 'fedex_international_economy': $opt_id1 = 'INTERNATIONAL_ECONOMY';  break;
                                    case 'fedex_international_ground': $opt_id1 = 'FEDEX_GROUND';  break;
                                    default: $opt_id1 = $opt_id; break;
                            }
                            //echo $opt_id1.'<br>';
                            $sql = "SELECT * FROM wp_shipping_rules
                                    WHERE categories LIKE '%|".$ship_cat."|%'
                                    AND shippings LIKE '%|".html_entity_decode($opt_id1)."|%'
                                    AND status = 1";
                            //echo $sql.'<br>'; //exit();
                            $ship_results = $wpdb->get_results($sql);
                            //if ($ship_res->RecordCount() > 0) { 
                            if (count($ship_results) > 0) {
                                //echo 'count > 0<br />';
                                
                                if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                                    echo 'ship_cat: '.$ship_cat.'<br />';
                                }
                                foreach ($ship_results as $ship_res)
                                    if (!isset($qty_array[$ship_res->id]['products']) || !in_array($value['product_id'], $qty_array[$ship_res->id]['products'])) {
                                        //echo $ship_res->fields['id'].' += '.$order->products[$jj]['qty'].'<br>';
                                        $qty_array[$ship_res->id]['qty'] += $value['quantity'];
                                        $qty_array[$ship_res->id]['products'][] = $value['product_id'];
                                        //BREAK;
                                        $product_found = true;
                                    } 
                            }
                    }
                }
            //}  //  else    if (strpos($order->products[$jj]['id'], 'sample') > 0) {
            
            if (!$product_found) {
                BREAK;
            }
        }

        //echo '<pre>'; print_r($qty_array); echo '</pre>'; exit();

        if ($product_found) {
            
            //echo '<pre>'; print_r($qty_array); echo '</pre>'; exit();
        
            $apply_array = array();
        
            $ship_count = 0;
        
            //  check qty for rules
            foreach ($output as $opt_key => $ship_option) {
                //foreach ($ship_option['methods'] as $method_key => $ship_method) {
                
                    foreach ($qty_array as $key => $value) {
                        
                        $opt_id = strtok(htmlentities($ship_option['service_code']), '&');
                        
                        switch ($opt_id) {
                                    case 'fedex_first_overnight': $opt_id1 = 'FIRST_OVERNIGHT';  break;
                                    case 'fedex_priority_overnight': $opt_id1 = 'PRIORITY_OVERNIGHT';  break;
                                    case 'fedex_standard_overnight': $opt_id1 = 'STANDARD_OVERNIGHT';  break;
                                    case 'fedex_2day_am': $opt_id1 = 'FEDEX_2_DAY_AM';  break;
                                    case 'fedex_2day': $opt_id1 = 'FEDEX_2_DAY';  break;
                                    case 'fedex_express_saver': $opt_id1 = 'FEDEX_EXPRESS_SAVER';  break;
                                    case 'fedex_home_delivery': $opt_id1 = 'GROUND_HOME_DELIVERY';  break;
                                    case 'fedex_international_economy': $opt_id1 = 'INTERNATIONAL_ECONOMY';  break;
                                    case 'fedex_international_ground': $opt_id1 = 'FEDEX_GROUND';  break;
                                    default: $opt_id1 = $opt_id; break;
                            }
                        
                        $sql = "SELECT * FROM wp_shipping_rules
                                WHERE status = 1
                                AND shippings LIKE '%|".html_entity_decode($opt_id1)."|%'
                                AND min_qty <= ".$value['qty']."
                                AND max_qty >= ".$value['qty'];
                        $ship_results = $wpdb->get_results($sql);
                                //echo $sql.'<br>'; 
                        if (count($ship_results) > 0) { 
                            foreach ($ship_results as $ship_res) {
                                if (!in_array($ship_res->id, $apply_array)) {
                                    $apply_array[] = $ship_res->id;
                                }
                            }
                        }
                    }
                //}
            }
            
            if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                //echo '<pre>'; print_r($apply_array); echo '</pre>'; //exit();
                //echo '<pre>'; print_r($qty_array); echo '</pre>'; exit();
            }

            //  calculate qty in cart
            $cart_qty = 0;
            foreach ($rates['rate']['items'] as $key => $value) { //echo '<pre>'; print_r($value); echo '</pre>'; exit();
                if (isset($value['quantity'])) {
                    $cart_qty += $value['quantity'];
                }
            }
//echo $qty_array[$apply_array[0]]['qty'].' == '.$cart_qty; exit();
            //if (count($apply_array) == 1 && $qty_array[$apply_array[0]]['qty'] == $cart_qty) {
            //if (count($apply_array) == 1)
            //foreach ($apply_array as $apply_item) {
                //echo '<pre>'; print_r($qty_array); echo '</pre>'; exit();
            foreach ($qty_array as $key => $value) {
                //echo $value['qty'].' == '.$cart_qty; exit();
                if ($value['qty'] == $cart_qty) {
                    //echo '<pre>'; print_r($output); echo '</pre>'; exit();
                    foreach ($output as $opt_key => $ship_option) { 
                        //foreach ($ship_option['methods'] as $method_key => $ship_method) {
                        
                            //foreach ($qty_array as $key => $value) {
                                
                                $opt_id = strtok(htmlentities($ship_option['service_code']), '&');
                                
                                switch ($opt_id) {
                                    case 'fedex_first_overnight': $opt_id1 = 'FIRST_OVERNIGHT';  break;
                                    case 'fedex_priority_overnight': $opt_id1 = 'PRIORITY_OVERNIGHT';  break;
                                    case 'fedex_standard_overnight': $opt_id1 = 'STANDARD_OVERNIGHT';  break;
                                    case 'fedex_2day_am': $opt_id1 = 'FEDEX_2_DAY_AM';  break;
                                    case 'fedex_2day': $opt_id1 = 'FEDEX_2_DAY';  break;
                                    case 'fedex_express_saver': $opt_id1 = 'FEDEX_EXPRESS_SAVER';  break;
                                    case 'fedex_home_delivery': $opt_id1 = 'GROUND_HOME_DELIVERY';  break;
                                    case 'fedex_international_economy': $opt_id1 = 'INTERNATIONAL_ECONOMY';  break;
                                    case 'fedex_international_ground': $opt_id1 = 'FEDEX_GROUND';  break;
                                    default: $opt_id1 = $opt_id; break;
                                }
                                
                                $sql = "SELECT * FROM wp_shipping_rules
                                        WHERE status = 1
                                        AND shippings LIKE '%|".html_entity_decode($opt_id1)."|%'
                                        AND id = ".$key."
                                        AND min_qty <= ".$value['qty']."
                                        AND max_qty >= ".$value['qty'];
                                //echo $sql.'<br>';  //exit();
                                $ship_results = $wpdb->get_results($sql);
                                //echo count($ship_results).'<br />';      
                                if (count($ship_results) > 0) { 
                                    if (isset($_GET['test']) && $_GET['test'] == 'ship') echo 'id: '.$ship_results[0]->id.'<br>'; //exit();
                                    //echo $ship_option['id'].'_'.$ship_method['id'].' = '.$ship_res->fields['price'].'<br>';
                                    //echo $opt_key.' '.$ship_results[0]->price.' < '.($ship_option['total_price'] / 100).'<br />';
                                    if ($ship_results[0]->price <= $ship_option['total_price'] / 100) {
                                        if (isset($_GET['test']) && $_GET['test'] == 'ship') echo 'price '.$ship_results[0]->price.'<br />';
                                        $output[$opt_key]['total_price'] = $ship_results[0]->price * 100;
                                    }
                                }
                            //}
                        //}
                    }
                }
            }
            
        }
    }  //  if (!in_array($order->delivery['state'], array('Hawaii','Alaska','Puerto Rico','Guam'))) {
    //}  //  if ($order->delivery['country']['id'] == 223) {
//echo '<pre>'; print_r($quotes_array); echo '</pre>'; //exit();

/***   Apply shipping rules EOF   ***/

if (isset($_GET['test']) && $_GET['test'] == 'ship') {
    echo '<pre>'; print_r($output); echo '</pre>'; exit();
}

$output_res = array('rates' => $output);

// encode into a json response
$json_output = json_encode($output_res);

// log it so we can debug the response
file_put_contents($filename.'-output', $json_output);

// send it back to shopify
print $json_output;

function getTestData() {
    
    $rates = array(
                'rate' => array(
                    'origin' => array(
                        'country' => 'US',
                        'postal_code' => '90270',  //  90270   90058
                        'province' => 'CA',
                        'city' => 'Maywood',
                        'name' => '',
                        'address1' => '3400 Slauson Avenue',
                        'address2' => 'Unit A',
                        'address3' => '',
                        //'latitude' => '33.98813',
                        //'longitude' => '-118.20383',
                        'phone' => '2137452449',
                        'fax' => '',
                        'email' => '',
                        'address_type' => '',
                        'company_name' => 'My Store'
                    ),
                    
                    'destination' => array(
                        'country' => 'US',
                        'postal_code' => '07748',
                        'province' => 'NJ',
                        'city' => 'New York',
                        'name' => 'TarasTaras Test',
                        'address1' => 'Central Park',
                        'address2' => '',
                        'address3' => '',
                        'latitude' => '',
                        'longitude' => '',
                        'phone' => '',
                        'fax' => '',
                        'email' => '',
                        'address_type' => '' ,
                        'company_name' => ''
                    ),
                    /*
                    'destination' => array(
                        'country' => 'FR',
                        'postal_code' => '59302',
                        'province' => '',
                        'city' => 'Bartolo',
                        'name' => 'TarasTaras Test',
                        'address1' => 'Central Park',
                        'address2' => '',
                        'address3' => '',
                        'latitude' => '',
                        'longitude' => '',
                        'phone' => '',
                        'fax' => '',
                        'email' => '',
                        'address_type' => '' ,
                        'company_name' => ''
                    ),
                    /*
                    'destination' => array(
                        'country' => 'CA',
                        'postal_code' => 'M5J 0B2',
                        'province' => '',
                        'city' => 'Ottawa',
                        'name' => 'TarasTaras Test',
                        'address1' => 'Central Park',
                        'address2' => '',
                        'address3' => '',
                        'latitude' => '',
                        'longitude' => '',
                        'phone' => '',
                        'fax' => '',
                        'email' => '',
                        'address_type' => '' ,
                        'company_name' => ''
                    ),
                    */
                    'items' => array (  
                                           
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'MV1',
                            'quantity' => isset($_GET['qty']) && (int)$_GET['qty'] > 0 ? (int)$_GET['qty'] : '1',
                            'grams' => isset($_GET['weight']) && (int)$_GET['weight'] > 0 ? (int)$_GET['weight'] :'200',
                            'price' => isset($_GET['price']) && (int)$_GET['price'] > 0 ? (int)$_GET['price'] :'800',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8170930176307',
                            'variant_id' => '44689736335667'
                        ),
                        /*
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'ZXD08617',
                            'quantity' => '15',
                            'grams' => '1383',
                            'price' => '2450',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8170931618099',
                            'variant_id' => '44690309447987'
                        ),
                        
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'ZXD06127',
                            'quantity' => '1',
                            'grams' => '907',
                            'price' => '1999',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8177881514291',
                            'variant_id' => '44690309447987'
                        ),
                        
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'FF3B-WCT-WL121-Peach',
                            'quantity' => '1',
                            'grams' => '1270',
                            'price' => '2350',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8178360680755',
                            'variant_id' => '44690309447987'
                        ),
                        
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'FF3B-WCT-WL121-Peach',
                            'quantity' => '1',
                            'grams' => '1361',
                            'price' => '2399',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8178397249843',
                            'variant_id' => '44690309447987'
                        ),
                        
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'FF3B-WCT-WL121-Peach',
                            'quantity' => '1',
                            'grams' => '426',
                            'price' => '450',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8178276499763',
                            'variant_id' => '44690309447987'
                        ),
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'FF3B-WCT-WL121-Peach',
                            'quantity' => '15',
                            'grams' => '254',
                            'price' => '480',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8178499387699',
                            'variant_id' => '44690309447987'
                        ),
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'FF3B-WCT-WL121-Peach',
                            'quantity' => '1',
                            'grams' => '1361',
                            'price' => '2350',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8178295701811',
                            'variant_id' => '44690309447987'
                        ),
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'FF3B-WCT-WL121-Peach',
                            'quantity' => '1',
                            'grams' => '1361',
                            'price' => '2350',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8178314248499',
                            'variant_id' => '44690309447987'
                        ),
                        array(
                            'name' => 'French Floral 3D Beaded Sequins Fabric / Peach / Sold By The Yard',
                            'sku' => 'FF3B-WCT-WL121-Peach',
                            'quantity' => '2',
                            'grams' => '1270',
                            'price' => '2350',
                            'vendor' => 'Big Z Fabric',
                            'requires_shipping' => '1',
                            'taxable' => '1',
                            'fulfillment_service' => 'manual',
                            'properties' => array() ,   
                            'product_id' => '8178371592499',
                            'variant_id' => '44690309447987'
                        )
                        */
                        
                    ),
                    'currency' => 'USD',
                    'locale' => 'en-US'
                )
            );
            //echo json_encode($rates); exit();
    return $rates;
    
}

?>
