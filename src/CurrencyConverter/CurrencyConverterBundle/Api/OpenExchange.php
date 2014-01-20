<?php
namespace CurrencyConverter\CurrencyConverterBundle\Api;

/**
 * Class to interact with open exchange api(https://openexchangerates.org/)
 * 
 * @author Joel Capillo <hunyoboy@gmail.com>
 */

class OpenExchange {
    
    protected $app_id;
    
    
    public function __construct($app_id){
        $this->app_id = $app_id;
    }
    
    /**
     * Returns the newest rate of world currencies in dollar base
     *
     * @return array $rate_container key-value array where key is the currency code and the value is the newest rate 
     */
    public function callRates(){
        
        $app_id = $this->app_id;
        $file = 'latest.json';  
        header('Content-Type: application/json');
        $json = file_get_contents("http://openexchangerates.org/api/{$file}?app_id={$app_id}");
      
        $obj = json_decode($json);
        $rate_container = array();
        
        if(isset($obj->{'rates'})){
            foreach($obj->{'rates'} as $key=>$rate){
                $rate_container[$key]=$rate;
            }
        }
        
        return $rate_container;
    }
    
    /**
     * Updates the database for the current pulled rates
     *
     * @param mixed $conn the dbal object database connection
     * @param array $current_rates the current currency rates 
     *
     */
    public function updateCurrencyRates($conn,$current_rates){
        $conn->beginTransaction();        
        try{
            
            foreach($current_rates as $currency_code=>$rate){
                $code = trim(strtolower($currency_code));
                $count = $conn->executeUpdate('UPDATE currencies SET rate = ? WHERE TRIM(LOWER(code)) = ?', array($rate, $code));
                if($count > 0){
                   echo 'Updated:'.$currency_code.' = '.$rate."\n";
                }
                else{
                    echo  'No update:'.$currency_code."\n"; 
                }
            }       
            
            $conn->commit();
            
            return "Completed update process.";
            
        }catch(\Exception $e){         
          print_r($e);         
        } 
    }
    
    
}