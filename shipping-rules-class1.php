<?php

include_once('/home/johnming/vendor/autoload.php');

use Shopify\Context;
use Shopify\Clients\Rest;
use Shopify\Clients\Http;
use Shopify\Auth\FileSessionStorage;

class shippingRules1 {
    //protected int $x;
    
    protected $api;

    public function __construct() {
        //$this->x = $x;
        
        // Initialize the client
        Context::initialize(
                $_ENV['SHOPIFY_API_KEY'],
                $_ENV['SHOPIFY_API_SECRET'],
                'read_products',  //$_ENV['SHOPIFY_APP_SCOPES'],
                $_ENV['SHOPIFY_APP_HOST_NAME'],
                new FileSessionStorage('/tmp/php_sessions'),
                '2023-04',
                true,  //bool $isEmbeddedApp = true,
                false,   //bool $isPrivateApp = false,
        );

        $this->api = new Rest($_ENV['SHOPIFY_APP_HOST_NAME'], $_ENV['SHOPIFY_API_KEY']);
        //$response = $client->get('products');
        //echo '!<pre>'; print_r($response->getDecodedBody()); echo '</pre>'; exit();
    }
    
    public function getCollections() {
        
        $collections = array();
        
        $result = $this->api->call('GET', 'admin/collects.json?limit=250');
        
        foreach ($result->collects as $key => $value) {
            if (!in_array($value->collection_id, $collections)) {
                $collections[] = $value->collection_id;
            }
        }
        
        $collections_array = array();
        
        foreach ($collections as $id) {
            $result = $this->api->call('GET', 'admin/collections/'.$id.'.json');
            if (isset($result->collection->title)) {
                $collections_array[$id] = $result->collection->title;
            }
        }
        
        return $collections_array;
        
    }
    
    public function getCollection($id) {
        
        $result = $this->api->call('GET', 'admin/collections/'.$id.'.json');
        
        return $result;
        
    }
    
    public function getCollectionMeta($id) {
        
        $meta_array = array();
        
        //sleep(1);
        $result = $this->api->get('collections/'.$id.'/metafields');
        
        $decoded = $result->getDecodedBody();
        if (!empty($decoded['metafields'])) {
            
            foreach ($decoded['metafields'] as $key => $value) {
                $meta_array[$value['key']] = $value['value'];
            }
            //echo '<pre>'; print_r($meta_array); echo '</pre>'; exit();
        }
        
        return $meta_array;
        
    }
    
    /*
    public function getSmartCollections() {
        
        $collections_array = array();
        
        $result = $this->api->get('smart_collections');
        sleep(1);
        
        $decoded = $result->getDecodedBody();
        //echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
        if (isset($decoded['smart_collections']) && !empty($decoded['smart_collections'])) {
            foreach ($decoded['smart_collections'] as $key => $value) {
                $collections_array[$value['id']] = $value['title'];
            }
        }
        //echo '<pre>'; print_r($collections_array); echo '</pre>'; exit();
        return $collections_array;
        
    }
    */
    public function getSmartCollections() { 
        
        //curl -X GET "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services.json"
        
        $page_info = '1';
        $collections_array = array();
        
        while ($page_info != '') {
        
            if ($page_info == '1') {
                $query = ['limit' => 250];
            } else {
                $query = $pageInfo->getNextPageQuery();
            }
            
            sleep(1);
            $result = $this->api->get(path: 'smart_collections', query: $query);
            
            $decoded = $result->getDecodedBody();

            if (isset($decoded['smart_collections']) && !empty($decoded['smart_collections'])) {
                foreach ($decoded['smart_collections'] as $key => $value) {
                    $collections_array[$value['id']] = $value['title'];
                }
            }
            
            $pageInfo = $result->getPageInfo();
            //echo count($collections_array).'<pre>'; print_r($pageInfo); echo '</pre>'; //exit();
            
            if (isset($pageInfo) && $pageInfo->hasNextPage()) {
                
                $info_array = $pageInfo->getNextPageQuery();
                
                if (isset($info_array['page_info']) && $info_array['page_info'] != '') {
                    $page_info = $info_array['page_info'];
                } else {
                    $page_info = '';
                }
            } else {
                $page_info = '';
            }
        }
        
        //echo count($collections_array).'<pre>'; print_r($collections_array); echo '</pre>'; exit();
        
        
        return $collections_array;
        
    }
    
    public function getSmartCollectionsCount() {

        $result = $this->api->get('smart_collections/count');
        sleep(1);
        
        $decoded = $result->getDecodedBody();
        echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
    }
    
    public function getCategoriesTree() {
        
        global $wpdb;
        
        $collections_array = array();
        
        $sql = "SELECT * FROM wp_categories
                WHERE is_main_category = 1";
        $res = $wpdb->get_results($sql);
        
        foreach ($res as $item) {
            $collections_array[$item->shopify_id] = array('name' => $item->name);
            
            //  get subcategories
            $sql = "SELECT * FROM wp_categories
                    WHERE parent_tag = '".$item->name."'";
            $res = $wpdb->get_results($sql);
            
            foreach ($res as $cat) {
                $collections_array[$item->shopify_id]['child'][$cat->shopify_id] = $cat->name;
            }
        }
        
        return $collections_array;
        
    }
    
    public function getCollectionListings() {
        
        //curl -X GET "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services.json"
        
        $collections_array = array();
        
        $result = $this->api->call('GET', 'admin/collection_listings.json?limit=250');
        
        return $result;
        
    }
    
    public function getCustomCollections() { 
        
        //curl -X GET "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services.json"
        
        $page_info = '1';
        $collections_array = array();
        
        while ($page_info != '') {
        
            if ($page_info == '1') {
                $query = ['limit' => 250];
            } else {
                $query = $pageInfo->getNextPageQuery();
            }
            
            sleep(1);
            $result = $this->api->get(path: 'custom_collections', query: $query);
            
            $decoded = $result->getDecodedBody();

            if (isset($decoded['custom_collections']) && !empty($decoded['custom_collections'])) {
                foreach ($decoded['custom_collections'] as $key => $value) {
                    $collections_array[$value['id']] = $value['title'];
                }
            }
            
            $pageInfo = $result->getPageInfo();
            //echo count($collections_array).'<pre>'; print_r($pageInfo); echo '</pre>'; //exit();
            
            if (isset($pageInfo) && $pageInfo->hasNextPage()) {
                
                $info_array = $pageInfo->getNextPageQuery();
                
                if (isset($info_array['page_info']) && $info_array['page_info'] != '') {
                    $page_info = $info_array['page_info'];
                } else {
                    $page_info = '';
                }
            } else {
                $page_info = '';
            }
        }
        
        //echo count($collections_array).'<pre>'; print_r($collections_array); echo '</pre>'; exit();
        
        
        return $collections_array;
        
    }
    
    public function getCustomCollectionsCount() {

        $result = $this->api->get('custom_collections/count');
        sleep(1);
        
        $decoded = $result->getDecodedBody();
        echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
    }
    
    public function getServices() {
        
        //curl -X GET "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services.json"
        
        $result = $this->api->get('carrier_services');
        
        $carriers = array();
        
        $decoded = $result->getDecodedBody();
echo '<pre>'; print_r($decoded); echo '</pre>'; //exit();
            if (isset($decoded['carrier_services']) && !empty($decoded['carrier_services'])) {
                foreach ($decoded['carrier_services'] as $key => $value) {
                    $carriers[$value['id']] = $value['name'];
                }
            }
            
        return $carriers;
        
    }
    
    public function updateService($service_id) {
        
        //curl -X DELETE "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services/1036894960.json"
        
        $data = array(
            'carrier_service' => array(
                'id' => $service_id,
                "name"        => "Shipping",
                //"callback_url"    => "http://johnmingo.com/shopify/shipping-rules/index.php",
                "callback_url"    => "https://cloudappsity.com/bigz/checkout-rules/",
                "service_discovery"       => true
            ),
        );
        
        $result = $this->api->put(path: 'carrier_services/'.$service_id.'.json', body: json_encode($data));  
        
        echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; //exit();
        
        return true;
        
    }
    
    public function removeService($service_id) {
        
        //curl -X DELETE "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services/1036894960.json"
        
        $result = $this->api->call('DELETE', 'admin/carrier_services/'.$service_id.'.json');
        
        return true;
        
    }
    
    public function createService() {
        
        //curl -d '{"carrier_service":{"name":"Shipping Rate Provider","callback_url":"http://shipping.example.com","service_discovery":true}}' \
        //-X POST "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services.json" \
        //echo 'create'; exit();
        $result = $this->api->call('POST', 'admin/carrier_services.json', [
            'carrier_service' => [
                "name"        => "Test Shipping",
                //"callback_url"    => "http://johnmingo.com/shopify/shipping-rules/index.php",
                "callback_url"    => "https://cloudappsity.com/bigz/fedex-test/",
                "service_discovery"       => true
            ],
        ]);
        
        return true;
        
    }
    
    public function checkService() {
        
        //$this->removeService('76732629306'); echo 'removed'; exit();
        
        $services = $this->getServices();

        $to_remove = false;
        $to_create = true;
        $service_id = false;
        
        foreach ($services as $key => $value) {
            
            if (isset($value->name) && $value->name == 'BigZ Advanced Shipping') {
                $to_create = false;
                $service_id = $value->id;
                if (isset($value->callback_url) && $value->callback_url == 'http://shopify.johnmingo.com/checkout-rules/') {
                    //  do nothing because service exist and setup
                    BREAK;
                } else {
                    $to_remove = true;
                    $to_create = true;
                    BREAK;
                }
            }
            
        }
        
        if ($to_remove) { 
            $this->removeService($service_id);
        }
        
        if ($to_create) { 
            $this->createService();
        }
    }
    
    public function getCollectionProducts($id) { 
        
        $page_info = '1';
        $products_array = array();
        
        while ($page_info != '') {
        
            if ($page_info == '1') {
                $query = ['limit' => 250];
            } else {
                $query = $pageInfo->getNextPageQuery();
            }
        
            $result = $this->api->get(path: 'collections/'.$id.'/products', query: $query);
            sleep(1);
            $decoded = $result->getDecodedBody();

            if (isset($decoded['products']) && !empty($decoded['products'])) {
                foreach ($decoded['products'] as $key => $value) {
                    //echo '<pre>'; print_r($decoded); echo '</pre>'; exit();
                    $products_array[$value['id']] = $value['title'];
                }
            }
            
            $pageInfo = $result->getPageInfo();
            //echo count($collections_array).'<pre>'; print_r($pageInfo); echo '</pre>'; //exit();
            
            if (isset($pageInfo) && $pageInfo->hasNextPage()) {
                
                $info_array = $pageInfo->getNextPageQuery();
                
                if (isset($info_array['page_info']) && $info_array['page_info'] != '') {
                    $page_info = $info_array['page_info'];
                } else {
                    $page_info = '';
                }
            } else {
                $page_info = '';
            }
        }
        
        //echo count($products_array).'<pre>'; print_r($products_array); echo '</pre>'; exit();
        
        return $products_array;
        
    }
    
    public function getCollectionProductsCount($id) {

        $result = $this->api->get('collections/'.$id.'/products/count');
        sleep(1);
        
        $decoded = $result->getDecodedBody();
        echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
    }
    
    public function getProducts() { 
        
        //curl -X GET "https://your-development-store.myshopify.com/admin/api/2023-04/carrier_services.json"
        
        $page_info = '1';
        $collections_array = array();
        
        $result = $this->api->get('products');
        //echo '1<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
        $decoded = $result->getDecodedBody();
        //echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
        foreach ($decoded['products'] as $key => $value) {
            $collections_array[$value['id']] = $value['title'];
        }

        $pageInfo = $result->getPageInfo();
        
        //echo '<pre>'; print_r($pageInfo->getNextPageQuery()); echo '</pre>'; exit();
        
        //echo '<pre>'; print_r($pageInfo); echo '</pre>'; exit();
        $result1 = $this->api->get(path: 'products', query: $pageInfo->getNextPageQuery());
        //echo '2<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        $decoded = $result1->getDecodedBody();
        
        foreach ($decoded['products'] as $key => $value) {
            $collections_array[$value['id']] = $value['title'];
        }
        
        echo count($collections_array).'<pre>'; print_r($collections_array); echo '</pre>'; exit();
        
        
        return $collections_array;
        
    }
    
    public function getProductsCount() {

        $result = $this->api->get('products/count');
        sleep(1);
        
        $decoded = $result->getDecodedBody();
        echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
    }
    
    public function getCustomerByEmail($email) {
        
        $query = ['email' => $email];
        
        $result = $this->api->get(path: 'customers/search.json', query: $query);  

        //echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
        $res = $result->getDecodedBody();
        
        if (isset($res['customers'][0]) && !empty($res['customers'][0])) {
            return $res['customers'][0];
        } else {
            return false;
        }
        
    }
    
    public function addCustomer($data) {
        
        //  insert new customer
        //  -X POST "https://your-development-store.myshopify.com/admin/api/2023-07/customers.json" \
        
        $result = $this->api->post(path: 'customers', body: json_encode($data));  
        
        //echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
        return $result->getDecodedBody();
    }
    
    
    public function getOrderByID($id) {
        
        //$query = ['email' => $email];
        
        $result = $this->api->get(path: 'orders/'.$id.'.json');  

        return $result->getDecodedBody();
        
    }
    
    public function getOrders($data) { //echo '<pre>'; print_r($data); echo '</pre>'; //exit();
        
        $result = $this->api->get(path: 'orders.json', query: $data);  
//echo '<pre>'; print_r($result); echo '</pre>'; exit();
        return $result->getDecodedBody();
        
    }
    
    public function getAllOrders($data) { //echo '<pre>'; print_r($data); echo '</pre>'; //exit();
        
        $orders = array();
        
        $page_info = '1';
        
        while ($page_info != '') {
        
            if ($page_info == '1') {
               // $query = ['limit' => 50];
               $data['limit'] = 10;
            } else {
                $data = $pageInfo->getNextPageQuery();
                //$data1 = array_merge($data, $query);
                //echo '<pre>'; print_r($data); echo '</pre>'; //exit();
            }
            
            //echo count($orders).'<pre>'; print_r($data); echo '</pre>'; //exit();
            
            sleep(1);
        
            $result = $this->api->get(path: 'orders.json', query: $data);  
    //echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; //exit();
            
            $tmp_array = array();
            
            $decoded = $result->getDecodedBody();
            if (isset($decoded['orders'])) foreach ($decoded['orders'] as $key => $value) {
                
                $tmp_array['line_items'] = $value['line_items'];
                $tmp_array['name'] = $value['name'];
                $tmp_array['id'] = $value['id'];
                $tmp_array['processed_at'] = $value['processed_at'];
                
                $orders[] = $value;
            }
            
            $pageInfo = $result->getPageInfo();
            //echo count($orders).'<pre>'; print_r($pageInfo); echo '</pre>'; //exit();
            
            if (isset($pageInfo) && $pageInfo->hasNextPage()) {
                
                $info_array = $pageInfo->getNextPageQuery();
                
                if (isset($info_array['page_info']) && $info_array['page_info'] != '') {
                    $page_info = $info_array['page_info'];
                } else {
                    $page_info = '';
                }
            } else {
                $page_info = '';
            }
        }

        return array('orders' => $orders);
        
    }
    
    public function getProductByID($id) {
        
        //  insert new customer
        //  -X POST "https://your-development-store.myshopify.com/admin/api/2023-07/customers.json" \
        
        sleep(1);
        
        $result = $this->api->get(path: 'products/'.$id.'.json');  
        
        //echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
        return $result->getDecodedBody();
    }
    
    public function addOrder($data) {
        
        //  insert new customer
        //  -X POST "https://your-development-store.myshopify.com/admin/api/2023-07/customers.json" \
        
        $result = $this->api->post(path: 'orders', body: json_encode($data));  
        
        //echo '<pre>'; print_r($result->getDecodedBody()); echo '</pre>'; exit();
        
        return $result->getDecodedBody();
    }
    
    
}

?>
