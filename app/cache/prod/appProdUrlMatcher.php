<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * appProdUrlMatcher
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class appProdUrlMatcher extends Symfony\Bundle\FrameworkBundle\Routing\RedirectableUrlMatcher
{
    /**
     * Constructor.
     */
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = array();
        $pathinfo = rawurldecode($pathinfo);
        $context = $this->context;
        $request = $this->request;

        // currency_converter_currency_converter_homepage
        if (rtrim($pathinfo, '/') === '') {
            if (substr($pathinfo, -1) !== '/') {
                return $this->redirect($pathinfo.'/', 'currency_converter_currency_converter_homepage');
            }

            return array (  '_controller' => 'CurrencyConverter\\CurrencyConverterBundle\\Controller\\HomeController::indexAction',  '_route' => 'currency_converter_currency_converter_homepage',);
        }

        // currency_converter_currency_converter_contact
        if ($pathinfo === '/contact') {
            if (!in_array($this->context->getMethod(), array('GET', 'POST', 'HEAD'))) {
                $allow = array_merge($allow, array('GET', 'POST', 'HEAD'));
                goto not_currency_converter_currency_converter_contact;
            }

            return array (  '_controller' => 'CurrencyConverter\\CurrencyConverterBundle\\Controller\\HomeController::contactAction',  '_route' => 'currency_converter_currency_converter_contact',);
        }
        not_currency_converter_currency_converter_contact:

        // currency_converter_currency_converter_api_load
        if ($pathinfo === '/api/load') {
            if (!in_array($this->context->getMethod(), array('GET', 'HEAD'))) {
                $allow = array_merge($allow, array('GET', 'HEAD'));
                goto not_currency_converter_currency_converter_api_load;
            }

            return array (  '_controller' => 'CurrencyConverter\\CurrencyConverterBundle\\Controller\\ApiController::loadAction',  '_route' => 'currency_converter_currency_converter_api_load',);
        }
        not_currency_converter_currency_converter_api_load:

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
