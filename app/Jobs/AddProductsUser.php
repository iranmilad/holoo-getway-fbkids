<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AddProductsUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 10*60;
    protected $user;
    protected $param;
    protected $categories;
    protected $type;
    protected $cluster;
    public $flag;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$param,$categories,$flag,$type="simple",$cluster=[])
    {
        $this->user=$user;
        $this->param=$param;
        $this->categories=$categories;
        $this->flag=$flag;
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
        $meta = array(
            (object)array(
                'key' => '_holo_sku',
                'value' => $this->param["holooCode"]
            ),
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => $this->param["wholesale_customer_wholesale_price"]
            )
        );
        if ($this->type=="variable") {
            $options=$this->variableOptions($this->cluster);
            $meta =$this->variableMetas($this->cluster);
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
                    'name' => $this->param["name"],
                    'type' => $this->type,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category,
                    'attributes' => $attributes,
                );
            }
            else{
                $data = array(
                    'name' => $this->param["name"],
                    'type' => $this->type,
                    'regular_price' =>(string)$this->param["regular_price"],
                    'stock_quantity' =>(int)$this->param["stock_quantity"],
                    'status' => 'draft',
                    "manage_stock" => true,
                    'meta_data' => $meta,
                    'attributes' => $attributes,
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
                    'name' => $this->param["name"],
                    'type' => 'simple',
                    'regular_price' => (string)$this->param["regular_price"],
                    'price' => $this->param["price"],
                    'sale_price' => ((int)$this->param["sale_price"]==0) ? null:(string)$this->param["sale_price"],
                    'stock_quantity' =>(int)$this->param["stock_quantity"],
                    "manage_stock" => true,
                    'status' => 'draft',
                    'meta_data' => $meta,
                    'categories' => $category
                );
            }
            else{
                $data = array(
                    'name' => $this->param["name"],
                    'type' => 'simple',
                    'regular_price' => (string)$this->param["regular_price"],
                    'sale_price' => ((int)$this->param["sale_price"]==0) ? null:(string)$this->param["sale_price"],
                    'stock_quantity' => (int)$this->param["stock_quantity"],
                    "manage_stock" => true,
                    'status' => 'draft',
                    'meta_data' => $meta,
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
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpcode !=201 and $httpcode !=200){

            log::info($data);
            log::alert("get http code ".$httpcode." for user ".$this->user->id);
            log::alert($response);

        }
        if ($this->type=="variable") {
            if ($response){
                $decodedResponse = json_decode($response,true);
                $this->AddProductVariation($decodedResponse["id"],$this->param,$this->cluster);
            }
        }
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->user->id.$this->flag;
    }


    private function AddProductVariation($id,$product,$clusters){

        $curl = curl_init();

        // $data = array(
        //     'name' => $product["holooName"],
        //     'type' => $type,
        //     'regular_price' => $product["holooRegularPrice"],
        //     'stock_quantity' => $product["holooStockQuantity"],
        //     'status' => 'draft',
        //     'meta_data' => $meta,
        //     'attributes' => $attributes,
        // );

        foreach($clusters as $cluster){

            $meta = array(
                (object)array(
                    'key' => '_holo_sku',
                    'value' => $this->param["holooCode"].'*'. $cluster->id
                )
            );
            $data=array(
                'description' => $cluster->name,
                'regular_price' => (string)$this->param["regular_price"],
                'sale_price' => ((int)$this->param["sale_price"]==0) ? null:$this->param["sale_price"],
                'stock_quantity' => $cluster->few,
                "manage_stock" => true,
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
              CURLOPT_POSTFIELDS => $data,
              CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
              CURLOPT_USERAGENT => 'Holoo',
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
            $options[]=$cluster->name;
        }

        return $options;

    }

    private function variableMetas($clusters){

        $metas = array(
            (object)array(

                'key' => '_holo_sku',
                'value' => $this->param["holooCode"]
            ),
            (object)array(

                'key' => '_holo_type',
                'value' => 'poshak'
            ),
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => $this->param["wholesale_customer_wholesale_price"]
            )
        );
        // foreach ( $clusters as $key=>$cluster){
        //     //log::info(gettype($cluster));
        //     //$response=json_encode($cluster,true);
        //     //log::info($response);

        //     $ss=(object)array(
        //         'key' => '_holo_sku',
        //         'value' => $this->param["holooCode"].'*'. $cluster->id
        //     );
        //     array_push($metas,$ss);
        // }
        // log::info($metas);
        return $metas;

    }


}
