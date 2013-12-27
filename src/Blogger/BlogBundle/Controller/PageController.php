<?php
namespace Blogger\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Blogger\BlogBundle\Entity\Enquiry;
use Blogger\BlogBundle\Form\EnquiryType;

class PageController extends Controller
{
    public function indexAction()
    {
        return $this->render('BloggerBlogBundle:Page:index.html.twig');
    }
    
    public function aboutAction()
    {
        return $this->render('BloggerBlogBundle:Page:about.html.twig');
    }
    
    public function contactAction()
    {
        $enquiry = new Enquiry();
        $form = $this->createForm(new EnquiryType(), $enquiry);
        
        $request = $this->getRequest();
        if ($request->getMethod() == 'POST') {
            
            $form->bind($request);        
           
            if ($form->isValid()) {
                //sent an email
                $message = \Swift_Message::newInstance()
                ->setSubject('Contact enquiry from symblog')
                ->setFrom('hunyoboy@gmail.com')
                ->setTo($this->container->getParameter('blogger_blog.emails.contact_email'))
                ->setBody($this->renderView('BloggerBlogBundle:Page:contactEmail.txt.twig', array('enquiry' => $enquiry)));
                $this->get('mailer')->send($message);
                
                
                $this->get('session')->getFlashBag()->add('blogger-notice', 'We sent you an email.');  
    
                return $this->redirect($this->generateUrl('BloggerBlogBundle_contact'));
            }
           
        }
        
        return $this->render('BloggerBlogBundle:Page:contact.html.twig', array(
              'form' => $form->createView()
        ));
    }
}

?>