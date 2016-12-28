<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/AdminController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use Braincrafted\Bundle\BootstrapBundle\Form\Type\FormActionsType;
use Doctrine\ORM\NoResultException;
use IServ\CoreBundle\Controller\PageController;
use IServ\CoreBundle\Traits\LoggerTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\LoggerInitalizationTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\MasterPasswordTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Administrative Settings for the school certificate manager connector
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("admin/scmc")
 */
class AdminController extends PageController {
    use MasterPasswordTrait, SecurityTrait, LoggerTrait, LoggerInitalizationTrait;
    
    /**
     * Overview page
     * 
     * @param Request $request
     * @Route("", name="admin_scmc")
     */
    public function indexAction(Request $request)
    {
        if(!$this->isAdmin()) {
            throw $this->createAccessDeniedException('You must be an administrator.');
        }
        
        $isMasterPasswordEmtpy = $this->isMasterPasswordEmpty();
        $form = $this->getMasterPasswordUpdateForm()->createView();
        
        // track path
        $this->addBreadcrumb(_('Certificate Management'));
        
        return $this->render('StsblSchoolCertificateManagerConnectorBundle:Admin:index.html.twig', array('emptyMasterPassword' => $isMasterPasswordEmtpy, 'masterpassword_form' => $form));
    }
    
    /**
     * Update master password
     * 
     * @param Request $request
     * @Route("/update/masterpassword", name="admin_scmc_update_master_password")
     * @Method("POST")
     */
    public function updateMasterPasswordAction(Request $request)
    {
        if(!$this->isAdmin()) {
            throw $this->createAccessDeniedException('You must be an administrator.');
        }
        $form = $this->getMasterPasswordUpdateForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->initalizeLogger();
            
            if (empty($data['oldmasterpassword']) && !$this->isMasterPasswordEmpty()) {
                $this->get('iserv.flash')->error(_('Old master password can not be empty.'));
                $this->log('Masterpasswortaktualisierung fehlgeschlagen: Altes Passwort leer');
                
                return $this->redirect($this->generateUrl('admin_scmc'));
            } else if (!isset($data['oldmasterpassword']) && $this->isMasterPasswordEmpty()) {
                $oldMasterPassword = '';
            } else {
                $oldMasterPassword = $data['oldmasterpassword'];
            }
            
            if ($data['newmasterpassword'] !== $data['repeatmasterpassword']) {
                $this->get('iserv.flash')->error(_('New password and repeat does not match.'));
                $this->log('Masterpasswortaktualisierung fehlgeschlagen: Neues Passwort und Wiederholung nicht übereinstimmend');
                
                return $this->redirect($this->generateUrl('admin_scmc'));
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
            
            return $this->redirect($this->generateUrl('admin_scmc'));
            
        } else {
            $this->get('iserv.flash')->error(_('Invalid request'));
            
            return $this->redirect($this->generateUrl('admin_scmc'));
        }
    }
    
    /**
     * Creates form to update master password
     * 
     * @return \Symfony\Component\Form\Form
     */
    private function getMasterPasswordUpdateForm()
    {
        $isMasterPasswordEmpty = $this->isMasterPasswordEmpty();
        $builder = $this->createFormBuilder();
        
        $builder
            ->setAction($this->generateUrl('admin_scmc_update_master_password'))
        ;
        
        if (!$isMasterPasswordEmpty) {
            $builder->add('oldmasterpassword', PasswordType::class, array(
                'label' => false,
                'required' => true,
                'attr' => array(
                    'placeholder' => _('Old master password'),
                    'autocomplete' => 'off',
                    )
                )
            );
        }
        
        $builder
            ->add('newmasterpassword', PasswordType::class, array(
                'label' => false,
                'required' => true,
                'attr' => array(
                    'placeholder' => _('New master password'),
                    'autocomplete' => 'off',
                    )
                )
            )
            ->add('repeatmasterpassword', PasswordType::class, array(
                'label' => false,
                'required' => true,
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
     * Displays form to set a password for a user
     * 
     * @param Request $request
     * @Route("/userpasswords/set/{user}", name="admin_scmc_set_user_password")
     * @param string $user
     */
    public function setUserPasswordAction(Request $request, $user)
    {
        if(!$this->isAdmin()) {
            throw $this->createAccessDeniedException('You must be an administrator.');
        }
        
        $FullName = $this->getUserEntity($user)->getName();
        
        $builder = $this->createFormBuilder();
        
        $builder
            ->add('userpassword', PasswordType::class, array(
                'label' => _('New user password'),
                'required' => true,
                'attr' => array(
                    'autocomplete' => 'off',
                    )
                )
            )
            ->add('submit', SubmitType::class, array(
                'label' => _('Set password'),
                'buttonClass' => 'btn-success',
                'icon' => 'ok'
                )
            )
        ;
        
        $form = $builder->getForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->initalizeLogger();
            $password = $data['userpassword'];
            if (empty($password)) {
                $this->get('iserv.flash')->error(_('User password can not be empty!'));
                return $this->redirectToRoute('admin_scmc_set_user_password', ['user' => $user]);
            }
            
            $securityHandler = $this->get('iserv.security_handler');
            $sessionPassword = $securityHandler->getSessionPassword();
            // echo $sessionPassword;
            $act = $securityHandler->getToken()->getUser()->getUsername();
        
            /* @var $shell \IServ\CoreBundle\Service\Shell */
            $shell = $this->get('iserv.shell');
            $shell->exec('/usr/bin/sudo', ['/usr/lib/iserv/scmc_userpassword_set', $user], null, ['SESSPW' => $sessionPassword, 'SCMC_ACT' => $act, 'SCMC_USERPW' => $password]);
            $exitCode = $shell->getExitCode();
            if ($exitCode !== 0) {
                throw new \RuntimeException('Shell returned exit code '.$exitCode.'.');
            }
            
            $this->get('iserv.flash')->success(__('User password of %s set.', $FullName));
            $this->log(sprintf('Benutzerpasswort von %s gesetzt', $FullName));
            return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user]);
            
        } else {
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
            
            $parameters = ['password_form' => $form->createView(), 'act' => $user, 'fullname' => $FullName, 'permissionnotice' => $permissionNotice];
            return $this->render('StsblSchoolCertificateManagerConnectorBundle:Admin:setuserpassword.html.twig', $parameters);
        }
    }

    /**
     * Displays form to delete a password for a user
     * 
     * @param Request $request
     * @Route("/userpasswords/delete/{user}", name="admin_scmc_delete_user_password")
     * @param string $user
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
                $shell->exec('/usr/bin/sudo', ['/usr/lib/iserv/scmc_userpassword_delete', $user], null, ['SESSPW' => $sessionPassword, 'SCMC_ACT' => $act]);
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
            
            $parameters = ['fullname' => $FullName, 'act' => $user, 'delete_form' => $form->createView()];
            return $this->render('StsblSchoolCertificateManagerConnectorBundle:Admin:deleteuserpassword.html.twig', $parameters);
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
