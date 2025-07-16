<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class UpdateProductsVariationUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    public $flag;
    public $timeout = 10*60;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$param,$flag)
    {
        Log::info(' queue update product variation start');
        $this->user=$user;
        $this->param=$param;
        $this->flag=$flag;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('update product for flag ' . $this->flag);

        $curl = curl_init();
        $meta = array(
            (object)array(
                'key' => 'wholesale_customer_wholesale_price',
                'value' => ($this->param["wholesale_customer_wholesale_price"]!=0 ) ? $this->param["wholesale_customer_wholesale_price"] : null,
            )
        );
        if(isset($this->param['description']) and $this->param['description']!=null){
            $data=[
                "regular_price"=>(string)$this->param['regular_price'],     //problem on update all need to convert to string
                "sale_price"=>((int)$this->param["sale_price"]==0) ? "":(string)$this->param['sale_price'],           //problem on update all need to convert to string
                "manage_stock" => true,
                "stock_quantity"=>(int)$this->param['stock_quantity'],
                "description"=>(string)$this->param['description'],
                //'wholesale_customer_wholesale_price' => $this->param['wholesale_customer_wholesale_price'],

                "meta_data"=>$meta,
            ];
        }
        else{
            $data=[
                "regular_price"=>(string)$this->param['regular_price'],     //problem on update all need to convert to string
                "sale_price"=>((int)$this->param["sale_price"]==0) ? "":(string)$this->param['sale_price'],           //problem on update all need to convert to string
                "manage_stock" => true,
                "stock_quantity"=>(int)$this->param['stock_quantity'],
                //'wholesale_customer_wholesale_price' => $this->param['wholesale_customer_wholesale_price'],

                "meta_data"=>$meta,
            ];
        }
        Log::info('param sended for variations wc product id '. $this->param['id']." and variation_id ".$this->param['variation_id']." for user ".$this->user->siteUrl);
        Log::info('param sended ');
        $data = json_encode($data);
        log::info($data);
        //$data = json_encode($data);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/'. $this->param['id']."/variations/".$this->param['variation_id'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpcode !=201 and $httpcode !=200){

            log::info($data);
            log::alert("get http code ".$httpcode." for user ".$this->user->id);
            log::alert("url request is ". $this->user->siteUrl.'/wp-json/wc/v3/products/'. $this->param['id']."/variations/".$this->param['variation_id']);
            log::alert($response);

        }
       // log::info(json_encode($response));

        curl_close($curl);
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
        log::warning("failed to update product variation user");
        log::warning("for website id : ".$this->user->siteUrl);
        $this->delete();
    }

}
