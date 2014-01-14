<?php
/**
 * API Controller - houses the exposed application API
 *
 * @author Joel Capillo <hunyoboy@gmail.com>
 *
 */
namespace CurrencyConverter\CurrencyConverterBundle\Controller;

use CurrencyConverter\CurrencyConverterBundle\Controller\BaseController;

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
                
                $data['currency_id'] = $country->getCurrency()->getId();            
                $data['currency_code'] = $currency_code;
                $data['currency_symbol'] = $symbol;
                $data['currency'] = $currency;
                $data['country'] = $country_name;
                
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
   
}
