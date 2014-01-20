<?php
namespace CurrencyConverter\CurrencyConverterBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use CurrencyConverter\CurrencyConverterBundle\Api\Encryption;

/**
 * This will generate an encrypted key using the keyword/value provided in your parameters.yml encryption_key.
 * The output key will be used as a querystring in the api url for updating current rates.
 * Run the command with this "php app/console key:generate"
 * 
 * @author Joel Capillo <hunyoboy@gmail.com>
 */

class KeyGeneratorCommand extends ContainerAwareCommand 
{   
    protected function configure()
    {
       $this
            ->setName('key:generate')
            ->setDescription('Generate encrypted key to be used as a security key.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ekey = $this->getContainer()->getParameter('encryption_key');
        $keyword = $this->getContainer()->getParameter('secret_Key_word'); //the word to be encrypted
        
        if (!$keyword || strlen($keyword) < 3) {
           throw new Exception('Please enter a valid secret keyword minimum 3 characters in your parameters.ini.');
        } 
        
        $encryption = new Encryption($ekey);
        $encoded_key = $encryption->encode($keyword);
        
        print_r("This is your encrypted secret keyword. Use this as a querystring on update rates url"."\n");
        print_r("$encoded_key"."\n");
        

    }
   
}