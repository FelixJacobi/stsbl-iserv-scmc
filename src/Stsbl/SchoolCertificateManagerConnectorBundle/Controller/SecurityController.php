<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/SecurityController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use IServ\CoreBundle\Traits\LoggerTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\LoggerInitalizationTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\MasterPasswordTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * School Certificate Manager Connector Login/Logout
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("scmc", schemes="https")
 * @Security("requires_channel: https")
 */
class SecurityController extends PageController {
    use MasterPasswordTrait, SecurityTrait, LoggerTrait, LoggerInitalizationTrait;
    
    /**
     * Displays login form
     * 
     * @param Request $request
     * @Route("/login", name="scmc_login")
     * @Template("StsblSchoolCertificateManagerConnectorBundle:Security:login.html.twig")
     * @Security("token.getAttribute('scmc_authentificated') !== true")
     */
    public function loginAction(Request $request)
    {
        if (!$this->isManager()) {
            throw $this->createAccessDeniedException("You don't have the privileges to access the connector.");
        }
        
        if ($this->get('stsbl.scmc.service.session')->isAuthentificated()) {
            // go to index
            return $this->redirect($this->generateUrl('scmc_index'));
        }
        
        $error = '';
        $form = $this->getLoginForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if(!$form->isValid() or !$form->isSubmitted()) {
                $this->get('iserv.flash')->error(_('Invalid request'));
            }
        
            $data = $form->getData();
            $this->initalizeLogger();
        
            if (empty($data['masterpassword'])) {
                $this->log('Login im Zeugnisverwaltungsbereich: Falsches Masterpasswort');
                $this->get('iserv.flash')->error(_('Please enter the master password and try it again.'));
                goto render;
            }
            
            $ret = $this->get('stsbl.scmc.service.session')->openSession($data['masterpassword']);
            
            if (!$ret) {
                $this->log('Login im Zeugnisverwaltungsbereich: Falsches Masterpasswort');
                $error = _('The master password is wrong.');
                goto render;
            } else if ($ret) {
                $this->log('Login im Zeugnisverwaltungsbreich erfolgreich');            
                $this->get('iserv.flash')->success(_('You have logged in successfully in the Certificate Management Section.'));
                
                return $this->redirect($this->generateUrl('scmc_index'));
            } 
            
            // if we are until here not redirected, expect an error.
            $error = _('Unknown error. Please try again.');
        }
        
        render:
        // parameters
        $view = $form->createView();
        $emptyMasterPassword = $this->isMasterPasswordEmpty();
        
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('scmc_forward'));
        
        return array('login_form' => $view, 'emptyMasterPassword' => $emptyMasterPassword, 'error' => $error);
    }
    
    /**
     * Logouts user from current session
     * 
     * @param Request $request
     * @Route("/logout", name="scmc_logout")
     * @Security("token.hasAttribute('scmc_authentificated') and token.getAttribute('scmc_authentificated') == true")
     */
    public function logoutAction(Request $request)
    {
        if (!$this->isManager()) {
            throw $this->createAccessDeniedException("You don't have the privileges to access the connector.");
        }
        
        $this->get('stsbl.scmc.service.session')->closeSession();
            
        $this->initalizeLogger();
        $this->log('Logout aus dem Zeugnisverwaltungsbereich');
        $this->get('iserv.flash')->success(_('You have logged out successfully from the Certificate Management Section.'));
    
        return $this->redirect($this->generateUrl('scmc_forward'));
    }
    
    /**
     * Creates form to login with masterpassword
     * 
     * @return \Symfony\Component\Form\Form
     */
    private function getLoginForm()
    {
        $builder = $this->createFormBuilder();
        
        $builder
            ->add('masterpassword', PasswordType::class, array(
                'label' => false,
                'required' => true,
                'attr' => array(
                    'placeholder' => _('Master password'),
                    'autofocus' => 'autofocus'
                    )
                )
            )
            ->add('submit', SubmitType::class, array(
                'label' => _('Login'),
                'buttonClass' => 'btn-primary'  
                )
            )
        ;
        
        return $builder->getForm();
    }
}
