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

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use CurrencyConverter\CurrencyConverterBundle\Controller\BaseControllerResolver;
use Symfony\Component\HttpFoundation\Response;

class BaseController extends Controller
{
    
    protected $entity_manager; 
    protected $country_repository; //exposes the CountryRepository object
    protected $currency_repository; //exposes the CurrencyRepository object
    
    public function __construct(){        
        $cr = new BaseControllerResolver();
        print_r(BaseControllerResolver::getContainer());die();
        $this->setContainer(BaseControllerResolver::getContainer());
        
        $this->entity_manager = $this->getDoctrine()
                                ->getManager();
        $this->country_repository = $this->entity_manager
                                    ->getRepository('CurrencyConverterCurrencyConverterBundle:Country');
        $this->currency_repository = $this->entity_manager
                                     ->getRepository('CurrencyConverterCurrencyConverterBundle:Currency');
                                     
        
    }
    
    
   /**
    * Gets plain response.
    * 
    * @param String Content of response
    * @param int    Response Code
    * @param array  Array of Headers
    * 
    * @return Response
    */
    protected function getResponse($strContent = "", $numStatus = 200, $arrHeaders = array()) {
            $oRet = new Response($strContent, $numStatus, $arrHeaders);
            $oDate = new \DateTime();
            $oDate->modify('-600 seconds');
            $oRet->setExpires($oDate);
            return $oRet;
    }
    
    /**
    * Returns a Not Found Error
    * 
    * @param String $strMessage Optional Message 
    * 
    * @return Response
    */
    protected function getNotFoundResponse($strMessage = "NOT FOUND") {
            $oResponse = $this->getResponse(json_encode(
                    array(
                            "error" => array(
                                    "type" => 'notfound',
                                    "message" => $strMessage 
                            )
                    )
            ));
            return $oResponse;
    }
    
   /**
    * Returns an Access Denied Error
    * 
    * @param String $strMessage Optional Message 
    * 
    * @return Response
    */
    protected function getAccessDenied($strMessage = "ACCESS DENIED") {
            $oResponse = $this->getResponse(json_encode(
                    array(
                            "error" => array(
                                    "type" => 'accessdenied',
                                    "message" => $strMessage 
                            )
                    )
            ));
            return $oResponse;
    }
    
   /**
    * Returns an Unknown Error
    * 
    * @param String $strMessage Optional Message 
    * 
    * @return Response
    */
    protected function getUnknownError($strMessage = "UNKNOWN ERROR") {
            $oResponse = $this->getResponse(json_encode(
                    array(
                            "error" => array(
                                    "type" => 'unknownerror',
                                    "message" => $strMessage 
                            )
                    )
            ));
            return $oResponse;
    }
    
   /**
    * Returns Standard Error Response
    *
    * @deprecated
    *
    * @param String strErrorType Error type
    * @param String strMessage   Optional Message
    * 
    * @return Response
    */
    protected function getErrorResponse($strErrorType, $strMessage = "") {
            $oResponse = $this->getResponse(json_encode(
                    array(
                            "error" => array(
                                    "type" => $strErrorType,
                                    "message" => $strMessage 
                            )
                    )
            ));
            return $oResponse;
    }
    
   /**
    * Returns Standard Success Response
    *
    * @deprecated
    *
    * @param String strMessage   Optional Message
    * @param array  arrData      Optional Array of data
    * 
    * @return Response
    */
    protected function getSuccessResponse($strMessage = "", $arrData = null) {
            $arrResponse = array(
                            "success" => array(
                                    "message" => $strMessage 
                            )
                    );
            $arrResponse['data'] = $arrData;
            $oResponse = $this->getResponse(json_encode(
                    $arrResponse
            ));
            return $oResponse;
    }


}
