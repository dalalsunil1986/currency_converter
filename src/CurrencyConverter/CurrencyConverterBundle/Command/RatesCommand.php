<?php
namespace CurrencyConverter\CurrencyConverterBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use CurrencyConverter\CurrencyConverterBundle\Api\OpenExchange;

/**
 * Run by cron every day to update rate currencies from Open Exchange Rate(https://openexchangerates.org/)
 * Run the command with this "php app/console update"
 * 
 * @author Joel Capillo <hunyoboy@gmail.com>
 */

class RatesCommand extends ContainerAwareCommand 
{   
    protected function configure()
    {
        $this->setName('update')
            ->setDescription('Update currency rates.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $appId = $this->getContainer()->getParameter('conversion_api_key');
        $open_exchange = new OpenExchange($appId);
        $current_rates = $open_exchange->callRates($appId); //get the fresh/newest currency rates
        $conn = $this->getContainer()->get('database_connection');        
        $output = $open_exchange->updateCurrencyRates($conn,$current_rates); //update rates
        
        print_r($output);        
    }
   
}