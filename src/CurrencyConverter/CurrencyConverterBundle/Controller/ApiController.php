<?php
/**
 * API Controller - houses the exposed application API
 *
 * @author Joel Capillo <hunyoboy@gmail.com>
 *
 */
namespace CurrencyConverter\CurrencyConverterBundle\Controller;

use CurrencyConverter\CurrencyConverterBundle\Controller\ApiBaseController;
use CurrencyConverter\CurrencyConverterBundle\Api\Encryption;
use CurrencyConverter\CurrencyConverterBundle\Api\OpenExchange;

class ApiController extends ApiBaseController
{
    /**
     * Called to fill the dropdown list on the home page
     *
     */
    public function loadAction(){
       
        $result = array();
        $data = array();
        
        try{
            
            $countries = $this->country_repository
                ->getAllCountries();
                
            if(!isset($countries) || empty($countries))
               throw new \Exception('No data found');
                
            foreach($countries as $country){
                
                $symbol = $country->getCurrency()->getSymbol();
                $currency = $country->getCurrency()->getCurrency();
                $country_name = $country->getCountry();
                $currency_code = $country->getCurrency()->getCode();
                $rate = $country->getCurrency()->getRate();
                
                $data['currency_id'] = $country->getCurrency()->getId();            
                $data['currency_code'] = $currency_code;
                $data['currency_symbol'] = $symbol;
                $data['currency'] = $currency;
                $data['country'] = $country_name;
                $data['rate'] = $rate;
                
                if(!$currency_code || $currency_code == null){
                    $currency_code = '';
                }
                else{
                    $currency_code = '('.$currency_code.')';
                }
                
                $data['currency_info'] = $currency_code.' '.$currency.' '.$country_name;              
                $result[]= $data;
                
            }
            
            $response = $this->getSuccessResponse('200 OK',$result);            
            return $response;
        
        }
        catch(\Exception $ex){
            
            $response = $this->getErrorResponse('500',$ex->getMessage());
            return $response;
        
        }
        
        
    }
    
    /**
     * This is another option for updating rates by calling this action from cronjob.
     * Important thing here is the encrypted key to make sure that only authorized calls to this action will be allowed.
     * 
     * 
     * @param string $encrypted_key the generated encrypted key using the console command 
     * @throws \Exception
     */
    public function updateRatesAction($encrypted_key){
       $ekey = $this->container->getParameter('encryption_key');
       $keyword = $this->container->getParameter('secret_Key_word'); //the word to be encrypted 
       $encryption = new Encryption($ekey);
       $decoded_key = $encryption->decode($encrypted_key);       
       if($decoded_key == $keyword){
            $appId = $this->container->getParameter('conversion_api_key');
            $open_exchange = new OpenExchange($appId);
            $current_rates = $open_exchange->callRates($appId); //get the fresh/newest currency rates
            $conn = $this->container->get('database_connection');        
            $output = $open_exchange->updateCurrencyRates($conn,$current_rates); //update rates
            die($output);    
       }
       else
         throw new \Exception ('Not authorized.', 503);  
    }
    
    
    
   
}
