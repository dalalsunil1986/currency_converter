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
                $data['currency_id'] = $country->getCurrency()->getId();            
                $data['currency_code'] = $country->getCurrency()->getCode();
                $data['currency_symbol'] = $country->getCurrency()->getSymbol();
                $data['currency'] = $country->getCurrency()->getCurrency();
                $data['country'] = $country->getCountry();
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
