<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class UpdateMultiProductsUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $params;
    public $flag;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$params,$flag)
    {
        Log::info(' queue update multi product start');
        $this->user=$user;
        $this->params=$params;
        $this->flag=$flag;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('   for flag ' . $this->flag);

        $products=[];
        $failer=[];

        $curl = curl_init();
        foreach($this->params as $param){

            $meta = array(
                (object)array(
                    'key' => 'wholesale_customer_wholesale_price',
                    'value' => $param["wholesale_customer_wholesale_price"]
                )
            );
            $products[]=(object)[
                "id" => (string)$param['id'],
                "regular_price"=>(string)$param['regular_price'],     //problem on update all need to convert to string
                "sale_price"=>((int)$param["sale_price"]==0) ? "" :(string)$param['sale_price'],           //problem on update all need to convert to string
                "manage_stock" => true,
                "stock_quantity"=>(int)$param['stock_quantity'],
                //'wholesale_customer_wholesale_price' => $param['wholesale_customer_wholesale_price'],
                "name"=>$param['name'],
                "meta_data"=>$meta,
            ];

        }

        $data=[
            "update"=>$products
        ];
        Log::info('param sended ');
        // log::info($data);
        $data = json_encode($data);
        //$data = json_encode($data);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->user->siteUrl.'/wp-json/wc/v3/products/batch',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERAGENT => 'Holoo',
            CURLOPT_USERPWD => $this->user->consumerKey. ":" . $this->user->consumerSecret,
            CURLOPT_HTTPHEADER => array(
              //'Content-Type: multipart/form-data',
              'Content-Type: application/json',
            ),
        ));

        $responsess = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $responses = json_decode($responsess);
        // check if response has $responses->update exist
        if (!isset($responses->update)) {
            log::warning('update multi product has error return is '.$responsess);
            log::warning("get http code ".$httpcode." for multi product code for user: ".$this->user->id);
            log::warning($data);
            return;
        }


        foreach($responses->update as $response){

            if(isset($response->error)){
                log::warning('update multi product has error return is ',json_encode($response));
                log::warning("get http code ".$httpcode." for ".$response->id." for user: ".$this->user->id);

            }
            else{
                log::info('update multi product succsessfuly for wc product id '.$response->id);
            }
        }
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
        log::warning("failed to update product user");
        log::warning("for website id : ".$this->user->siteUrl);
        $this->delete();
    }
}
