<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;



class MirrorWebHook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $Dbname;
    protected $Table;
    protected $MsgType;
    protected $MsgValue;
    protected $Message;
    public $flag;
    public $timeout = 100*60;
    public $failOnTimeout = true;
    protected $product;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($Dbname,$Table,$MsgType,$MsgValue,$Message)
    {
        Log::info(' queue mirror start for '.$Dbname);
        $this->Dbname=$Dbname;
        $this->Table=$Table;
        $this->MsgType=$MsgType;
        $this->MsgValue=$MsgValue;
        $this->Message=$Message;

    }

    /**
     * Execute the job.
     *
     * @return void
     */


    public function handle()
    {
        $curl = curl_init();
        $data = json_encode(array(
            "Dbname"=> $this->Dbname,
            "Table"=>$this->Table,
            "MsgType"=> $this->MsgType,
            "MsgValue"=> $this->MsgValue,
            "MsgError"=> null,
            "Message"=> $this->Message
        ));
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://www.laravel.nilaserver.com/api/webhook',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>$data,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
          ),
        ));

        $response = curl_exec($curl);

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
        log::warning("failed to mirror webhook");
        $this->delete();
    }
}
