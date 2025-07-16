<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateProductFindStep2All;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
class UpdateProductFind implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $param;
    protected $category;
    protected $config;
    public $flag;
    public $failOnTimeout = true;
    protected $product;
    protected $capacity_per_page=100;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user,$category,$config,$flag)
    {
        Log::info(' queue update product find start');
        $this->user=$user;
        $this->config=$config;
        $this->category=$category;
        $this->flag=$flag;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user_id=$this->user->id;
        Log::info("update for user id $user_id");
        log::info ($this->user->siteUrl);
        $queue_delicate =$this->checkUserInString($this->user->queue_server);
        $this->capacity_per_page = ($queue_delicate) ? 10 : 100;
        FetchPrestaShopProductsJob::dispatch((object)["queue_server"=>$this->user->queue_server,"id"=>$this->user->id,"siteUrl"=>$this->user->siteUrl,"serial"=>$this->user->serial,"apiKey"=>$this->user->apiKey,"holooDatabaseName"=>$this->user->holooDatabaseName,"consumerKey"=>$this->user->consumerKey,"consumerSecret"=>$this->user->consumerSecret,"cloudTokenExDate"=>$this->user->cloudTokenExDate,"cloudToken"=>$this->user->cloudToken, "holo_unit"=>$this->user->holo_unit, "plugin_unit"=>$this->user->plugin_unit,"user_traffic"=>$this->user->user_traffic,"poshak"=>$this->user->poshak],[],$this->config,1,[],[],[])->onConnection($this->user->queue_server)->onQueue("default");

    }

    /**
     * Handle the failing job.
     *
     *
     * @return void
     */
    public function failed()
    {
        log::warning("failed to update product find");
        log::warning("for website id : ".$this->user->siteUrl);
        $this->delete();
    }
    public function checkUserInString($inputString) {
        // جستجو برای "user" در استرینگ
        $position = strpos($inputString, 'user');

        // اگر "user" در استرینگ وجود داشته باشد، مقدار true را برگردان
        if ($position !== false) {
            return true;
        } else {
            // در غیر این صورت، مقدار false را برگردان
            return false;
        }
    }
}
