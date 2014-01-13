<?php
namespace CurrencyConverter\CurrencyConverterBundle\Kernel;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\AsseticBundle\AsseticBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle;
use CurrencyConverter\CurrencyConverterBundle\CurrencyConverterCurrencyConverterBundle;

class ApiKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(            
            new DoctrineBundle(),           
            new FrameworkBundle(),            
            new SecurityBundle(),           
            new CurrencyConverterCurrencyConverterBundle()
        );
        
        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
       $loader->load(__DIR__.'/../../../../app/config/api.yml');
    }
}
