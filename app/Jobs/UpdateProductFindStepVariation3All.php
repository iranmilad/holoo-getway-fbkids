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



class UpdateProductFindStepVariation3All implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    protected $category;
    protected $config;
    protected $holooProducts;
    protected $wcId;
    public $flag;
    public $timeout = 3*60*60;
    public $failOnTimeout = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$category,$config,$flag,$holooProducts,$wcId)
    {

        $this->user=$user;
        $this->config=$config;
        $this->category=$category;
        $this->flag=$flag;
        $this->holooProducts=$holooProducts;
        $this->wcId=$wcId;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        Log::info(' queue update product find step 3 start for all category');

        $holooProducts = $this->holooProducts;

        $config=$this->config;
        $response_product=[];

        $wcholooCounter=0;
        $holooFinded=0;
        $conflite=0;
        $wcCount=0;
        $variation=[];
        //log::info($this->config);
        $wcId=$this->wcId;
        $wcProducts=$this->get_variation_product( $this->wcId);
        // if($wcId==9865){
        //     log::warning ($wcProducts);
        // }
        if(!$wcProducts){
            log::alert("not found wc product for variation $wcId");

        }
        foreach ($wcProducts as $WCProd) {
            if (count($WCProd->meta_data)>0) {

                $wcHolooCode = $this->findKey($WCProd->meta_data,'_holo_sku');

                if ($wcHolooCode) {
                    $wcholooCounter=$wcholooCounter+1;
                    $productFind = false;
                    foreach ($holooProducts as $key=>$HolooProd) {

                        //$HolooProd= $HolooProd->result;

                        $HolooProd=(object)$HolooProd;
                        // print($key);
                        // print_r($HolooProd);
                        // die();

                        if ($wcHolooCode === $HolooProd->a_Code) {

                            $productFind = true;
                            $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                            if (
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
                                    'wholesale_customer_wholesale_price' => (isset($config->update_product_price) && $config->update_product_price=="1") && ((int)$wholesale_customer_wholesale_price != $this->get_price_type($config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                    'stock_quantity' => (isset($config->update_product_stock) && $config->update_product_stock=="1") ? $this->get_exist_type($config->product_stock_field,$HolooProd) : (int)$WCProd->stock_quantity,
                                ];
                                log::info("add new update product to queue for product variation");
                                log::info("for website id : ".$this->user->siteUrl);

                                UpdateProductsVariationUser::dispatch((object)["queue_server"=>$this->user->queue_server,"id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret],$data,$wcHolooCode)->onConnection($this->user->queue_server)->onQueue("low");


                                $notneedtoProsse[]=$key;



                            }
                            else{
                                $notneedtoProsse[]=$key;

                            }
                        }

                    }


                }

            }
        }


        if(count($variation)>0){
            $countvariation=$this->updateWCVariation($variation,$holooProducts,$this->config);
            $wcholooCounter=$wcholooCounter+$countvariation;
        }

        Log::info( $wcholooCounter ." product(s) update");
        log::info('product fetch compelete for all category in step 3');

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

        $curl = curl_init();
        // log::info("yes");
        curl_setopt_array($curl, array(
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
                'Authorization: Bearer ' .$this->getNewToken(),
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response=json_decode($response, true)["data"]["product"];
        //print_r($response);
        return $response;
    }



    public function fetchAllWCProds($published=false,$category=null)
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
        $page = 1;
        $products = [];
        $all_products = [];
        do{
          try {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products?'.$status.$category.'meta=_holo_sku&page='.$page.'&per_page=10000',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_USERAGENT => 'Holoo',
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            ));

            $response = curl_exec($curl);

            if($response){
                $products = json_decode($response);
                if(is_array($products) and count($products)>0){
                    $all_products = array_merge($all_products,$products);
                }

            }

          }
          catch(\Throwable $th){
            log::error("error in fetchAllWCProds".$th->getMessage());
            break;
          }
          $page++;
        } while (is_array($products) and count($products) > 0);

        curl_close($curl);

        return $all_products;



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

    public function updateWCVariation($variations,$holooProducts,$config){
        //return;

        ini_set('max_execution_time', 0); // 120 (seconds) = 2 Minutes
        set_time_limit(0);
        $notneedtoProsse=[];
        $wcholooCounter=0;
        foreach ($variations as $wcId){

        }
        return $wcholooCounter;

    }

    public function get_variation_product($product_id){

        $curl = curl_init();
        $all_products=[];
        $products=[];
        $page=1;
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
            if ($response) {
                $products = json_decode($response);
                if (count($products)>0) {
                    $all_products = array_merge($all_products,$products);
                }
            }
            $page=$page+1;
        }
        while (count($products) == 100);

        return $all_products;
    }


    private function get_exist_type($exist_field,$HolooProd){
        // "sales_price_field": "1",
        // "special_price_field": "2",
        // "wholesale_price_field": "3",


        if((int)$exist_field==1){
            return (int)(float) $HolooProd->few;
        }
        elseif((int)$exist_field==2){
            return (int)(float) $HolooProd->fewspd;
        }
        elseif((int)$exist_field==3){
            return (int)(float) $HolooProd->fewtak;
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
        log::warning("failed to update product find step variation 3 all");
        log::warning("for website id : ".$this->user->siteUrl);
        $this->delete();
    }
}
