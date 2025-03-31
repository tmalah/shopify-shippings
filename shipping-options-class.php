<?php

use pdt256\Shipping\Shipment;
use pdt256\Shipping\Package;

use pdt256\Shipping\UPS;
use pdt256\Shipping\RateRequest;
use pdt256\Shipping\USPS;
//use pdt256\Shipping\Fedex;
use pdt256\Shipping\SmartPost;

class shippingOptions {
    
    protected $shipment;
    protected $package;
    protected $ups;
    protected $usps;
    protected $fedex;
    
    public function __construct() {
        
        
    }
    
    public function getShippingRates($data = array()) { 
        
        global $wpdb;
        
        $shipping_settings = unserialize(get_option('shipping_settings'));
        
        $output = array();
        
        //  get shipping rates
        $this->shipment = new Shipment;
        $this->shipment
            ->setFromIsResidential(false)
            ->setFromStateProvinceCode(isset($data['rate']['origin']['province']) ? $data['rate']['origin']['province'] : '')
            ->setFromPostalCode(isset($data['rate']['origin']['postal_code']) ? $data['rate']['origin']['postal_code'] : '')
            ->setFromCountryCode(isset($data['rate']['origin']['country']) ? $data['rate']['origin']['country'] : '')
            ->setToIsResidential(true)
            //->setToStateProvinceCode(isset($data['rate']['destination']['province']) ? $data['rate']['destination']['province'] : '')
            ->setToPostalCode(isset($data['rate']['destination']['postal_code']) ? $data['rate']['destination']['postal_code'] : '')
            ->setToCountryCode(isset($data['rate']['destination']['country']) ? $data['rate']['destination']['country'] : '');

        //  get total weight
        $total_weight = 0;
        $order_total = 0;
        foreach ($data['rate']['items'] as $key => $value) {
            $total_weight += $value['grams'] * $value['quantity'];
            $order_total += $value['quantity'] * $value['price'];
        }
        
        //$weight_lbs = $total_weight / 453.592;
//echo $order_total; exit();
        //  calculate shipping boxes
        $zc_boxes = 1;
        
        if ($total_weight > 22678) {
            $total_weight = $total_weight * 1.1;
        }
        $grand_weight = $total_weight;
        
        $shipping_num_boxes = 1;
      if ($total_weight > 22678) { // Split into many boxes
//        $shipping_num_boxes = ceil($shipping_weight/SHIPPING_MAX_WEIGHT);
        $zc_boxes = round(($total_weight/22678), 2);
        $shipping_num_boxes = ceil($zc_boxes);
        $total_weight = $total_weight/$shipping_num_boxes;
      }

    for ($i=0; $i<$shipping_num_boxes; $i++) {
        $this->package = new Package;
        $this->package
            ->setLength(12)
            ->setWidth(4)
            ->setHeight(3)
            ->setWeight($total_weight) // grams
            ->setValue(round($order_total/$shipping_num_boxes/100, 2));
        //echo '<pre>'; print_r(round($order_total/$shipping_num_boxes/100, 2)); echo '</pre>'; exit();
        $this->shipment->addPackage($this->package);
    }

        //$on_min_date = date('Y-m-d H:i:s O', strtotime('+1 day'));
        //$on_max_date = date('Y-m-d H:i:s O', strtotime('+2 days'));
        $on_min_date = '';
        $on_max_date = '';
        
        //  get UPS rates
/*
        $rates = $this->getUPSrates();
        
        if (isset($_GET['test']) && $_GET['test'] == 'ship') {
            //echo 'UPS rates<pre>'; print_r($rates); echo '</pre>'; //exit();
        }
            
        if ($rates && is_array($rates)) foreach ($rates as $key => $quote) { 
            
            if (!empty($quote)) {
            
                $code = str_replace(' ', '_', strtolower($quote->getName()));
                
                //  array('id' => 'fedexsmartpost_fedexsmartpost', 'text' => 'FedEx SmartPost'),
                
                $cost = $quote->getCost();
                
                if ($grand_weight >= 6349) {   //   20
                    switch ($code) {
                        case 'ups_ground': $cost = $cost*1.237; break;
                        case 'ups_3_day_select': $cost = $cost*1.122; break;
                        case 'ups_2nd_day_air': $cost = $cost*1.092; break;
                        case 'ups_next_day_air_saver': $cost = $cost*1.06; break;
                        case 'ups_next_day_air': $cost = $cost*1.053; break;
                    }
                }
                
                $output[] = array(
                    'service_name' => $quote->getName(),
                    'service_code' => $code,
                    'total_price' => $cost,
                    'currency' => 'USD',
                    'min_delivery_date' => $on_min_date,
                    'max_delivery_date' => $on_max_date
                );
                
            }
            
        }
//}        
*/  
      
        //  get USPS rates
        
        $rates = $this->getUSPSrates();
//echo '<pre>'; print_r($rates); echo '</pre>'; exit();

//echo '<pre>'; print_r($shipping_settings); echo '</pre>'; //exit();
        foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $cost = $quote->getCost();
            
            //  add handling per box
            $box_handling = 0;
            if ($data['rate']['destination']['country'] == 'US') {
                if (isset($shipping_settings['USPS']['box']['domestic'])) {
                   $box_handling = ((float)$shipping_settings['USPS']['box']['domestic'] * ($this->shipment->packageCount()))*100;
                }
            } else {
                if (isset($shipping_settings['USPS']['box']['int'])) {
                    $box_handling = ((float)$shipping_settings['USPS']['box']['int'] * ($this->shipment->packageCount()))*100;
                }
            }
            
            if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                echo 'box handling: '.$box_handling.'<br />';
            }
            
            $cost = $cost + $box_handling;
            
            //  add fee from settings
            $extra_fee = 0;
            $opt_id = strtok(htmlentities($code), '&');
            if (isset($shipping_settings['USPS']['handling'][$opt_id]) && $shipping_settings['USPS']['handling'][$opt_id] != '') {
                $handling_fee = $this->get_handling_fee($shipping_settings['USPS']['handling'][$opt_id]);
                $extra_fee = strpos($handling_fee, '%') ? ($cost * (float)$handling_fee / 100) : (float)$handling_fee*100;
            }
            
            $cost = $cost + $extra_fee;
            
            if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                //echo $this->shipment->packageCount(); exit();
                echo 'code '.$code.' : '.$opt_id.' : '.$handling_fee.' : '.$extra_fee.' : '.$cost.'<br />';
            }

            
            $output[] = array(
                'service_name' => 'USPS '.strip_tags(html_entity_decode($quote->getName())),
                'service_code' => $code,
                //'total_price' => $quote->getCost() + (isset($shipping_settings['handling']['USPS']['domestic']) ? (float)$shipping_settings['handling']['USPS']['domestic']*100 : 0),
                //'total_price' => $quote->getCost(),
                'total_price' => $cost,
                'currency' => 'USD',
                'min_delivery_date' => $on_min_date,
                'max_delivery_date' => $on_max_date
            );
            
        }
        //echo '!!!<pre>'; print_r($output); echo '</pre>'; exit();
              
 
        /***   GET FEDEX RATES   ***/
        
        $rates = $this->getFedexRates();
//echo '<pre>'; print_r($rates); echo '</pre>'; exit();            
            
        //  set categories fee if needed
        //  84,2497,2757,2529,2001,404,389,1035,1034,392,2523,1271,928,2235,408,400,986,394,402,858,405,683,397,382,385,383,985,387,406,388,409,390,2261,1758,2522,2501,2493,1865,2572,2239,2340,2298,2282,2457,2302,2059,1727,1873,2576,2455,57,172,214,198,173,233,2541,229,230,228,193,250,246,252,248,247,249,176,192,206,724,912,204,2135,182,187,191,202,175,207,209,920,196,194,256,260,259,189,183,188,242,241,2543,2544,195,213,180,203,181,184,174,2785,1601,178,186,1134,2546,179,2542,2545,2539,238,240,1984,1547,197,1278,177,1068,215
        //echo '<pre>'; print_r($shipping_settings); echo '</pre>'; exit();
		
		$fedex_excats_array = $shipping_settings['Fedex']['excats'];
		
        if (isset($_GET['fedex']) && $_GET['fedex'] == 'test') {
            $shipping_settings['Fedex'] = $shipping_settings['FedexRest'];
			$shipping_settings['Fedex']['excats'] = $fedex_excats_array;
        } elseif (isset($shipping_settings['fedexSwitch']) && $shipping_settings['fedexSwitch'] == 'new') {
            $shipping_settings['Fedex'] = $shipping_settings['FedexRest'];
			$shipping_settings['Fedex']['excats'] = $fedex_excats_array;
        }
        
        $tmp_option = unserialize(get_option('shipping_settings'));
        //echo '<pre>'; print_r($tmp_option); echo '</pre>'; exit();
        $exclude_cats_array = isset($tmp_option['excludeCats']) ? $tmp_option['excludeCats'] : array();

        if ($rates && is_array($rates)) foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $opt_id = $quote->getCode();
            
            $cost = $quote->getCost();
            
            if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                echo '---------<br />cost: '.$cost.'<br />';
            }    
//echo $grand_weight; exit();

            //echo $grand_weight; exit();
            
            //  15:32%,30:28%,40:23%,60:18%,90:10%,150:4%
            
            $is_exclude = false;
            foreach ($data['rate']['items'] as $key => $value) {
                $sql = "SELECT category_id FROM wp_products_to_categories
                        WHERE product_id = ".$value['product_id'];
                $exclude_cats = $wpdb->get_results($sql);
                
                foreach ($exclude_cats as $exclude_cat) { //echo '<pre>'; print_r($exclude_cats_array); echo '</pre>'; //exit();
                    if (in_array($exclude_cat->category_id, $exclude_cats_array)) { 
                        if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                            echo 'exclude '.$value['product_id'].' : '.$exclude_cat->category_id.'<br />';
                        }
                        $is_exclude = true;
                        BREAK;
                    }
                }
            }
            
            //echo '<pre>'; print_r($rates); echo '</pre>'; //exit();
            //echo '<pre>'; print_r($shipping_settings); echo '</pre>'; exit();
            
            //  add fee from settings
            $extra_fee = 0;

            if (isset($shipping_settings['Fedex']['handling'][$opt_id]) && $shipping_settings['Fedex']['handling'][$opt_id] != '') {
                $handling_fee = $this->get_handling_fee($shipping_settings['Fedex']['handling'][$opt_id]);
                $extra_fee = strpos($handling_fee, '%') ? ($cost * (float)$handling_fee / 100) : (float)$handling_fee*100;
            }
            
            if ($is_exclude) {
                //$extra_fee = $cost * 30 / 100;
                if ($data['rate']['destination']['country'] == 'US') {
                    if (isset($shipping_settings['Fedex']['excats']['domestic']) && $shipping_settings['Fedex']['excats']['domestic'] != '') {
                        $excats_fee = $this->get_handling_fee($shipping_settings['Fedex']['excats']['domestic']);
                        $extra_fee += strpos($excats_fee, '%') ? ($cost * (float)$excats_fee / 100) : (float)$excats_fee*100;
                    }
                } else {
                    if (isset($shipping_settings['Fedex']['excats']['int']) && $shipping_settings['Fedex']['excats']['int'] != '') {
                        $excats_fee = $this->get_handling_fee($shipping_settings['Fedex']['excats']['int']);
                        $extra_fee += strpos($excats_fee, '%') ? ($cost * (float)$excats_fee / 100) : (float)$excats_fee*100;
                    }
                }
                if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                    echo 'excats fee: '.$extra_fee.' = '.$cost.' * '.$excats_fee.'<br />';
                }
            }
            
            $cost = $cost + $extra_fee;
            
            if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                echo 'code '.$code.' : '.$opt_id.' : '.$handling_fee.' : '.$extra_fee.' : '.$cost.'<br />';
            }
            
            $opt_name = $quote->getName();
            if ($code == 'fedex_ground') {
                $opt_name = 'Fedex International Ground';
            }
            
            $output[] = array(
                'service_name' => $opt_name,
                'service_code' => $code,
                'total_price' => $cost,
                'currency' => 'USD',
                'min_delivery_date' => $on_min_date,
                'max_delivery_date' => $on_max_date
            );
            
        }
        //echo '<pre>'; print_r($output); echo '</pre>'; exit();
        
        //  get Smart Post rates
        
        $rates = $this->getSmartPostRates();
//echo '<pre>'; print_r($rates); echo '</pre>'; exit();
        
        if ($rates && is_array($rates) && !empty($rates[0])) foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $cost = $quote->getCost();
            $cost += $cost*3;
            
            $output[] = array(
                'service_name' => $quote->getName(),
                'service_code' => $code,
                'total_price' => $cost,
                'currency' => 'USD',
                'min_delivery_date' => $on_min_date,
                'max_delivery_date' => $on_max_date
            );
        }
        
        //echo '<pre>'; print_r($output); echo '</pre>'; exit();
        return $output;
        
    }
    
    public function getShippingOptions($data = array()) {
        
        //  get shipping rates
        $this->shipment = new Shipment;
        $this->shipment
            ->setFromIsResidential(false)
            ->setFromStateProvinceCode(isset($data['rate']['origin']['province']) ? $data['rate']['origin']['province'] : 'CA')
            ->setFromPostalCode(isset($data['rate']['origin']['postal_code']) ? $data['rate']['origin']['postal_code'] : '90401')
            ->setFromCountryCode(isset($data['rate']['origin']['country']) ? $data['rate']['origin']['country'] : 'US')
            ->setToIsResidential(true)
            ->setToPostalCode(isset($data['rate']['destination']['postal_code']) ? $data['rate']['destination']['postal_code'] : '78703')
            ->setToCountryCode(isset($data['rate']['destination']['country']) ? $data['rate']['destination']['country'] : 'US');
            
        //  get total weight
        //$total_weight = 0;
        //foreach ($data['rate']['items'] as $key => $value) {
        //    $total_weight += $value['grams'] * $value['quantity'];
        //}
        
        $this->package = new Package;
        $this->package
            ->setLength(12)
            ->setWidth(4)
            ->setHeight(3)
            ->setWeight(1);
        
        $this->shipment->addPackage($this->package);
        
        //$on_min_date = date('Y-m-d H:i:s O', strtotime('+1 day'));
        //$on_max_date = date('Y-m-d H:i:s O', strtotime('+2 days'));
        $on_min_date = '';
        $on_max_date = '';
        
        //  get UPS rates
        /*   
        $rates = $this->getUPSrates();
        
        echo '!!!<pre>'; print_r($rates); echo '</pre>'; exit();
        
        foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $output['UPS'][] = array(
                'id' => $code,
                'text' => $quote->getName()
            );
            
        }
        */
        //  get USPS rates
        
        $rates = $this->getUSPSrates(); 
        
        foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $output['USPS'][] = array(
                'id' => $code,
                'text' => 'USPS '.$quote->getName()
            );
            
        }

        //  get fedex rates
        $rates = $this->getFedexRates();
        
        foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $output['Fedex'][] = array(
                'id' => $code,
                'text' => $quote->getName()
            );
            
        }
        
        return $output;
        
    }
    
    public function getUSPSrates() {
  
        $approved_codes = $this->getApprovedUSPS();
        //echo '<pre>'; print_r($approved_codes); echo '</pre>'; exit();

        $this->usps = new USPS\Rate([
        	'prod'     => true,
        	'username' => '751BIGZF1824',
        	'password' => 'YAVzar23$',
        	'shipment' => $this->shipment,
        	'approvedCodes'  => $approved_codes,
            //'approvedCodes'  => [12, 13],
        	//'requestAdapter' => new RateRequest\StubUSPS(),
        ]);
  
        $rates = $this->usps->getRates();

        return $rates;
        
    }
    
    public function getApprovedUSPS() {
        
        $approved = array();
        
        $shipping_settings_tmp = unserialize(get_option('shipping_settings'));

        $shipping_settings = $shipping_settings_tmp['USPS'];
        foreach ($shipping_settings as $value) {
            //$new_settings[] = $value.'<sup>®</sup>';
        }
        
        $all_options = $this->getAllShippingOptions();
        //echo '<pre>'; print_r($shipping_settings); echo '</pre>'; //exit();
        //echo '<pre>'; print_r($all_options); echo '</pre>'; exit();
        
        foreach ($all_options['USPS'] as $key => $value) {
            
            if (in_array($key, $shipping_settings)) {
                
                $approved[] = $value['code'];
                
            }
            
        }
        //echo '<pre>'; print_r($approved); echo '</pre>'; exit();
        
        return $approved;
                
    }
    
    public function getUSPSallRates() {

        $this->usps = new USPS\Rate([
        	'prod'     => true,
        	'username' => '751BIGZF1824',
        	'password' => 'YAVzar23$',
        	'shipment' => $this->shipment,
        	'approvedCodes'  => [],
            //'approvedCodes'  => [12, 13],
        	//'requestAdapter' => new RateRequest\StubUSPS(),
        ]);

        $rates = $this->usps->getRates();
//echo '<pre>'; print_r($rates); echo '</pre>'; exit();
        return $rates;
        
    }
    
    public function getFedexRates() {

    $shipping_settings = unserialize(get_option('shipping_settings'));
    //echo '<pre>'; print_r($shipping_settings); echo '</pre>'; exit();
        
    //if (isset($_GET['fedex']) && $_GET['fedex'] == 'test') {
    if ((isset($shipping_settings['fedexSwitch']) && $shipping_settings['fedexSwitch'] == 'new') 
        || (isset($_GET['fedex']) && $_GET['fedex'] == 'test') || 1==1) {
        
		//  TEST
        //define('MODULE_SHIPPING_FEDEX_REST_API_KEY', 'l76b40a267246543a497a40b91333dac63');
        //define('MODULE_SHIPPING_FEDEX_REST_SECRET_KEY', '12ee567c2ee942c5b5d62f31eb3ced0c');
        
        //  PRODUCTION
        define('MODULE_SHIPPING_FEDEX_REST_API_KEY', 'l748f5ad3629ab4b6cb10283bf6752006d');
        define('MODULE_SHIPPING_FEDEX_REST_SECRET_KEY', 'de9e91b6-4af4-49ce-81eb-a744c05beab6');
        
        $approved_codes = $this->getApprovedFedex();
        
        //echo '<pre>'; print_r($this); echo '</pre>'; exit();
        
        include_once(get_stylesheet_directory() . '/fedex/fedexrest.php');
        
        $fedex = new fedexrest();
        
        $rates = $fedex->getRates(true, $this->shipment);
        
        return $rates;
            
    } else {
        
        $approved_codes = $this->getApprovedFedex();
        //echo '<pre>'; print_r($approved_codes); echo '</pre>'; exit();
        
        $this->fedex = new Fedex\Rate([
        	'prod'           => true,
        	'key'            => '6W02EnQC0n9nO5NH',
        	'password'       => 'tmZlwVIuLUHGNtasKOHQYkKKd',
        	'accountNumber' => '573800028',
        	'meterNumber'   => '251644967',
        	'dropOffType'  => 'REGULAR_PICKUP',
        	'shipment'       => $this->shipment,
        	/*'approvedCodes'  => [
        		'FEDEX_EXPRESS_SAVER',  // 1-3 business days
        		'FEDEX_GROUND',         // 1-5 business days
        		'GROUND_HOME_DELIVERY', // 1-5 business days
        		'FEDEX_2_DAY',          // 2 business days
        		'STANDARD_OVERNIGHT',   // overnight
                'PRIORITY_OVERNIGHT',
        	],*/
            'approvedCodes'  => [ $approved_codes ],
        	//'requestAdapter' => new RateRequest\StubFedex(),
        ]);
        
        $rates = $this->fedex->getRates();
//echo '<pre>'; print_r($rates); echo '</pre>'; exit();
        return $rates;
        
    }
        
    }
    
    public function getApprovedFedex() {
        
        $approved = array();
        
        $shipping_settings_tmp = unserialize(get_option('shipping_settings'));

        $shipping_settings = $shipping_settings_tmp['Fedex'];
        //echo '<pre>'; print_r($shipping_settings); echo '</pre>'; //exit();
        $all_options = $this->getAllShippingOptions();
        //echo '<pre>'; print_r($all_options); echo '</pre>'; exit();
        foreach ($all_options['Fedex'] as $key => $value) {
            
            if (in_array($value['id'], $shipping_settings)) {
                
                $approved[] = $value['id'];
                
            }
            
        }
        
        //echo '<pre>'; print_r($approved); echo '</pre>'; exit();
        return $approved;
                
    }
    
    
    public function getSmartPostRates() {
        
        $approved_codes = $this->getApprovedSmartPost();
        //echo '<pre>'; print_r($approved_codes); echo '</pre>'; exit();
        
        $this->smartpost = new SmartPost\Rate([
        	'prod'           => true,
        	'key'            => '6W02EnQC0n9nO5NH',
        	'password'       => 'tmZlwVIuLUHGNtasKOHQYkKKd',
        	'accountNumber' => '573800028',
        	'meterNumber'   => '251644967',
        	'dropOffType'  => 'REGULAR_PICKUP',
        	'shipment'       => $this->shipment,
        	/*'approvedCodes'  => [
        		'FEDEX_EXPRESS_SAVER',  // 1-3 business days
        		'FEDEX_GROUND',         // 1-5 business days
        		'GROUND_HOME_DELIVERY', // 1-5 business days
        		'FEDEX_2_DAY',          // 2 business days
        		'STANDARD_OVERNIGHT',   // overnight
                'PRIORITY_OVERNIGHT',
        	],*/
            'approvedCodes'  => [ $approved_codes ],
        	//'requestAdapter' => new RateRequest\StubFedex(),
        ]);
        
        $rates = $this->smartpost->getRates();

        return $rates;
        
    }
    
    public function getApprovedSmartPost() {
        
        $approved = array();
        
        /*
        $shipping_settings_tmp = unserialize(get_option('shipping_settings'));

        $shipping_settings = $shipping_settings_tmp['Fedex'];
        //echo '<pre>'; print_r($shipping_settings); echo '</pre>'; //exit();
        $all_options = $this->getAllShippingOptions();
        //echo '<pre>'; print_r($all_options); echo '</pre>'; exit();
        foreach ($all_options['Fedex'] as $key => $value) {
            
            if (in_array($value['id'], $shipping_settings)) {
                
                $approved[] = $value['id'];
                
            }
            
        }
        */
        
        $approved[] = 'SMART_POST';
        
        //echo '<pre>'; print_r($approved); echo '</pre>'; exit();
        return $approved;
                
    }
    
    
    
    public function getUPSrates() {
        
        $rates = array();
        
      //  Next Day Air [01], 2nd Day Air [02], Ground [03], Worldwide Express [07], Worldwide Expedited [08], Standard [11], 3 Day Select [12], Next Day Air Saver [13]  
      $approvedCodes = array(
        //'03', '12', '02', '13', '01'
        '01', '02', '03', '07', '08', '11', '12', '13'
      );
        
      foreach ($approvedCodes as $code) {
        
        $this->ups = new UPS\Rate([
            'prod'           => false,
            'accessKey'      => '1D87B9833431F195',
            'userId'         => 'bigzfabric',
            'password'       => 'YAVzar23$',
            'shipperNumber'  => 'XXXX', //  Y416Y6
            'shipment'       => $this->shipment,
            //'approvedCodes'  => [],
            'approvedCodes' => [$code]
            //'requestAdapter' => new RateRequest\StubUPS(),
        ]);
        
        $tmp_rates = $this->ups->getRates();
        //echo 'R<pre>'; print_r($rates); echo '</pre>'; //exit();
        
        $rates[] = $tmp_rates[0];
        
      } 
      
        return $rates;
        
    }
    
    public function getFedexHanling() {
        
        $res = array('INTERNATIONAL_PRIORITY' => 38,
                    'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 38,
                    'INTERNATIONAL_ECONOMY' => 38,
                    'STANDARD_OVERNIGHT' => 40,
                    'PRIORITY_OVERNIGHT' => 40,
                    'FEDEX_2_DAY' => 40,
                    'FEDEX_GROUND' => 32,
                    'GROUND_HOME_DELIVERY' => 32,
                    'INTERNATIONAL_GROUND' => 42,
                    'FEDEX_EXPRESS_SAVER' => 40);

        return $res;        
    }
    
    public function getAllShippingOptions($data = array()) {
        
        return array(
        'USPS' => array(
                'media_mail_parcel' => array(
                    'id' => 'media_mail_parcel',
                    'text' => 'USPS Media Mail Parcel',
                    'code' => 6
                ),
                /*array(
                    [id] => media_mail_parcel_parcel_locker
                    [text] => USPS Media Mail Parcel Parcel Locker
                    [code] => 6076
                )*/
                'library_mail_parcel' => array(
                    'id' => 'library_mail_parcel',
                    'text' => 'USPS Library Mail Parcel',
                    'code' => 7
                ),
                /*array(
                    [id] => library_mail_parcel_parcel_locker
                    [text] => USPS Library Mail Parcel Parcel Locker
                    [code] => 6075
                )
                array(
                    [id] => usps_ground_advantage<sup>T</sup>_cubic_hazmat
                    [text] => USPS USPS Ground Advantage<sup>T</sup> Cubic HAZMAT
                    [code] => 4096
                )
                array(
                    [id] => usps_ground_advantage<sup>T</sup>_cubic
                    [text] => USPS USPS Ground Advantage<sup>T</sup> Cubic
                    [code] => 1096
                )
                array(
                    [id] => usps_ground_advantage<sup>T</sup>_cubic_hold_for_pickup
                    [text] => USPS USPS Ground Advantage<sup>T</sup> Cubic Hold For Pickup
                    [code] => 2096
                )
                array(
                    [id] => usps_ground_advantage<sup>T</sup>_cubic_parcel_locker
                    [text] => USPS USPS Ground Advantage<sup>T</sup> Cubic Parcel Locker
                    [code] => 6096
                )
                array(
                    [id] => usps_ground_advantage<sup>T</sup>_hazmat
                    [text] => USPS USPS Ground Advantage<sup>T</sup> HAZMAT
                    [code] => 4058
                )*/
                'ground_advantage' => array(
                    'id' => 'ground_advantage',
                    'text' => 'USPS Ground Advantage<sup>T</sup>',
                    'code' => 1058
                ),
                /*array(
                    [id] => usps_ground_advantage<sup>T</sup>_hold_for_pickup
                    [text] => USPS USPS Ground Advantage<sup>T</sup> Hold For Pickup
                    [code] => 2058
                )
                array(
                    [id] => usps_ground_advantage<sup>T</sup>_parcel_locker
                    [text] => USPS USPS Ground Advantage<sup>T</sup> Parcel Locker
                    [code] => 6058
                )*/
                'priority_mail_flat_rate' => array(
                    'id' => 'priority_mail_flat_rate',
                    'text' => 'USPS Priority Mail Flat Rate<sup>R</sup> Envelope',
                    'code' => 16
                ),
                /*array(
                    [id] => priority_mail_flat_rate<sup>R</sup>_envelope_hold_for_pickup
                    [text] => USPS Priority Mail Flat Rate<sup>R</sup> Envelope Hold For Pickup
                    [code] => 37
                )
                array(
                    [id] => priority_mail<sup>R</sup>_legal_flat_rate_envelope
                    [text] => USPS Priority Mail<sup>R</sup> Legal Flat Rate Envelope
                    [code] => 44
                )
                array(
                    [id] => priority_mail<sup>R</sup>_legal_flat_rate_envelope_hold_for_pickup
                    [text] => USPS Priority Mail<sup>R</sup> Legal Flat Rate Envelope Hold For Pickup
                    [code] => 45
                )
                array(
                    [id] => priority_mail<sup>R</sup>_window_flat_rate_envelope
                    [text] => USPS Priority Mail<sup>R</sup> Window Flat Rate Envelope
                    [code] => 40
                )
                array(
                    [id] => priority_mail<sup>R</sup>_window_flat_rate_envelope_hold_for_pickup
                    [text] => USPS Priority Mail<sup>R</sup> Window Flat Rate Envelope Hold For Pickup
                    [code] => 41
                )*/ //  <sup>®</sup>
                'priority_mail' => array(
                //'1' => array(
                    //'id' => '1',
                    'id' => 'priority_mail',
                    //'id' => 'priority_mail',
                    'text' => 'USPS Priority Mail<sup>&reg;</sup>',
                    'code' => 1
                ),
                /*array(
                    [id] => priority_mail<sup>R</sup>_hold_for_pickup
                    [text] => USPS Priority Mail<sup>R</sup> Hold For Pickup
                    [code] => 33
                )
                array(
                    [id] => priority_mail<sup>R</sup>_hazmat
                    [text] => USPS Priority Mail<sup>R</sup> HAZMAT
                    [code] => 4010
                )
                array(
                    [id] => priority_mail<sup>R</sup>_parcel_locker
                    [text] => USPS Priority Mail<sup>R</sup> Parcel Locker
                    [code] => 6010
                )
                array(
                    [id] => priority_mail<sup>R</sup>_padded_flat_rate_envelope
                    [text] => USPS Priority Mail<sup>R</sup> Padded Flat Rate Envelope
                    [code] => 29
                )
                array(
                    [id] => priority_mail<sup>R</sup>_padded_flat_rate_envelope_hold_for_pickup
                    [text] => USPS Priority Mail<sup>R</sup> Padded Flat Rate Envelope Hold For Pickup
                    [code] => 46
                )
                array(
                    [id] => priority_mail<sup>R</sup>_medium_flat_rate_box
                    [text] => USPS Priority Mail<sup>R</sup> Medium Flat Rate Box
                    [code] => 17
                )
                array(
                    [id] => priority_mail<sup>R</sup>_medium_flat_rate_box_hold_for_pickup
                    [text] => USPS Priority Mail<sup>R</sup> Medium Flat Rate Box Hold For Pickup
                    [code] => 35
                )
                array(
                    [id] => priority_mail<sup>R</sup>_medium_flat_rate_box_parcel_locker
                    [text] => USPS Priority Mail<sup>R</sup> Medium Flat Rate Box Parcel Locker
                    [code] => 6013
                )
                array(
                    [id] => priority_mail<sup>R</sup>_large_flat_rate_box
                    [text] => USPS Priority Mail<sup>R</sup> Large Flat Rate Box
                    [code] => 22
                )
                array(
                    [id] => priority_mail<sup>R</sup>_large_flat_rate_box_hold_for_pickup
                    [text] => USPS Priority Mail<sup>R</sup> Large Flat Rate Box Hold For Pickup
                    [code] => 34
                )
                array(
                    [id] => priority_mail<sup>R</sup>_large_flat_rate_box_parcel_locker
                    [text] => USPS Priority Mail<sup>R</sup> Large Flat Rate Box Parcel Locker
                    [code] => 6012
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_flat_rate_envelope
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Flat Rate Envelope
                    [code] => 13
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_flat_rate_envelope_hold_for_pickup
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Flat Rate Envelope Hold For Pickup
                    [code] => 27
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_legal_flat_rate_envelope
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Legal Flat Rate Envelope
                    [code] => 30
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_legal_flat_rate_envelope_hold_for_pickup
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Legal Flat Rate Envelope Hold For Pickup
                    [code] => 31
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_padded_flat_rate_envelope
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Padded Flat Rate Envelope
                    [code] => 62
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_padded_flat_rate_envelope_hold_for_pickup
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Padded Flat Rate Envelope Hold For Pickup
                    [code] => 63
                )*/
                'priority_mail_express' => array(
                //'1' => array(
                    //'id' => '3',
                    'id' => 'priority_mail_express',
                    //'id' => 'priority_mail_express_2-day',
                    'text' => 'USPS Priority Mail Express <sup>&reg;</sup>',
                    'code' => 3
                ),
                'priority_mail_international' => array(
                //'1' => array(
                    //'id' => '4',
                    'id' => 'priority_mail_international',
                    //'id' => 'priority_mail_express_2-day',
                    'text' => 'USPS Priority Mail International<sup>&reg;</sup>',
                    'code' => 2
                ),
                'priority_mail_express_international' => array(
                //'1' => array(
                    //'id' => '4',
                    'id' => 'priority_mail_express_international',
                    //'id' => 'priority_mail_express_2-day',
                    'text' => 'USPS Priority Mail Express International<sup>&reg;</sup>',
                    'code' => 1
                ),
                /*array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_hold_for_pickup
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Hold For Pickup
                    [code] => 2
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_hazmat
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> HAZMAT
                    [code] => 4001
                )
                array(
                    [id] => priority_mail_express_2-day<sup>R</sup>_parcel_locker
                    [text] => USPS Priority Mail Express 2-Day<sup>R</sup> Parcel Locker
                    [code] => 6001
                )*/
            ),
        'Fedex' => array(
                'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => array(
                    'id' => 'EUROPE_FIRST_INTERNATIONAL_PRIORITY',
                    'text' => 'Europe First International Priority',
                    'code' => 1,
                ),
                'FEDEX_1_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_1_DAY_FREIGHT',
                    'text' => 'Fedex 1 Day Freight',
                    'code' => 2,
                ),
                'FEDEX_2_DAY' => array(
                    'id' => 'FEDEX_2_DAY',
                    'text' => 'Fedex 2 Day',
                    'code' => 3,
                ),
                'FEDEX_2_DAY_AM' => array(
                    'id' => 'FEDEX_2_DAY_AM',
                    'text' => 'Fedex 2 Day AM',
                    'code' => 4,
                ),
                'FEDEX_2_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_2_DAY_FREIGHT',
                    'text' => 'Fedex 2 Day Freight',
                    'code' => 5,
                ),
                'FEDEX_3_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_3_DAY_FREIGHT',
                    'text' => 'Fedex 3 Day Freight',
                    'code' => 6,
                ),
                'FEDEX_EXPRESS_SAVER' => array(
                    'id' => 'FEDEX_EXPRESS_SAVER',
                    'text' => 'Fedex Express Saver',
                    'code' => 7,
                ),
                'FEDEX_FIRST_FREIGHT' => array(
                    'id' => 'FEDEX_FIRST_FREIGHT',
                    'text' => 'Fedex First Freight',
                    'code' => 8,
                ),
                'FEDEX_FREIGHT_ECONOMY' => array(
                    'id' => 'FEDEX_FREIGHT_ECONOMY',
                    'text' => 'Fedex Freight Economy',
                    'code' => 9,
                ),
                'FEDEX_FREIGHT_PRIORITY' => array(
                    'id' => 'FEDEX_FREIGHT_PRIORITY',
                    'text' => 'Fedex Freight Priority',
                    'code' => 10,
                ),
                'FEDEX_GROUND' => array(
                    'id' => 'FEDEX_GROUND',
                    'text' => 'Fedex Int Ground',
                    'code' => 11,
                ),
                'FIRST_OVERNIGHT' => array(
                    'id' => 'FIRST_OVERNIGHT',
                    'text' => 'First Overnight',
                    'code' => 12,
                ),
                'GROUND_HOME_DELIVERY' => array(
                    'id' => 'GROUND_HOME_DELIVERY',
                    'text' => 'Ground Home Delivery',
                    'code' => 13,
                ),
                'INTERNATIONAL_ECONOMY' => array(
                    'id' => 'INTERNATIONAL_ECONOMY',
                    'text' => 'International Economy',
                    'code' => 14,
                ),
                'INTERNATIONAL_ECONOMY_FREIGHT' => array(
                    'id' => 'INTERNATIONAL_ECONOMY_FREIGHT',
                    'text' => 'International Economy Freight',
                    'code' => 15,
                ),
                'INTERNATIONAL_FIRST' => array(
                    'id' => 'INTERNATIONAL_FIRST',
                    'text' => 'International First',
                    'code' => 16,
                ),
                'INTERNATIONAL_PRIORITY' => array(
                    'id' => 'INTERNATIONAL_PRIORITY',
                    'text' => 'International Priority',
                    'code' => 17,
                ),
                'INTERNATIONAL_PRIORITY_FREIGHT' => array(
                    'id' => 'INTERNATIONAL_PRIORITY_FREIGHT',
                    'text' => 'International Priority Freight',
                    'code' => 18,
                ),
                'PRIORITY_OVERNIGHT' => array(
                    'id' => 'PRIORITY_OVERNIGHT',
                    'text' => 'Priority Overnight',
                    'code' => 19,
                ),
                'SMART_POST' => array(
                    'id' => 'SMART_POST',
                    'text' => 'Smart Post',
                    'code' => 20,
                ),
                'STANDARD_OVERNIGHT' => array(
                    'id' => 'STANDARD_OVERNIGHT',
                    'text' => 'Standard Overnight',
                    'code' => 21,
                )
            ),
            
            'FedexRest' => array(
                'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => array(
                    'id' => 'EUROPE_FIRST_INTERNATIONAL_PRIORITY',
                    'text' => 'Europe First International Priority',
                    'code' => 1,
                ),
                'FEDEX_1_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_1_DAY_FREIGHT',
                    'text' => 'Fedex 1 Day Freight',
                    'code' => 2,
                ),
                'FEDEX_2_DAY' => array(
                    'id' => 'FEDEX_2_DAY',
                    'text' => 'Fedex 2 Day',
                    'code' => 3,
                ),
                'FEDEX_2_DAY_AM' => array(
                    'id' => 'FEDEX_2_DAY_AM',
                    'text' => 'Fedex 2 Day AM',
                    'code' => 4,
                ),
                'FEDEX_2_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_2_DAY_FREIGHT',
                    'text' => 'Fedex 2 Day Freight',
                    'code' => 5,
                ),
                'FEDEX_3_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_3_DAY_FREIGHT',
                    'text' => 'Fedex 3 Day Freight',
                    'code' => 6,
                ),
                'FEDEX_EXPRESS_SAVER' => array(
                    'id' => 'FEDEX_EXPRESS_SAVER',
                    'text' => 'Fedex Express Saver',
                    'code' => 7,
                ),
                'FEDEX_FIRST_FREIGHT' => array(
                    'id' => 'FEDEX_FIRST_FREIGHT',
                    'text' => 'Fedex First Freight',
                    'code' => 8,
                ),
                'FEDEX_FREIGHT_ECONOMY' => array(
                    'id' => 'FEDEX_FREIGHT_ECONOMY',
                    'text' => 'Fedex Freight Economy',
                    'code' => 9,
                ),
                'FEDEX_FREIGHT_PRIORITY' => array(
                    'id' => 'FEDEX_FREIGHT_PRIORITY',
                    'text' => 'Fedex Freight Priority',
                    'code' => 10,
                ),
                'FEDEX_GROUND' => array(
                    'id' => 'FEDEX_GROUND',
                    'text' => 'Fedex Int Ground',
                    'code' => 11,
                ),
                'FIRST_OVERNIGHT' => array(
                    'id' => 'FIRST_OVERNIGHT',
                    'text' => 'First Overnight',
                    'code' => 12,
                ),
                'GROUND_HOME_DELIVERY' => array(
                    'id' => 'GROUND_HOME_DELIVERY',
                    'text' => 'Ground Home Delivery',
                    'code' => 13,
                ),
                'INTERNATIONAL_ECONOMY' => array(
                    'id' => 'INTERNATIONAL_ECONOMY',
                    'text' => 'International Economy',
                    'code' => 14,
                ),
                'INTERNATIONAL_ECONOMY_FREIGHT' => array(
                    'id' => 'INTERNATIONAL_ECONOMY_FREIGHT',
                    'text' => 'International Economy Freight',
                    'code' => 15,
                ),
                'INTERNATIONAL_FIRST' => array(
                    'id' => 'INTERNATIONAL_FIRST',
                    'text' => 'International First',
                    'code' => 16,
                ),
                'INTERNATIONAL_PRIORITY' => array(
                    'id' => 'INTERNATIONAL_PRIORITY',
                    'text' => 'International Priority',
                    'code' => 17,
                ),
                'INTERNATIONAL_PRIORITY_FREIGHT' => array(
                    'id' => 'INTERNATIONAL_PRIORITY_FREIGHT',
                    'text' => 'International Priority Freight',
                    'code' => 18,
                ),
                'PRIORITY_OVERNIGHT' => array(
                    'id' => 'PRIORITY_OVERNIGHT',
                    'text' => 'Priority Overnight',
                    'code' => 19,
                ),
                'SMART_POST' => array(
                    'id' => 'SMART_POST',
                    'text' => 'Smart Post',
                    'code' => 20,
                ),
                'STANDARD_OVERNIGHT' => array(
                    'id' => 'STANDARD_OVERNIGHT',
                    'text' => 'Standard Overnight',
                    'code' => 21,
                )
            ),
            
            'FedexRestTest' => array(
                'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => array(
                    'id' => 'EUROPE_FIRST_INTERNATIONAL_PRIORITY',
                    'text' => 'Europe First International Priority',
                    'code' => 1,
                ),
                'FEDEX_1_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_1_DAY_FREIGHT',
                    'text' => 'Fedex 1 Day Freight',
                    'code' => 2,
                ),
                'FEDEX_2_DAY' => array(
                    'id' => 'FEDEX_2_DAY',
                    'text' => 'Fedex 2 Day',
                    'code' => 3,
                ),
                'FEDEX_2_DAY_AM' => array(
                    'id' => 'FEDEX_2_DAY_AM',
                    'text' => 'Fedex 2 Day AM',
                    'code' => 4,
                ),
                'FEDEX_2_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_2_DAY_FREIGHT',
                    'text' => 'Fedex 2 Day Freight',
                    'code' => 5,
                ),
                'FEDEX_3_DAY_FREIGHT' => array(
                    'id' => 'FEDEX_3_DAY_FREIGHT',
                    'text' => 'Fedex 3 Day Freight',
                    'code' => 6,
                ),
                'FEDEX_EXPRESS_SAVER' => array(
                    'id' => 'FEDEX_EXPRESS_SAVER',
                    'text' => 'Fedex Express Saver',
                    'code' => 7,
                ),
                'FEDEX_FIRST_FREIGHT' => array(
                    'id' => 'FEDEX_FIRST_FREIGHT',
                    'text' => 'Fedex First Freight',
                    'code' => 8,
                ),
                'FEDEX_FREIGHT_ECONOMY' => array(
                    'id' => 'FEDEX_FREIGHT_ECONOMY',
                    'text' => 'Fedex Freight Economy',
                    'code' => 9,
                ),
                'FEDEX_FREIGHT_PRIORITY' => array(
                    'id' => 'FEDEX_FREIGHT_PRIORITY',
                    'text' => 'Fedex Freight Priority',
                    'code' => 10,
                ),
                'FEDEX_GROUND' => array(
                    'id' => 'FEDEX_GROUND',
                    'text' => 'Fedex Int Ground',
                    'code' => 11,
                ),
                'FIRST_OVERNIGHT' => array(
                    'id' => 'FIRST_OVERNIGHT',
                    'text' => 'First Overnight',
                    'code' => 12,
                ),
                'GROUND_HOME_DELIVERY' => array(
                    'id' => 'GROUND_HOME_DELIVERY',
                    'text' => 'Ground Home Delivery',
                    'code' => 13,
                ),
                'INTERNATIONAL_ECONOMY' => array(
                    'id' => 'INTERNATIONAL_ECONOMY',
                    'text' => 'International Economy',
                    'code' => 14,
                ),
                'INTERNATIONAL_ECONOMY_FREIGHT' => array(
                    'id' => 'INTERNATIONAL_ECONOMY_FREIGHT',
                    'text' => 'International Economy Freight',
                    'code' => 15,
                ),
                'INTERNATIONAL_FIRST' => array(
                    'id' => 'INTERNATIONAL_FIRST',
                    'text' => 'International First',
                    'code' => 16,
                ),
                'INTERNATIONAL_PRIORITY' => array(
                    'id' => 'INTERNATIONAL_PRIORITY',
                    'text' => 'International Priority',
                    'code' => 17,
                ),
                'INTERNATIONAL_PRIORITY_FREIGHT' => array(
                    'id' => 'INTERNATIONAL_PRIORITY_FREIGHT',
                    'text' => 'International Priority Freight',
                    'code' => 18,
                ),
                'PRIORITY_OVERNIGHT' => array(
                    'id' => 'PRIORITY_OVERNIGHT',
                    'text' => 'Priority Overnight',
                    'code' => 19,
                ),
                'SMART_POST' => array(
                    'id' => 'SMART_POST',
                    'text' => 'Smart Post',
                    'code' => 20,
                ),
                'STANDARD_OVERNIGHT' => array(
                    'id' => 'STANDARD_OVERNIGHT',
                    'text' => 'Standard Overnight',
                    'code' => 21,
                )
            )
        );
        
        //  get shipping rates
        $this->shipment = new Shipment;
        $this->shipment
            ->setFromIsResidential(false)
            ->setFromStateProvinceCode(isset($data['rate']['origin']['province']) ? $data['rate']['origin']['province'] : 'CA')
            ->setFromPostalCode(isset($data['rate']['origin']['postal_code']) ? $data['rate']['origin']['postal_code'] : '90401')
            ->setFromCountryCode(isset($data['rate']['origin']['country']) ? $data['rate']['origin']['country'] : 'US')
            ->setToIsResidential(true)
            ->setToPostalCode(isset($data['rate']['destination']['postal_code']) ? $data['rate']['destination']['postal_code'] : '78703')
            ->setToCountryCode(isset($data['rate']['destination']['country']) ? $data['rate']['destination']['country'] : 'US');
            
        //  get total weight
        //$total_weight = 0;
        //foreach ($data['rate']['items'] as $key => $value) {
        //    $total_weight += $value['grams'] * $value['quantity'];
        //}
        
        $this->package = new Package;
        $this->package
            ->setLength(12)
            ->setWidth(4)
            ->setHeight(3)
            ->setWeight(1);
        
        $this->shipment->addPackage($this->package);
        
        //$on_min_date = date('Y-m-d H:i:s O', strtotime('+1 day'));
        //$on_max_date = date('Y-m-d H:i:s O', strtotime('+2 days'));
        $on_min_date = '';
        $on_max_date = '';
        
        //  get UPS rates
        /*   
        $rates = $this->getUPSrates();
        
        echo '!!!<pre>'; print_r($rates); echo '</pre>'; exit();
        
        foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $output['UPS'][] = array(
                'id' => $code,
                'text' => $quote->getName()
            );
            
        }
        */
        //  get USPS rates
        
        $rates = $this->getUSPSallRates(); 
        
        foreach ($rates as $key => $quote) { 
            
            if ($quote->getName() != '') {
            
                $code = str_replace(' ', '_', strtolower($quote->getName()));
                
                $output['USPS'][] = array(
                    'id' => $code,
                    'text' => 'USPS '.$quote->getName(),
                    'code' => $quote->getCode(),
                );
                
            }
        }

        //  get fedex rates
        $rates = $this->getFedexRates();
        //echo '<pre>'; print_r($rates); echo '</pre>'; exit();
        foreach ($rates as $key => $quote) { 
            
            $code = str_replace(' ', '_', strtolower($quote->getName()));
            
            $output['Fedex'][] = array(
                'id' => $code,
                'text' => $quote->getName()
            );
            
        }
        
        return $output;
        
    }
    
    function get_handling_fee($setting) { 
    
        //  get full weight
        $total_weight = 0;
        foreach ($this->shipment->getPackages() as $p) {
            $total_weight += $p->getWeight() * 2.20477/1000;
        }
        
        if (isset($_GET['test']) && $_GET['test'] == 'ship') {
                echo 'total weight: '.$total_weight.'<br />';
        }
        
        $table_cost = preg_split("/[:,]/" , $setting);

        if (sizeof($table_cost) == 1) {
            return $table_cost[0];
        }
//echo '<pre>'; print_r($table_cost); echo '</pre>'; //exit();
        $size = sizeof($table_cost);
        for ($i=0, $n=$size; $i<$n; $i+=2) {
          if ($total_weight <= $table_cost[$i]) {
            //echo '<pre>'; print_r($table_cost[$i+1]); echo '</pre>'; exit();
            return $table_cost[$i+1];
            break;
          }
        }

        return $table_cost[count($table_cost)-1];
    }
    
}


?>
