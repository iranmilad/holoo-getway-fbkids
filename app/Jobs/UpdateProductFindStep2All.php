<?php
namespace App\Jobs;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateMultiProductsUser;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\UpdateProductFindStep2VariationAll;


class UpdateProductFindStep2All implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    protected $category;
    protected $config;
    protected $holoo_cat;
    protected $wc_cat;
    protected $wcProducts;

    public $flag;
    public $timeout = 3*60;
    public $failOnTimeout = true;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$category,$config,$flag,$holoo_cat,$wc_cat,$wcProducts)
    {

        $this->user=$user;
        $this->config=$config;
        $this->category=$category;
        $this->flag=$flag;
        $this->holoo_cat=$holoo_cat;
        $this->wc_cat=$wc_cat;
        $this->wcProducts=$wcProducts;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {


            Log::info('queue update product find step 2 start for all category');
            log::info('product fetch compelete for all category ');
            $response_product=[];
            $wcholooCounter=0;
            $holooFinded=0;
            $conflite=0;
            $page=1;
            $data=[];
            $wcHolooCode=[];

            $variation=[];

                $wcProducts =$this->wcProducts;
                foreach ($wcProducts as $WCProd) {

                    if (count($WCProd->meta_data)>0) {
                        if ($WCProd->type=='simple') {
                            $wc_holoo_code=$this->findKey($WCProd->meta_data,'_holo_sku');
                            if ($wc_holoo_code==null) continue;

                            $wcHolooCode[] =(string) $wc_holoo_code;
                        }
                        else if($WCProd->type=='variable'){
                            $variation[]=$WCProd->id;
                        }
                    }

                }

                log::info("send holo code for update");
                if (count($wcHolooCode)>0) {
                    $holooProducts = $this->GetMultiProductHoloo($wcHolooCode);
                    $holooProducts = $this->reMapHolooProduct($holooProducts);
                    if(count($holooProducts)!=0) {

                        //return;
                        foreach ($wcProducts as $WCProd) {

                            if ($WCProd->type=='simple') {
                                $wcholooCounter=$wcholooCounter+1;
                                $holooFinded=$holooFinded+1;
                                if (count($WCProd->meta_data)>0) {


                                    $wc_holoo_code=$this->findKey($WCProd->meta_data,'_holo_sku');
                                    if ($wc_holoo_code==null or !array_key_exists((string)$wc_holoo_code, $holooProducts)) continue;
                                    $HolooProd = $holooProducts[(string)$wc_holoo_code];

                                    $wholesale_customer_wholesale_price= $this->findKey($WCProd->meta_data,'wholesale_customer_wholesale_price');

                                    if (
                                        isset($this->config->update_product_price) && $this->config->update_product_price=="1" &&
                                        (
                                        (isset($this->config->sales_price_field) && (int)$WCProd->regular_price != $this->get_price_type($this->config->sales_price_field,$HolooProd)) or
                                        (isset($this->config->special_price_field) && (int)$WCProd->sale_price  != $this->get_price_type($this->config->special_price_field,$HolooProd)) or
                                        (isset($this->config->wholesale_price_field) && (int)$wholesale_customer_wholesale_price  != $this->get_price_type($this->config->wholesale_price_field,$HolooProd))
                                        ) or
                                        ((isset($this->config->update_product_stock) && $this->config->update_product_stock=="1")  and $WCProd->stock_quantity != $this->get_exist_type($this->config->product_stock_field,$HolooProd)) or
                                        ((isset($this->config->update_product_name) && $this->config->update_product_name=="1") && $WCProd->name != trim($this->arabicToPersian($HolooProd->name)))

                                    ){
                                        $conflite=$conflite+1;
                                        $data[] = [
                                            'id' => $WCProd->id,
                                            'name' =>(isset($this->config->update_product_name) && $this->config->update_product_name=="1") && ($WCProd->name != $this->arabicToPersian($HolooProd->name)) ? $this->arabicToPersian($HolooProd->name) :$WCProd->name,
                                            'regular_price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && ((int)$WCProd->regular_price != $this->get_price_type($this->config->sales_price_field,$HolooProd)) ? $this->get_price_type($this->config->sales_price_field,$HolooProd) : (int)$WCProd->regular_price,
                                            'price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($this->config->special_price_field,$HolooProd)) ? $this->get_price_type($this->config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                            'sale_price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && ((int)$WCProd->sale_price != $this->get_price_type($this->config->special_price_field,$HolooProd)) ? $this->get_price_type($this->config->special_price_field,$HolooProd)  :(int)$WCProd->sale_price,
                                            'wholesale_customer_wholesale_price' => (isset($this->config->update_product_price) && $this->config->update_product_price=="1") && ((int)$wholesale_customer_wholesale_price != $this->get_price_type($this->config->wholesale_price_field,$HolooProd)) ? $this->get_price_type($this->config->wholesale_price_field,$HolooProd)  : ((isset($wholesale_customer_wholesale_price)) ? (int)$wholesale_customer_wholesale_price : null),
                                            'stock_quantity' => (isset($this->config->update_product_stock) && $this->config->update_product_stock=="1") ? $this->get_exist_type($this->config->product_stock_field,$HolooProd) : (int)$WCProd->stock_quantity,
                                        ];
                                        log::info("add new update product to queue for product ");
                                        log::info("for website id : ".$this->user->siteUrl);

                                    }
                                }

                            }

                        }
                        if(count($data)>0){
                            UpdateMultiProductsUser::dispatch((object)["queue_server"=>$this->user->queue_server,"id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret],$data,$this->flag)->onConnection($this->user->queue_server)->onQueue("high");
                        }
                    }
                    else{
                        log::info('holoo product not found');
                    }

                }
                if(count($variation)>0){
                    $countvariation=count($variation);
                    $this->updateWCVariation($variation,$this->config);

                }
            log::info("update finish for website : ".$this->user->siteUrl." for product count : ".$wcholooCounter);
    }

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
    public function updateWCVariation($variations,$config){
        foreach ($variations as $wcId){
            UpdateProductFindStep2VariationAll::dispatch((object)$this->user,$config,$wcId)->onConnection($this->user->queue_server)->onQueue("medium");
        }
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
    public function failed()
    {
        log::warning("failed to update product step 2 all ");
        log::warning("for website id : ".$this->user->siteUrl);
        $this->delete();
    }

}
