<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class createSingleProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    public $flag;
    public $categories;
    public $type;
    public $cluster;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$param,$flag,$categories=null,$type="simple",$cluster=[])
    {
        Log::info(' queue update product start');
        $this->user=$user;
        $this->param=$param;
        $this->flag=$flag;
        $this->categories=$categories;
        $this->type=$type;
        $this->cluster=$cluster;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('update product for flag ' . $this->flag);

        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $this->param["holooCode"]
            )
        );
        if ($this->type=="variable") {
            $options=$this->variableOptions($this->cluster);
            $attributes = array(
                (object)array(
                    'id'        => 5,
                    'variation' => true,
                    'visible'   => true,
                    'options'   => $options,
                )
            );
            if ($this->categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $this->categories["id"],
                        "name" => $this->categories["name"],
                    )
                );
                $data = array(
                    'name' => $this->param["holooName"],
                    'type' => $this->type,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    'attributes' => $attributes,
                    "manage_stock" => true,
                );
            }
            else{
                $data = array(
                    'name' => $this->param["holooName"],
                    'type' => $this->type,
                    'regular_price' => $this->param["regular_price"],
                    'stock_quantity' => $this->param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'attributes' => $attributes,
                    "manage_stock" => true,
                );
            }
        }
        else {

            if ($this->categories !=null) {
                $category=array(
                    (object)array(
                        'id' => $this->categories["id"],
                        //"name" => $this->categories["name"],
                    )
                );
                $data = array(
                    'name' => $this->param["holooName"],
                    'type' => $this->type,
                    'regular_price' => $this->param["regular_price"],
                    'stock_quantity' => $this->param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    "manage_stock" => true,
                );
            }
            else{
                $data = array(
                    'name' => $this->param["holooName"],
                    'type' => $this->type,
                    'regular_price' => $this->param["regular_price"],
                    'stock_quantity' => $this->param["stock_quantity"],
                    'status' => 'draft',
                    'meta_data' => $meta,
                    "manage_stock" => true,
                );
            }
        }


        $data = json_encode($data);
        //return response($data);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $decodedResponse = ($response) ?? json_decode($response);

        if ($response && isset($decodedResponse->id)){

            if ($this->type=="variable") {
               $a= $this->AddProductVariation($decodedResponse->id,$this->param,$this->cluster);
            }
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

    private function AddProductVariation($id,$product,$clusters){
        $curl = curl_init();

        // $data = array(
        //     'name' => $product["holooName"],
        //     'type' => $type,
        //     'regular_price' => $product["regular_price"],
        //     'stock_quantity' => $product["stock_quantity"],
        //     'status' => 'draft',
        //     'meta_data' => $meta,
        //     'attributes' => $attributes,
        // );
        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $product["holooCode"]
            )
        );

        foreach($clusters as $cluster){

            $data=array(
                'description' => $this->arabicToPersian($cluster->Name),
                'regular_price' => $product["regular_price"],
                'sale_price' => $product["regular_price"],
                'stock_quantity' => $cluster->Few,
                //'status' => 'draft',
                'meta_data' => $meta,


                // 'weight' => $cluster->,
                // 'dimensions' => '<string>',
                //'meta_data' => $meta,
            );
            $data = json_encode($data);

            curl_setopt_array($curl, array(
              CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/'.$id.'/variations?per_page=100',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_USERAGENT => 'Holoo',
              CURLOPT_POSTFIELDS => $data,
              CURLOPT_USERPWD =>  $this->user->consumerKey. ":" .  $this->user->consumerSecret,
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
              ),
            ));

            $response = curl_exec($curl);
            //return $response;
        }


        curl_close($curl);
        return $response;

    }

    private function variableOptions($clusters){
        $options=[];

        foreach ( $clusters as $key=>$cluster){
            $options[]=$cluster->Name;
        }

        return $options;

    }

    public static function arabicToPersian($string)
    {

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
}
