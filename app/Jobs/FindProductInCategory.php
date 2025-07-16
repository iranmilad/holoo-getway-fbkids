<?php

namespace App\Jobs;

use App\Jobs\AddProductsUser;
use App\Jobs\AddProductsUserVariation;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FindProductInCategory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60*60;
    public $failOnTimeout = true;


    protected $user;

    protected $category;
    protected $token;
    protected $wcHolooExistCode;
    protected $request;
    public $flag;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$category,$token,$wcHolooExistCode,$request,$flag)
    {
        $this->user=$user;
        $this->category=$category;
        $this->flag=$flag;
        $this->token=$token;
        $this->wcHolooExistCode=$wcHolooExistCode;
        $this->request=$request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(){
        //log::info("start");
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://myholoo.ir/api/Article/GetProducts?sidegroupcode='.$this->category->s_groupcode.'&maingroupcode='.$this->category->m_groupcode,
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
                'Authorization: Bearer ' .$this->token,
            ),
        ));
        $response = curl_exec($curl);
        if($response){
            $HolooProds = json_decode($response)->data->product;
        }
        else{
            $HolooProds = null;
        }
        // log::info($HolooProds);
        // log::info($this->category->m_groupcode);
        // log::info($this->category->s_groupcode);
        if($HolooProds){
            foreach ($HolooProds as $HolooProd) {
                if (!in_array($HolooProd->a_Code, $this->wcHolooExistCode)) {

                    $param = [
                        "holooCode" => $HolooProd->a_Code,
                        'name' => $this->arabicToPersian($HolooProd->name),
                        'regular_price' => $this->get_price_type($this->request["sales_price_field"],$HolooProd),
                        'price' => $this->get_price_type($this->request["special_price_field"],$HolooProd),
                        'sale_price' => $this->get_price_type($this->request["special_price_field"],$HolooProd),
                        'wholesale_customer_wholesale_price' => $this->get_price_type($this->request["wholesale_price_field"],$HolooProd),
                        'stock_quantity' => $this->get_exist_type($this->request["product_stock_field"],$HolooProd),
                    ];
                    // get first wordpress group
                    if(is_array($this->request["product_cat"]->{$this->category->m_groupcode."-".$this->category->s_groupcode})){
                        $prodcat=$this->request["product_cat"]->{$this->category->m_groupcode."-".$this->category->s_groupcode}[0];
                    }
                    else{
                        $prodcat=$this->request["product_cat"]->{$this->category->m_groupcode."-".$this->category->s_groupcode};
                    }

                    if ((!isset($this->request["insert_product_with_zero_inventory"]) && $this->get_exist_type($this->request["product_stock_field"],$HolooProd) > 0) || (isset($this->request["insert_product_with_zero_inventory"]) && $this->request["insert_product_with_zero_inventory"] == "0" && $this->get_exist_type($this->request["product_stock_field"],$HolooProd) > 0)) {


                        if (property_exists($HolooProd,"poshak") and isset($HolooProd->poshak) and count($HolooProd->poshak)>0 ) {
                            if ($this->user->poshak==true){
                                $holooProducts=$this->reMapPoshakHolooProduct($HolooProd);
                                foreach($holooProducts as $key=>$value){
                                    if(in_array($value->a_Code."*".$value->id, $this->wcHolooExistCode)){
                                        continue 2;
                                    }
                                }
                                AddProductsUserVariation::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code,"variable",$holooProducts,$this->wcHolooExistCode,$this->request)->onConnection($this->user->queue_server)->onQueue('poshak');
                            }
                            else{
                                continue;
                            }
                        }
                        else{

                            AddProductsUser::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code)->onConnection($this->user->queue_server)->onQueue("default");
                        }
                    }
                    elseif (isset($this->request["insert_product_with_zero_inventory"]) && $this->request["insert_product_with_zero_inventory"] == "1") {

                        if (property_exists($HolooProd,"poshak") and isset($HolooProd->poshak) and count($HolooProd->poshak)>0) {
                            if ($this->user->poshak==true){
                                $holooProducts=$this->reMapPoshakHolooProduct($HolooProd);
                                foreach($holooProducts as $key=>$value){

                                    if(in_array($value->a_Code."*".$value->id, $this->wcHolooExistCode)){
                                        continue 2;
                                    }
                                }
                                AddProductsUserVariation::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code,"variable",$HolooProd->poshak,$this->request)->onConnection($this->user->queue_server)->onQueue("poshak");
                            }
                            else{
                                continue;
                            }
                        }
                        else{
                            AddProductsUser::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code)->onConnection($this->user->queue_server)->onQueue("default");
                        }

                    }
                }

            }

        }
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



        $HolooProd=(object) $holooProducts;


        if(property_exists($HolooProd,"poshak") and $HolooProd->poshak!=null){
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
            foreach ($HolooProd as $key=>$NormalHolooProd) {
                $NormalHolooProd=(object) $NormalHolooProd;
                if (isset($NormalHolooProd->a_Code)){
                    $newHolooProducts[$NormalHolooProd->a_Code]=$NormalHolooProd;
                }
            }
        }

        return $newHolooProducts;
    }

    /**
     * Handle the failing job.
     *
     *
     * @return void
     */
    public function failed()
    {
        log::warning("failed to find Product in category product");
        log::warning("for website id : ".$this->user->siteUrl);
        $this->delete();
    }

}
