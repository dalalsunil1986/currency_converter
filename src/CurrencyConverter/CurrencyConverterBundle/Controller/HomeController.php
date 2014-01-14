<?php

namespace CurrencyConverter\CurrencyConverterBundle\Controller;

use CurrencyConverter\CurrencyConverterBundle\Entity\Contact;
use CurrencyConverter\CurrencyConverterBundle\Form\ContactType;
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
    
    
    /**
     * Loads the contact page
     *
     */
    public function contactAction()
    {
       
        $contact = new Contact();      
        $form = $this->createForm(new ContactType(), $contact);
       
        $request = $this->getRequest();
        if ($request->getMethod() == 'POST') {
            
            $form->bind($request);
            
            if ($form->isValid()) {
                
                $message = \Swift_Message::newInstance() //call the SwiftMailer Class
                ->setSubject('Contact enquiry from currency converter app.')
                ->setFrom('enquiries@currencyconverter.com')
                ->setTo($this->container->getParameter('currency_converter.emails.contact_email'))
                ->setBody($this->renderView('CurrencyConverterCurrencyConverterBundle:Home:contactEmail.txt.twig', array('contact' => $contact)));
                $this->get('mailer')->send($message);
                
                $this->get('session')->getFlashBag()->set('converter-notice', 'Your contact enquiry was successfully sent. Thank you!');

                return $this->redirect($this->generateUrl('currency_converter_currency_converter_contact'));
            }
            
        }
        
        return $this->render('CurrencyConverterCurrencyConverterBundle:Home:contact.html.twig', array(
                 'form' => $form->createView()
        ));
       
    }
    
}
