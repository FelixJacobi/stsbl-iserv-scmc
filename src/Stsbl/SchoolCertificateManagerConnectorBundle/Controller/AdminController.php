<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/AdminController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use Braincrafted\Bundle\BootstrapBundle\Form\Type\FormActionsType;
use Doctrine\ORM\NoResultException;
use IServ\CoreBundle\Controller\PageController;
use IServ\CoreBundle\Traits\LoggerTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\FormTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\LoggerInitalizationTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\MasterPasswordTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
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
 * Administrative Settings for the school certificate manager connector
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("admin/scmc")
 */
class AdminController extends PageController {
    use MasterPasswordTrait, SecurityTrait, LoggerTrait, LoggerInitalizationTrait, FormTrait;
    
    /**
     * Overview page
     * 
     * @param Request $request
     * @return array
     * @Route("", name="admin_scmc")
     * @Template("StsblSchoolCertificateManagerConnectorBundle:Admin:index.html.twig")
     */
    public function indexAction(Request $request)
    {
        if(!$this->isAdmin()) {
            throw $this->createAccessDeniedException('You must be an administrator.');
        }
        
        $isMasterPasswordEmtpy = $this->isMasterPasswordEmpty();
        $this->handleMasterPasswordForm($request);
        $view = $this->getMasterPasswordUpdateForm()->createView();
        
        // track path
        $this->addBreadcrumb(_('Certificate Management'));
        
        return ['emptyMasterPassword' => $isMasterPasswordEmtpy, 'masterpassword_form' => $view];
    }
    
    /**
     * Try to update the master password
     * 
     * @param Form $form
     */
    private function handleMasterPasswordForm(Request $request)
    {
        $form = $this->getMasterPasswordUpdateForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->initalizeLogger();
         
            if (!isset($data['oldmasterpassword']) && $this->isMasterPasswordEmpty()) {
                $oldMasterPassword = '';
            } else {
                $oldMasterPassword = $data['oldmasterpassword'];
            }
            
            if ($data['newmasterpassword'] !== $data['repeatmasterpassword']) {
                $this->get('iserv.flash')->error(_('New password and repeat does not match.'));
                $this->log('Masterpasswortaktualisierung fehlgeschlagen: Neues Passwort und Wiederholung nicht übereinstimmend');
                return;
            } else {
                $newMasterPassword = $data['newmasterpassword'];
            }
            
            $update = $this->updateMasterPassword($oldMasterPassword, $newMasterPassword);
            
            if ($update === true) {
                
                $this->get('iserv.flash')->success(_('Master password updated successfully.'));
                $this->log('Masterpasswort erfolgreich aktualisiert');
            } else if ($update === 'wrong') {
                $this->get('iserv.flash')->error(_('Old master password is wrong.'));
                $this->log('Masterpasswortaktualisierung fehlgeschlagen: Altes Passwort falsch');
            } else {
                $this->get('iserv.flash')->error(_('This should never happen.'));
            }
        } else {
            $this->handleFormErrors($form);
        }
    }
    
    /**
     * Creates form to update master password
     * 
     * @return Form
     */
    private function getMasterPasswordUpdateForm()
    {
        $isMasterPasswordEmpty = $this->isMasterPasswordEmpty();
        $builder = $this->createFormBuilder();
        
        if (!$isMasterPasswordEmpty) {
            $builder->add('oldmasterpassword', PasswordType::class, array(
                'label' => false,
                'required' => true,
                'constraints' => new NotBlank(['message' => _('Old master password can not be empty.')]),
                'attr' => array(
                    'placeholder' => _('Old master password'),
                    'autocomplete' => 'off',
                    )
                )
            );
        }
        
        $builder
            ->add('newmasterpassword', PasswordType::class, [
                'label' => false,
                'required' => true,
                'constraints' => new NotBlank(['message' => _('New master password can not be empty.')]),
                'attr' => [
                    'placeholder' => _('New master password'),
                    'autocomplete' => 'off',
                    ]
                ]
            )
            ->add('repeatmasterpassword', PasswordType::class, array(
                'label' => false,
                'required' => true,
                'constraints' => new NotBlank(['message' => _('Repeat of new master password can not be empty.')]),
                'attr' => array(
                    'placeholder' => _('Repeat new master password'),
                    'autocomplete' => 'off',
                    )
                )
            )
            ->add('submit', SubmitType::class, array(
                'label' => _('Update password'),
                'buttonClass' => 'btn-success',
                'icon' => 'ok'
                )
            )
        ;
        
        return $builder->getForm();
    }
    
    /**
     * Creates form for a new user password
     * 
     * @return Form
     */
    private function getNewUserPasswordForm()
    {
        $builder = $this->createFormBuilder();
        
        $builder
            ->add('userpassword', PasswordType::class, array(
                'label' => _('New user password'),
                'required' => true,
                'constraints' => new NotBlank(['message' => _('User password can not be empty.')]),
                'attr' => array(
                    'autocomplete' => 'off',
                    )
                )
            )
            ->add('actions', FormActionsType::class);
        
        $builder->get('actions')
            ->add('approve', SubmitType::class, array(
                'label' => _('Set user password'),
                'buttonClass' => 'btn-success',
                'icon' => 'ok'
                )
            )
            ->add('cancel', SubmitType::class, array(
                'label' => _('Cancel'),
                'buttonClass' => 'btn-default',
                'icon' => 'remove'
                ))
        ;
        
        return $builder->getForm();
    }
    
    /**
     * Displays form to set a password for a user
     * 
     * @param Request $request
     * @param string $user
     * @return array
     * @Route("/userpasswords/set/{user}", name="admin_scmc_set_user_password")
     * @Template("StsblSchoolCertificateManagerConnectorBundle:Admin:setuserpassword.html.twig")
     */
    public function setUserPasswordAction(Request $request, $user)
    {
        if(!$this->isAdmin()) {
            throw $this->createAccessDeniedException('You must be an administrator.');
        }
        
        $FullName = $this->getUserEntity($user)->getName();
        
        $form = $this->getNewUserPasswordForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->getClickedButton()->getName() === 'cancel') {
            // go back, if user pressed cancel
            return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user]);
        } else if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->initalizeLogger();
            $password = $data['userpassword'];
            
            $securityHandler = $this->get('iserv.security_handler');
            $sessionPassword = $securityHandler->getSessionPassword();
            // echo $sessionPassword;
            $act = $securityHandler->getToken()->getUser()->getUsername();
        
            /* @var $shell \IServ\CoreBundle\Service\Shell */
            $shell = $this->get('iserv.shell');
            $shell->exec('/usr/bin/setsid', ['-w', '/usr/bin/sudo', '/usr/lib/iserv/scmc_userpassword_set', $user], null, ['SESSPW' => $sessionPassword, 'SCMC_ACT' => $act, 'SCMC_USERPW' => $password]);
            $exitCode = $shell->getExitCode();
            if ($exitCode !== 0) {
                throw new \RuntimeException('Shell returned exit code '.$exitCode.'.');
            }
            
            $this->get('iserv.flash')->success(__('User password of %s set.', $FullName));
            $this->log(sprintf('Benutzerpasswort von %s gesetzt', $FullName));
            return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user]);
            
        } else {
            // show form errors if any
            $this->handleFormErrors($form);
            
            $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('admin_scmc'));
            $this->addBreadcrumb(_('User passwords'), $this->generateUrl('admin_scmc_userpassword_index'));
            $this->addBreadcrumb($FullName, $this->generateUrl('admin_scmc_userpassword_show', ['id' => $user]));
            $this->addBreadcrumb(_('Set user password'));
            
            /* @var $userPrivileges \Doctrine\Common\Collections\ArrayCollection */
            $userPrivileges = $this->getUserEntity($user)->getPrivileges();
            $hasPrivilege = false;
            
            foreach ($userPrivileges as $privilege) {
                /* @var $privilege \IServ\CoreBundle\Entity\Privilege */
                if ($privilege->getPriv() == 'PRIV_SCMC_ACCESS_FRONTEND') {
                    $hasPrivilege = true;
                    break;
                }
            }
            
            // check if user has privileges
            if (!$hasPrivilege) {
                $permissionNotice = true;
            } else {
                $permissionNotice = false;
            }
            
            return ['password_form' => $form->createView(), 'act' => $user, 'fullname' => $FullName, 'permissionnotice' => $permissionNotice];
        }
    }

    /**
     * Displays form to delete a password for a user
     * 
     * @param Request $request
     * @param string $user
     * @return array
     * @Route("/userpasswords/delete/{user}", name="admin_scmc_delete_user_password")
     * @Template("StsblSchoolCertificateManagerConnectorBundle:Admin:deleteuserpassword.html.twig")
     */
    public function deleteUserPasswordAction(Request $request, $user)
    {
        if(!$this->isAdmin()) {
            throw $this->createAccessDeniedException('You must be an administrator.');
        }
        
        $FullName = $this->getUserEntity($user)->getName();
        
        $builder = $this->createFormBuilder();
        $builder
            ->add('actions', FormActionsType::class)
        ;
        
        $builder->get('actions')
            ->add('approve', SubmitType::class, array(
                'label' => _('Yes'),
                'buttonClass' => 'btn-danger',
                'icon' => 'ok'
                )
            )
            ->add('cancel', SubmitType::class, array(
                'label' => _('No'),
                'buttonClass' => 'btn-default',
                'icon' => 'remove'
                )
            )
        ;
        
        $form = $builder->getForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->initalizeLogger();
            $button = $form->getClickedButton()->getName();
            if ($button === 'approve') {
                $securityHandler = $this->get('iserv.security_handler');
                $sessionPassword = $securityHandler->getSessionPassword();
                // echo $sessionPassword;
                $act = $securityHandler->getToken()->getUser()->getUsername();
        
                /* @var $shell \IServ\CoreBundle\Service\Shell */
                $shell = $this->get('iserv.shell');
                $shell->exec('/usr/bin/setsid', ['-w', '/usr/bin/sudo', '/usr/lib/iserv/scmc_userpassword_delete', $user], null, ['SESSPW' => $sessionPassword, 'SCMC_ACT' => $act]);
                $exitCode = $shell->getExitCode();
                if ($exitCode !== 0) {
                    throw new \RuntimeException('Shell returned exit code '.$exitCode.'.');
                }
            
                $this->get('iserv.flash')->success(__('User password of %s deleted.', $FullName));
                $this->log(sprintf('Benutzerpasswort von %s gelöscht', $FullName));
                return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user]);
            } else {
                return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user]);
            }
        } else {
            $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('admin_scmc'));
            $this->addBreadcrumb(_('User passwords'), $this->generateUrl('admin_scmc_userpassword_index'));
            $this->addBreadcrumb($FullName, $this->generateUrl('admin_scmc_userpassword_show', ['id' => $user]));
            $this->addBreadcrumb(_('Delete user password'));
            
            return ['fullname' => $FullName, 'act' => $user, 'delete_form' => $form->createView()];
        }
    }
    
    /**
     * Gets the Entity of an IServ User
     * 
     * @param string $act
     * @return User
     */
    private function getUserEntity($act)
    {
        /* @var $repository \Doctrine\Common\Persistence\ObjectRepository */
        $repository = $this->getDoctrine()->getRepository('IServCoreBundle:User');
        
        /* @var $userObject \IServ\CoreBundle\Entity\User */
        try {
            $userEntity = $repository->findOneByUsername($act);
        } catch (NoResultException $e) {
            throw new \RuntimeException('User was not found.');
        }
        
        return $userEntity;
    }
}
