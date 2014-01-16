<?php
/**
 * API Controller - houses the exposed application API
 *
 * @author Joel Capillo <hunyoboy@gmail.com>
 *
 */
namespace CurrencyConverter\CurrencyConverterBundle\Controller;

use CurrencyConverter\CurrencyConverterBundle\Controller\ApiBaseController;

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
    
    //run twice a day by cron
    public function conversionRatesAction(){
        
        $file = 'latest.json';
        $appId = $this->container->getParameter('conversion_api_key');
        
        $ch = curl_init("http://openexchangerates.org/api/{$file}?app_id={$appId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $json = curl_exec($ch);
        curl_close($ch);
        
        $obj = json_decode($json);
        $rate_container = array();
        
        if(isset($obj->{'rates'})){
            foreach($obj->{'rates'} as $key=>$rate){
                $rate_container[$key]=$rate;
            }
        }
        
        //do database insertion here
        die();
        
    }
    
    
    
    
   
}
