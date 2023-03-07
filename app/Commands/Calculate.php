<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use LaravelZero\Framework\Commands\Command;

class Calculate extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'calculate { name : Enter The CSV File Name }';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Calculates The Commission Fees';

    protected $files;
    private $deposit_percentage = 0.03;
    private $exchangeRates;
    private $transactions = [];


    public function __construct(Filesystem $file)
    {
        parent::__construct();

        //Assign to the variable scope
        //
        $this->files = $file;
        $this->exchangeRates = json_decode(file_get_contents('https://developers.paysera.com/tasks/api/currency-exchange-rates'));
        //dd($exchangeRates);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $name = $this->argument('name');
        //dd($name);

        $commission_fees = [];

        if (($open = fopen(storage_path() . "/app/".$name, "r")) !== FALSE) {

            while (($data = fgetcsv($open, 1000, ",")) !== FALSE) {
                $this->transactions[] = $data;
            }
            fclose($open);
        }
        //check if transactions are not empty
        if(!empty($this->transactions)){

            $commission_fees = $this->calculateTransactions($this->transactions);
            echo "<pre>";
            print_r($commission_fees);
        }else {
            $this->info("File Is Empty");
        }
    }
    private function calculateTransactions($data){

        $result = [];
        foreach ($data as $single){

            $operationType = $single[3];
            switch ($operationType){
                case('deposit'):
                    $result[] = $this->calculateDepositCommission($single);
                    break;
                case('withdraw'):
                    $result[] = $this->calculateWithdrawCommission($single);
                    break;
            }
            //$result[] = $this->deposit_percentage;
        }
        //echo "<pre>";
        //print_r($result);
        return $result;
    }
    private function calculateDepositCommission($single)
    {
        $amount = $single[4];
        return round((($this->deposit_percentage/100)*$amount),2);

    }

    private function calculateWithdrawCommission($single)
    {

        $userType = $single[2];
        $amount = $single[4];
        //dd($amount);
        $userId = $single[1];

        switch ($userType){
            case('private'):
                //$totalAmount[$userId] = $totalAmount[$userId] + $amount;
                return $this->calculatePrivateClientsWithdrawCommission($single);
                break;
            case('business'):
                return round(((0.5/100)*$amount),2);
                break;
        }

    }

    private function calculatePrivateClientsWithdrawCommission($single)
    {
        $rate = 1;
        $amount = $single[4];
        $currency = $single[5];
        $userId = $single[1];
        if($currency !== 'EUR'){
            //dd($currency);
            $rate = $this->exchangeRates->rates->$currency;
            //dd($rate);

        }

        //dd($rate);
        $exchangedAmount = round(($amount / $rate),2);
        //dd($exchangedAmount);

        if($exchangedAmount > 1000.00){
            $extra = $exchangedAmount - 1000.00 ;
            $commission = round(((0.3/100) * $extra),2);
            //convert the commission to the original currency amount

            if($commission <= 0){
                return $commission;

            }else {
                return round(($commission * $rate),2);
            }

        }else {

            $commission =  round(((0.3/100)*$exchangedAmount),2);

            if($commission <= 0){
                return $commission;

            }else {
                return round(($commission * $rate),2);
            }
        }

    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
