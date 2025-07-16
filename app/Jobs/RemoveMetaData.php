<?php

namespace App\Jobs;

use App\Jobs\AddProductsUser;
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
        $HolooProds = json_decode($response)->data->product;
        // log::info($HolooProds);
        // log::info($this->category->m_groupcode);
        // log::info($this->category->s_groupcode);

        foreach ($HolooProds as $HolooProd) {
            if (!in_array($HolooProd->a_Code, $this->wcHolooExistCode)) {
                //if ($HolooProd->a_Code!='0204003') continue;
                $param = [
                    "holooCode" => $HolooProd->a_Code,
                    'name' => $this->arabicToPersian($HolooProd->name),
                    'regular_price' => $this->get_price_type($this->request["sales_price_field"],$HolooProd),
                    'price' => $this->get_price_type($this->request["special_price_field"],$HolooProd),
                    'sale_price' => $this->get_price_type($this->request["special_price_field"],$HolooProd),
                    'wholesale_customer_wholesale_price' => $this->get_price_type($this->request["wholesale_price_field"],$HolooProd),
                    'stock_quantity' => $this->get_exist_type($this->request["product_stock_field"],$HolooProd),
                ];

                if(is_array($this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode])){
                    $prodcat=$this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode][0];
                }
                else{
                    $prodcat=$this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode];
                }

                if ((!isset($this->request["insert_product_with_zero_inventory"]) && $this->get_exist_type($this->request["product_stock_field"],$HolooProd) > 0) || (isset($this->request["insert_product_with_zero_inventory"]) && $this->request["insert_product_with_zero_inventory"] == "0" && $this->get_exist_type($this->request["product_stock_field"],$HolooProd) > 0)) {


                    if (isset($HolooProd->poshak)) {
                        AddProductsUser::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code,"variable",$HolooProd->poshak)->onConnection($this->user->queue_server)->onQueue('poshak');
                    }
                    else{
                        //Log::info(['id' => $this->request["product_cat"][$this->category->m_groupcode."-".$this->category->s_groupcode], "name" => ""]);
                        AddProductsUser::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code)->onConnection($this->user->queue_server)->onQueue("default");
                    }
                }
                elseif (isset($this->request["insert_product_with_zero_inventory"]) && $this->request["insert_product_with_zero_inventory"] == "1") {

                    if (isset($HolooProd->poshak)) {
                        AddProductsUser::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code,"variable",$HolooProd->poshak)->onConnection($this->user->queue_server)->onQueue("poshak");
                    }
                    else{
                        AddProductsUser::dispatch($this->user, $param, ['id' => $prodcat, "name" => ""], $HolooProd->a_Code)->onConnection($this->user->queue_server)->onQueue("default");
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
    /**
     * The unique ID of the job.
     *
     * @return string
     */
    // public function uniqueId()
    // {
    //     return $this->user->id.$this->flag;
    // }

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
