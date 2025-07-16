<?php

namespace App\Jobs;

use Carbon\Carbon;

use Illuminate\Bus\Queueable;

use Illuminate\Support\Facades\Log;

use Illuminate\Queue\SerializesModels;

use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\UpdateProductsVariationUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;



class UpdateProductFindStep2VariationAll implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    protected $config;
    protected $parent_id;

    public $flag;
    public $timeout = 5*60;
    public $failOnTimeout = true;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$config,$parent_id)
    {

        $this->user=$user;
        $this->config=$config;
        $this->parent_id=$parent_id;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {


        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        Log::info("start update variation products for wc parent id ". $this->parent_id);
        $this->updateWCVariation($this->parent_id,$this->config);
        Log::info("finish update variation products for wc parent id ". $this->parent_id);

    }




    /**
     * The unique ID of the job.
     *
     * @return string
     */
    // public function uniqueId()
    // {
    //     return $this->user->id.$this->flag;
    // }


    private function getNewToken(): string
    {
        $userSerial = $this->user->serial;
        $userApiKey = $this->user->apiKey;
        if ($this->user->cloudTokenExDate > Carbon::now()) {

            return $this->user->cloudToken;
        }
        else {


            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Ticket/RegisterForPartner',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('Serial' => $userSerial, 'RefreshToken' => 'false', 'DeleteService' => 'false', 'MakeService' => 'true', 'RefreshKey' => 'false'),
                CURLOPT_HTTPHEADER => array(
                    'apikey:' . $userApiKey,
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($response);

            if ($response) {
                log::info("take new token request and response");
                log::info(json_encode($response));

                $this->user->cloudTokenExDate = Carbon::now()->addHour(4);
                return $response->result->apikey;
            }
        }
    }


    public function fetchCategoryHolloProds($categorys)
    {
        $totalProduct=[];

        $curl = curl_init();
        foreach ($categorys as $category_key=>$category_value) {
            if ($category_value != "") {
                $m_groupcode=explode("-",$category_key)[0];
                $s_groupcode=explode("-",$category_key)[1];
                if ($this->user->user_traffic=='heavy') {
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?maingroupcode='.$m_groupcode,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'GET',
                        CURLOPT_HTTPHEADER => array(
                            'serial: ' . $this->user->serial,
                            'database: ' . $this->user->holooDatabaseName,
                            'access_token: ' . $this->user->apiKey,
                            'Authorization: Bearer ' .$this->getNewToken(),
                        ),
                    ));

                }
                else{
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?sidegroupcode='.$s_groupcode.'&maingroupcode='.$m_groupcode,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'GET',
                        CURLOPT_HTTPHEADER => array(
                            'serial: ' . $this->user->serial,
                            'database: ' . $this->user->holooDatabaseName,
                            'access_token: ' . $this->user->apiKey,
                            'Authorization: Bearer ' .$this->getNewToken(),
                        ),
                    ));

                }

                $response = curl_exec($curl);

                if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
                    $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);
                }

            }


        }


        return $totalProduct;
    }


    public function fetchAllHolloProds()
    {
        $totalProduct=[];
        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProductsPagingCount',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'serial: ' . $this->user->serial,
                'database: ' . $this->user->holooDatabaseName,
                'access_token: ' . $this->user->apiKey,
                'Authorization: Bearer ' .$this->user->cloudToken,
            ),
        ));

        $response = curl_exec($curl);
        $totalCount=json_decode($response, true)["data"]["totalCount"];
        $totalPage=ceil($totalCount/2000);
        log::info('total cloud page is '.$totalPage);
        $totalPage=1;
        for ($x = 1; $x <= $totalPage; $x+=1) {

            curl_setopt_array($curl, array(
                // CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProductsPaging/'.$x.'/2000',
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $this->user->serial,
                    'database: ' . $this->user->holooDatabaseName,
                    'access_token: ' . $this->user->apiKey,
                    'Authorization: Bearer ' .$this->user->cloudToken,
                ),
            ));

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);


            if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"]) and count(json_decode($response, true)["data"]["product"])){
                //log::info(json_decode($response, true)["data"]["product"]);
                $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ?? [] , $totalProduct ?? []);
            }
            else{
                log::warning('cloud holo dont response any value');
                log::warning("get http code ".$httpcode." for all product for user: ".$this->user->id);
                log::warning($response);

                break;
            }
        }
        curl_close($curl);
        //print_r($response);
        return $totalProduct;
    }


    public function fetchAllWCProds($published=false,$category=null,$page=1)
    {

        if($published){
            $status= "status=publish&" ;
        }
        else{
            $status= "";
        }

        if($category){
            $category= "category=$category&";
        }
        else{
            $category= "";
        }

        $curl = curl_init();

        $products = [];
        $all_products = [];

        try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products?'.$status.$category.'meta=_holo_sku&page='.$page.'&per_page=100',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            ));

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if($response){
                $products = json_decode($response);
                if(is_array($products) and count($products)>0){
                    $all_products = array_merge($all_products,$products);
                }
                else{
                    if($page==1){
                        log::error("error in WCProds not array for user id ".$this->user->id);
                        log::warning("get http code ".$httpcode." for wc number ".$page." for user: ".$this->user->id);
                        log::error(json_encode($products));
                    }

                }
            }

        }
        catch(\Throwable $th){
            log::error("error in fetchAllWCProds ".$th->getMessage());

        }




        curl_close($curl);

        return $all_products;



    }

    public function GetMultiProductHoloo($holooCodes)
    {
        $curl = curl_init();
        $holooCodes=array_unique($holooCodes);
        $totalPage=ceil(count($holooCodes)/100);
        $totalProduct=[];

        for ($x = 1; $x <= $totalPage; $x+=1) {

            $GroupHolooCodes=implode(',', array_slice($holooCodes,($x-1)*100,100*$x));

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $GroupHolooCodes,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $this->user->serial,
                    'database: ' . $this->user->holooDatabaseName,
                    'access_token: ' . $this->user->apiKey,
                    'Authorization: Bearer ' .$this->user->cloudToken,
                ),
            ));
            $response = curl_exec($curl);
            if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
                $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);
            }

            $err = curl_errno($curl);
            $err_msg = curl_error($curl);
            $header = curl_getinfo($curl);

            log::info("start log cloud");
            //Log::info($header);

        }
        //$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        //Log::info($err_msg);
        //Log::info($err);
        //log::info("finish log cloud");
        //log::info("get http code ".$httpcode."  for get single product from cloud for holoo product id: ".$holoo_id);

        curl_close($curl);
        return $totalProduct;

    }

    public function GetMultiPoshakProductHoloo($holooCodes)
    {

        $curl = curl_init();
        $totalProduct=[];
        $holooCodes=array_unique($holooCodes);

        foreach($holooCodes as $holooCode){

            $HolooIDs=explode("*",$holooCode);
            $a_code= $HolooIDs[0];
            $id =  $HolooIDs[1];

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?a_Code=' . $a_code,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'serial: ' . $this->user->serial,
                    'database: ' . $this->user->holooDatabaseName,
                    'access_token: ' . $this->user->apiKey,
                    'Authorization: Bearer ' .$this->user->cloudToken,
                ),
            ));


            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if($response and isset(json_decode($response, true)["data"]) and isset(json_decode($response, true)["data"]["product"])){
                $totalProduct=array_merge(json_decode($response, true)["data"]["product"] ??[],$totalProduct??[]);

            }
        }

        // $err = curl_errno($curl);
        // $err_msg = curl_error($curl);
        // $header = curl_getinfo($curl);

        // log::info("start log cloud");
        // Log::info($header);
        // Log::info($err_msg);
        // Log::info($err);
        // log::info("finish log cloud");
        // php array to string
        // $response = json_encode($response);

        curl_close($curl);
        return $totalProduct;

    }



    private function findKey($array, $key)
    {
        foreach ($array as $k => $v) {
            if (isset($v->key) and $v->key == $key) {
                return $v->value;
            }
        }
        return null;
    }


    private function get_price_type($price_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",

        // "sellPrice": 12000,
        // "sellPrice2": 0,
        // "sellPrice3": 0,
        // "sellPrice4": 0,
        // "sellPrice5": 0,
        // "sellPrice6": 0,
        // "sellPrice7": 0,
        // "sellPrice8": 0,
        // "sellPrice9": 0,
        // "sellPrice10": 0,


        if((int)$price_field==1){
            return (int)(float) $HolooProd->sellPrice*$this->get_tabdel_vahed();
        }
        else{
            return (int)(float) $HolooProd->{"sellPrice".$price_field}*$this->get_tabdel_vahed();
        }
    }


    public static function arabicToPersian($string){

        $characters = [
            'ك' => 'ک',
            'دِ' => 'د',
            'بِ' => 'ب',
            'زِ' => 'ز',
            'ذِ' => 'ذ',
            'شِ' => 'ش',
            'سِ' => 'س',
            'ى' => 'ی',
            'ي' => 'ی',
            '١' => '۱',
            '٢' => '۲',
            '٣' => '۳',
            '٤' => '۴',
            '٥' => '۵',
            '٦' => '۶',
            '٧' => '۷',
            '٨' => '۸',
            '٩' => '۹',
            '٠' => '۰',
        ];
        return str_replace(array_keys($characters), array_values($characters), $string);
    }

    public function get_tabdel_vahed(){

        // log::alert($user->holo_unit);
        if ($this->user->holo_unit=="rial" and $this->user->plugin_unit=="toman"){
            return 0.1;
        }
        elseif ($this->user->holo_unit=="toman" and $this->user->plugin_unit=="rial"){
            return 10;
        }
        else{
            return 1;
        }

    }

    public function updateWCVariation($wcId,$config){
        //return;

        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        $notneedtoProsse=[];
        $wcholooCounter=0;
        $wcHolooCodes=[];
        $wcHolooCodesVariation=[];

        $wcProducts=$this->get_variation_product($wcId);


        if(!$wcProducts){
            log::alert("not found wc product for variation $wcId");
            return;
        }

        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {
                $holocodes=$this->findKey($WCProd->meta_data,'_holo_sku');
                if ($holocodes==null) continue;
                // add new code for holo id code with props
                $pos = strpos($holocodes, "*");
                if ($pos === false) {
                    $wcHolooCodes[] = $holocodes;
                }
                else{
                    $wcHolooCodesVariation[] = $holocodes;
                }
            }
        }

        // normal product
        if(count($wcHolooCodes)>0){
            $callApi = $this->GetMultiProductHoloo($wcHolooCodes);
            if(!isset($callApi)){
                Log::warning("dont find any holoo code");
                Log::warning($wcHolooCodes);
                Log::warning($callApi);

            }

            $holooProducts = $callApi;
            $holooProducts = $this->reMapHolooProduct($holooProducts);

            if ($holooProducts) {
                $wcholooCounter=$wcholooCounter+1;

                foreach ($wcProducts as $WCProd) {
                    if (count($WCProd->meta_data)>0) {



                        $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');
                        // if($WCProd->id =="13482"){
                        //     log::warning ("this is wc 13482 with meta ");
                        //     log::warning ($wcHolooCode);
                        // }

                        // if($wcHolooCode =="0302024"){
                        //     log::warning ("this is wcHolooCode holoo code ");
                        //     log::warning ($holooProducts);
                        // }

                        if(isset($holooProducts[(string)$wcHolooCode])){
                            $HolooProd=$holooProducts[(string)$wcHolooCode];
                        }
                        else{
                            continue;
                        }
                        $HolooProd=(object)$HolooProd;



                        if ($wcHolooCode === $HolooProd->a_Code) {

                            $productFind = true;
                            $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                            if(
                                isset($config->update_product_price) && $config->update_product_price=="1" &&
                                (
                                (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                                (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                                (isset($config->wholesale_price_field) && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
                                ) or
                                ((isset($config->update_product_stock) && $config->update_product_stock=="1") and $WCProd->stock_quantity != $this->get_exist_type($config->product_stock_field,$HolooProd)) or
                                ((isset($config->update_product_name) && $config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->name)))

                            ){


                                $data = [
                                    'id' => $wcId ,
                                    'variation_id' => $WCProd->id,

                                    'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) ? $this->get_price_type($config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                    'price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'sale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && (isset($wholesale_customer_wholesale_price) && (int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                    'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1") ? $this->get_exist_type($config->product_stock_field,$HolooProd) : (int)$WCProd->stock_quantity,
                                ];
                                log::info("add new update product to queue for product variation");
                                log::info("for website id : ".$this->user->siteUrl);

                                UpdateProductsVariationUser::dispatch((object)["queue_server"=>$this->user->queue_server,"id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret],$data,$wcHolooCode)->onConnection($this->user->queue_server)->onQueue("low");

                            }

                        }


                    }
                }

            }

        }
        //log::info("variation");
        //log::info($wcHolooCodesVariation);
        // variation product
        if(count($wcHolooCodesVariation)>0){

            $callApi = $this->GetMultiPoshakProductHoloo($wcHolooCodesVariation);

            if(!isset($callApi)){
                Log::warning("dont find any holoo code");
                Log::warning($wcHolooCodesVariation);
                Log::warning($callApi);

            }

            $holooProducts = $callApi;
            $holooProducts = $this->reMapPoshakHolooProduct($holooProducts);
            //log::info($holooProducts);

            if ($holooProducts) {
                $wcholooCounter=$wcholooCounter+1;

                foreach ($wcProducts as $WCProd) {
                    if (count($WCProd->meta_data)>0) {



                        $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');

                        //log::info($wcHolooCode);

                        if(isset($holooProducts[(string)$wcHolooCode])){
                            $HolooProd=$holooProducts[(string)$wcHolooCode];
                        }
                        else{
                            continue;
                        }
                        $HolooProd=(object)$HolooProd;

                        //log::info($HolooProd->a_Code);
                        //log::info($HolooProd->id);

                        if ($wcHolooCode === $HolooProd->a_Code."*".$HolooProd->id) {
                            //log::info($HolooProd->a_Code);
                            $productFind = true;
                            $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                            if(
                                isset($config->update_product_price) && $config->update_product_price=="1" &&
                                (
                                (isset($config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) or
                                (isset($config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($config->special_price_field,$HolooProd)) or
                                (isset($config->wholesale_price_field) && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($config->wholesale_price_field,$HolooProd))
                                ) or
                                ((isset($config->update_product_stock) && $config->update_product_stock=="1") and $WCProd->stock_quantity != $this->get_exist_type($config->product_stock_field,$HolooProd))
                               // ((isset($config->update_product_name) && $config->update_product_name=="1") && (property_exists($WCProd, 'name') && $WCProd->name != trim($this->arabicToPersian($HolooProd->name)))

                            ){


                                $data = [
                                    'id' => $wcId ,
                                    'variation_id' => $WCProd->id,

                                    'regular_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($config->sales_price_field,$HolooProd)) ? $this->get_price_type($config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                    'price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'sale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($config->special_price_field,$HolooProd)) ? $this->get_price_type($config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                    'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                    'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1") ? $this->get_exist_type($config->product_stock_field,$HolooProd) : (int)$WCProd->stock_quantity,
                                ];
                                log::info("add new update product to queue for product variation");
                                log::info("for website id : ".$this->user->siteUrl);

                                UpdateProductsVariationUser::dispatch((object)["queue_server"=>$this->user->queue_server,"id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret],$data,$wcHolooCode)->onConnection($this->user->queue_server)->onQueue("poshak");

                            }

                        }


                    }
                }

            }
        }



    }

    public function get_variation_product($product_id){

        $curl = curl_init();
        $all_products=[];
        $page=1;
        $products=[];
        do {
            curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/'.$product_id.'/variations?per_page=100&page='.$page,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            if ($response!=null) {
                $products = json_decode($response);
                if (is_array($products) and count($products)>0) {
                    $all_products = array_merge($all_products,$products);
                }
            }
            $page=$page+1;
        }
        while ($products and count($products) == 100);

        return $all_products;
    }

    public function reMapHolooProduct($holooProducts){
        $newHolooProducts = [];
        if(is_array($holooProducts)){
            foreach ($holooProducts as $key=>$HolooProd) {
                $HolooProd=(object) $HolooProd;
                if (isset($HolooProd->a_Code)){
                    $newHolooProducts[$HolooProd->a_Code]=$HolooProd;
                }
            }
        }
        return $newHolooProducts;
    }

    private function get_exist_type($exist_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",


        if((int)$exist_field==1){
            return (int)(float) $HolooProd->few;
        }
        elseif((int)$exist_field==2){
            if(property_exists($HolooProd, 'fewspd'))  return (int)(float) $HolooProd->fewspd;
            else return (int)(float) $HolooProd->fewSpd;
        }
        elseif((int)$exist_field==3){
            if(property_exists($HolooProd, 'fewtak'))  return (int)(float) $HolooProd->fewtak;
            else return (int)(float) $HolooProd->fewTak;
        }
    }

    /**
     * Handle the failing job.
     *
     *
     * @return void
     */
    public function failed()
    {
        log::warning("failed to update product step 2 all ");
        log::warning("for website id : ".$this->user->siteUrl);
        $this->delete();
    }


    private function reMapPoshakHolooProduct($holooProducts){
        $newHolooProducts = [];
        // "code": null,
        // "name": "تست 1",
        // "few": 14.0,
        // "fewspd": 14.0,
        // "fewtak": 14.0,
        // "buyPrice": 4000000.0,
        // "sellPrice": 0.0,
        // "sellPrice2": 0.0,
        // "sellPrice3": 0.0,
        // "sellPrice4": 0.0,
        // "sellPrice5": 0.0,
        // "sellPrice6": 0.0,
        // "sellPrice7": 0.0,
        // "sellPrice8": 0.0,
        // "sellPrice9": 0.0,
        // "sellPrice10": 0.0,
        // "countInKarton": 0.0,
        // "countInBasteh": 0.0,
        // "mainGroupName": "پوشاک توليدي",
        // "mainGroupErpCode": "bBADfg==",
        // "sideGroupName": "پوشاک توليدي",
        // "sideGroupErpCode": "bBADNA1jDg0=",
        // "unitErpCode": "0",
        // "discountPercent": 0.0,
        // "discountPrice": 0.0,
        // "erpCode": "bBADNA1mckd7Zh4O",
        // "sideGroupCode": "01",
        // "a_Code": "0301001",
        // "mainGroupCode": "03",



        $HolooProds=(object) $holooProducts;

        foreach($HolooProds as $key=>$HolooProdVar){
            $HolooProd=(object) $HolooProdVar;
            if(property_exists($HolooProd, 'poshak') and $HolooProd->poshak!=null){
                foreach ($HolooProd->poshak as $key=>$poshak) {
                    $newPoshak=(object)$poshak;
                    $newPoshak->a_Code = $HolooProd->a_Code;
                    $newPoshak->variation_name = $newPoshak->name;
                    $newPoshak->name = $HolooProd->name;
                    $newPoshak->mainGroupName = $HolooProd->mainGroupName;
                    $newPoshak->sideGroupName = $HolooProd->sideGroupName;
                    $newPoshak->sideGroupCode = $HolooProd->sideGroupCode;
                    $newPoshak->mainGroupCode = $HolooProd->mainGroupCode;
                    $newPoshak->sellPrice =  $newPoshak->sellPrice["price"] ?? null;
                    $newPoshak->sellPrice2 = $newPoshak->sellPrice2["price"] ?? null;
                    $newPoshak->sellPrice3 = $newPoshak->sellPrice3["price"] ?? null;
                    $newPoshak->sellPrice4 = $newPoshak->sellPrice4["price"] ?? null;
                    $newPoshak->sellPrice5 = $newPoshak->sellPrice5["price"] ?? null;
                    $newPoshak->sellPrice6 = $newPoshak->sellPrice6["price"] ?? null;
                    $newPoshak->sellPrice7 = $newPoshak->sellPrice7["price"] ?? null;
                    $newPoshak->sellPrice8 = $newPoshak->sellPrice8["price"] ?? null;
                    $newPoshak->sellPrice9 = $newPoshak->sellPrice9["price"] ?? null;
                    $newPoshak->sellPrice10 = $newPoshak->sellPrice10["price"] ?? null;

                    $newHolooProducts[(string)$HolooProd->a_Code.'*'.(string)$newPoshak->id]= $newPoshak;
                }
            }
            else{
                foreach ($holooProducts as $key=>$HolooProd) {

                    $HolooProd=(object) $HolooProd;
                    $newHolooProducts[(string)$HolooProd->a_Code]=$HolooProd;
                }
            }

        }

        return $newHolooProducts;
    }

    private function get_poshak_price_type($price_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",

        // "sellPrice": 12000,
        // "sellPrice2": 0,
        // "sellPrice3": 0,
        // "sellPrice4": 0,
        // "sellPrice5": 0,
        // "sellPrice6": 0,
        // "sellPrice7": 0,
        // "sellPrice8": 0,
        // "sellPrice9": 0,
        // "sellPrice10": 0,
        //log::info(json_encode($HolooProd));

        if((int)$price_field==1){
            return (int)(float) $HolooProd->sellPrice["price"]*$this->get_tabdel_vahed();
        }
        else{
            if (isset($HolooProd->{"sellPrice".$price_field}["price"]))
            return (int)(float) $HolooProd->{"sellPrice".$price_field}["price"]*$this->get_tabdel_vahed();
            return 0;
        }
    }

}
