<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/SecurityController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use IServ\CoreBundle\Traits\LoggerTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\FormTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\LoggerInitalizationTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\MasterPasswordTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\NotBlank;

/*
 * The MIT License
 *
 * Copyright 2017 Felix Jacobi.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * School Certificate Manager Connector Login/Logout
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("scmc", schemes="https")
 * //@Security("requires_channel: https")
 */
class SecurityController extends PageController 
{
    use MasterPasswordTrait, SecurityTrait, LoggerTrait, LoggerInitalizationTrait, FormTrait;
    
    /**
     * Displays login form
     * 
     * @param Request $request
     * @return array|Response
     * @Route("/login", name="scmc_login")
     * @Template("StsblSchoolCertificateManagerConnectorBundle:Security:login.html.twig")
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
            if(!$form->isValid()) {
                $this->handleFormErrors($form);
                goto render;
            }
        
            $data = $form->getData();
            $this->initalizeLogger();
            
            $ret = $this->get('stsbl.scmc.service.session')->openSession($data['masterpassword'], $data['userpassword']);
            
            if ($ret == 'wrong') {
                $this->log('Zeugnisverwaltungs-Login: Falsches Masterpasswort');
                $error = _('The master password is wrong.');
                goto render;
            } else if ($ret == 'wrong userpassword') {
                $this->log('Zeugnisverwaltungs-Login: Falsches Benuterpasswort');
                $error = _('The user password is wrong.');
                goto render;
            }
            
            $this->log('Login im Zeugnisverwaltungsbreich erfolgreich');            
            $this->get('iserv.flash')->success(_('You have logged in successfully in the Certificate Management Section.'));
            
            // assume sucessful login
            return $this->redirect($this->generateUrl('scmc_index')); 
        }
        
        render:

        $act = $this->get('iserv.security_handler')->getToken()->getUser()->getUsername();
        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $doctrine = $this->getDoctrine();
        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $doctrine->getManager();
        /* @var $object \Stsbl\SchoolCertificateManagerConnectorBundle\Entity\User */
        $object = $em->find('StsblSchoolCertificateManagerConnectorBundle:UserPassword', $act);
        
        if (!is_null($object)) {
            $hasUserPassword = $object->getPassword();
        } else {
            $hasUserPassword = false;
        }
            
        // parameters
        $view = $form->createView();
        $emptyMasterPassword = $this->isMasterPasswordEmpty();
        
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('scmc_forward'));
        
        return array('login_form' => $view, 'emptyMasterPassword' => $emptyMasterPassword, 'hasUserPassword' => $hasUserPassword, 'error' => $error);
    }
    
    /**
     * Logouts user from current session
     * 
     * @param Request $request
     * @return RedirectResponse
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
                'constraints' => new NotBlank(['message' => _('Please enter the master password and try it again.')]),
                'attr' => array(
                    'placeholder' => _('Master password'),
                    'autofocus' => 'autofocus'
                    )
                )
            )
            ->add('userpassword', PasswordType::class, array(
                'label' => false,
                'required' => true,
                'constraints' => new NotBlank(['message' => _('Please enter the user password and try it again.')]),
                'attr' => array(
                    'placeholder' => _('User password'),
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
