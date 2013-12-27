<?php

namespace CurrencyConverter\CurrencyConverterBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use CurrencyConverter\CurrencyConverterBundle\Entity\Currency;
use CurrencyConverter\CurrencyConverterBundle\Entity\Country;
use CurrencyConverter\CurrencyConverterBundle\Utility\simple_html_dom;

class CurrencyFixtures implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        
        
        $var = __DIR__.'\simple_html_dom.php';
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
            $currency->setCurrency($item['currency']);
            $currency->setCode($item['code']);
            if(isset($final_symbol)){
              if(strlen($final_symbol) > 0){
                  $currency->setSymbol($final_symbol);
              }
            }
            $manager->persist($currency);        
            $manager->flush();
            
            $country = new Country();
            $country->setCurrencyId($currency->getId());
            $country->setCountry($item['country']);
            $manager->persist($country);        
            $manager->flush();
        }
        
       
    }
    
    
}