<?php

namespace CurrencyConverter\CurrencyConverterBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use CurrencyConverter\CurrencyConverterBundle\Entity\Currency;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use CurrencyConverter\CurrencyConverterBundle\Entity\Country;

/**
 * Running this on the CLI will fill up the database with necessary
 * data for the application. Run the command "php app/console doctrine:fixtures:load
 * 
 */

class CurrencyFixtures implements FixtureInterface, ContainerAwareInterface
{
    
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
    
    public function load(ObjectManager $manager)
    {
       
        $rates = $this->callRates(); //grab all the rates
        
        $var = __DIR__.'/simple_html_dom.php';
        include_once($var);
        $html = file_get_html('http://www.xe.com/iso4217.php');
        
       
        $container = array();
        $site = 'http://www.xe.com';
        
        foreach ($html->find('table') as $table) {
            if($table->id == 'currencyTable'){
               
                foreach ($table->find('tr') as $tr) {
                   
                    $rows = array();
                    $new_array = array();
                    
                    foreach ($tr->find('td') as $td) {
                         $rows[]=$td;
                    }
                    
                    if(!isset($rows[0]) || !isset($rows[1]))
                       continue;
                     
                    $first = $rows[0];
                    $second = $rows[1];
                    
                    if($second->innertext && $first->find('a',0)){
                       
                       $last_position = strrpos($second->innertext,' ') + 1;
                       $length = strlen ($second->innertext);
                       $length_of_currency = $length - $last_position;
                       $currency = trim(substr($second->innertext, $last_position, $length_of_currency));
                       $country = trim(substr($second->innertext,0,$last_position - 1));
                       
                       $new_array['currency'] = $currency;
                       $new_array['country'] = $country;
                       $new_array['code'] = $first->find('a',0)->innertext;
                       $new_array['link'] = $site.$first->find('a',0)->href;
                      
                       $container[] = $new_array;
                    }
                 
                }
            }
           
        }
       
        foreach($container as $item){          
            
            $next_html = file_get_html($item['link']);                       
            $stats_div = $next_html->find('div.currencystats',0);
            
            $inner_row = array();
            foreach($stats_div->find('p') as $p){
              $inner_row[] = $p;
            }
            
            $first_term_to_find = '<strong>Symbol:</strong>';
            $first_term_to_find_length = strlen($first_term_to_find);
            
            $second_term_to_find = '<strong>';
            $second_term_to_find_length  = strlen($second_term_to_find);
            
            $extracted_symbol = null;
            
            $final_symbol = null;
            
            if(isset($inner_row[1])){
                
                $term = $inner_row[1];
                $text_html = $term->innertext;
                
                if(strlen($text_html) > $first_term_to_find_length && strpos($text_html,$first_term_to_find) != false){
                    
                    $symbol = trim($text_html);
                    $symbol = substr($symbol, $first_term_to_find_length, strlen($text_html) - $first_term_to_find_length);
                   
                    if(strpos($symbol,$second_term_to_find) != false){
                        $start_index = strpos($symbol,$second_term_to_find) + 1;
                        $length = strlen($symbol);
                        $length_of_string_to_cut = $length - $start_index;
                        $last_term = trim(substr($symbol,$start_index,$length_of_string_to_cut));
                        $extracted_symbol = trim(str_replace($last_term, "", $symbol));                        
                    }
                    else{
                        $extracted_symbol = trim($symbol);
                    }
                    
                    
                }
                
                $clean_symbol = $extracted_symbol;
                
                if(strlen($clean_symbol) > 0){
                   $clean_symbol = trim($clean_symbol);
                   if(strpos($clean_symbol,'<') != false){
                      $clean_symbol = trim(str_replace('<', "", $clean_symbol));
                      if(strpos($clean_symbol,'img') != false){
                         $clean_symbol = '';
                      }
                   }                   
                }
                
                if(strpos($clean_symbol,'img') != false){
                         $clean_symbol = '';
                }
                
                $final_symbol = $clean_symbol;
            }
            
           
            $currency = new Currency();
	   
            $currency_name = trim($this->cleanString($item['currency']));
            $currency_code = trim($this->cleanString($item['code']));
            $currency_rate = 0;
            if(isset($rates[$currency_code])){
               $currency_rate = $rates[$currency_code];
            }
            
            $currency->setCurrency($currency_name);
            $currency->setCode($currency_code);
            $currency->setRate($currency_rate);
            
            if(isset($final_symbol)){
              if(strlen($final_symbol) > 0){
                  $currency->setSymbol($final_symbol);
              }
            }
            
            $manager->persist($currency);
            $manager->flush();
            
            $country = new Country();            
            $country->setCurrencyId($currency->getId());
            $country->setCurrency($currency);           
            $country_name = trim($this->cleanString($item['country']));
	    $country->setCountry($country_name);
           
            $manager->persist($country);
            $manager->flush();
            
        }
        
        
       
    }
    
    /**
     * Call the rate for each currency
     *
     * @return array $rate_container
     **/
    public function callRates(){
        
        $file = 'latest.json';
        $appId = $this->container->getParameter('conversion_api_key');
        
        header('Content-Type: application/json');
        $json = file_get_contents("http://openexchangerates.org/api/{$file}?app_id={$appId}");
      
        $obj = json_decode($json);
        $rate_container = array();
        
        if(isset($obj->{'rates'})){
            foreach($obj->{'rates'} as $key=>$rate){
                $rate_container[$key]=$rate;
            }
        }
        
        return $rate_container;
    }
    
    
    private function cleanString($string){
        return preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($string)))));
    }
    
    
}