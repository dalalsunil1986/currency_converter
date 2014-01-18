<?php

namespace CurrencyConverter\CurrencyConverterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class HomeController extends Controller
{
   
    /**
     * Loads the homepage
     *
     */
    public function indexAction()
    {      
       return $this->render('CurrencyConverterCurrencyConverterBundle:Home:index.html.twig');
    }
    
    
}
