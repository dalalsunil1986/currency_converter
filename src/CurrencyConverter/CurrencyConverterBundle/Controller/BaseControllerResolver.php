<?php

/**
 * Controller that extends to Symfony controller and
 * let us access the container object during controller's
 * __construct function
 *
 * @author Joel Capillo <hunyoboy@gmail.com>
 *
 */
namespace CurrencyConverter\CurrencyConverterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;

/**
 * ControllerResolver.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class BaseControllerResolver extends ControllerResolver
{
    protected static $container_carrier;
    
     /**
     * Constructor.
     *
     * @param ContainerInterface   $container A ContainerInterface instance
     * @param ControllerNameParser $parser    A ControllerNameParser instance
     * @param LoggerInterface      $logger    A LoggerInterface instance
     */
    public function __construct()
    {
        
        self::$container_carrier = $this->container;
    }
    
    
    public static function getContainer(){
        return self::$container_carrier;
    }




}

?>