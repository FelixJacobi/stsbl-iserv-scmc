<?php

namespace Stsbl\ScmcBundle\Controller;

use Braincrafted\Bundle\BootstrapBundle\Form\Type\FormActionsType;
use IServ\CoreBundle\Entity\User;
use IServ\CoreBundle\Entity\UserRepository;
use IServ\CoreBundle\Form\Type\BooleanType;
use IServ\CoreBundle\Service\Flash;
use IServ\CoreBundle\Service\Logger;
use IServ\CoreBundle\Traits\LoggerTrait;
use IServ\CrudBundle\Controller\StrictCrudController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\ScmcBundle\Security\Privilege;
use Stsbl\ScmcBundle\Service\ScmcAdm;
use Stsbl\ScmcBundle\Traits\LoggerInitializationTrait;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;

/*
 * The MIT License
 *
 * Copyright 2018 Felix Jacobi.
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
 * @Security("is_granted('PRIV_SCMC_ADMIN')")
 */
class AdminController extends StrictCrudController
{
    use LoggerTrait, LoggerInitializationTrait, FormTrait;

    const ROOM_CONFIG_FILE = '/var/lib/stsbl/scmc/cfg/room-mode.json';


    /**
     * Current room policy mode
     *
     * @var bool
     */
    private static $roomMode;

    /**
     * Get current room filter mode
     *
     * @return bool
     */
    public static function getRoomMode()
    {
        if (!is_bool(self::$roomMode)) {
            $content = file_get_contents(self::ROOM_CONFIG_FILE);
            self::$roomMode = json_decode($content, true)['invert'];
        }

        return self::$roomMode;
    }

    /**
     * Overview page
     *
     * @param Request $request
     * @return array
     * @Route("", name="admin_scmc")
     * @Template("StsblScmcBundle:Admin:index.html.twig")
     * @throws \IServ\CoreBundle\Exception\ShellExecException
     */
    public function indexAction(Request $request)
    {
        $this->handleMasterPasswordForm($request);
        $view = $this->getMasterPasswordUpdateForm()->createView();
        $isMasterPasswordEmtpy = $this->get(ScmcAdm::class)->masterPasswdEmpty();

        // track path
        $this->addBreadcrumb(_('Certificate Management'));
        
        return [
            'emptyMasterPassword' => $isMasterPasswordEmtpy,
            'masterpassword_form' => $view,
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc'
        ];
    }

    /**
     * Try to update the master password
     *
     * @param Request $request
     * @throws \IServ\CoreBundle\Exception\ShellExecException
     */
    private function handleMasterPasswordForm(Request $request)
    {
        $form = $this->getMasterPasswordUpdateForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->initalizeLogger();
         
            if (!isset($data['oldmasterpassword']) && $this->get(ScmcAdm::class)->masterPasswdEmpty()) {
                $oldMasterPassword = '';
            } else {
                $oldMasterPassword = $data['oldmasterpassword'];
            }
            
            if ($data['newmasterpassword'] !== $data['repeatmasterpassword']) {
                $this->get('iserv.flash')->error(_('New password and repeat does not match.'));
                $this->log(
                    'Masterpasswortaktualisierung fehlgeschlagen: Neues Passwort und Wiederholung nicht übereinstimmend'
                );
                return;
            } else {
                $newMasterPassword = $data['newmasterpassword'];
            }
            
            $this->get(Flash::class)->addBag(
                $this->get(ScmcAdm::class)->setMasterPasswd($newMasterPassword, $oldMasterPassword)
            );
        } else {
            $this->handleFormErrors($form);
        }
    }

    /**
     * Creates form to update master password
     *
     * @return Form|FormInterface
     * @throws \IServ\CoreBundle\Exception\ShellExecException
     */
    private function getMasterPasswordUpdateForm()
    {
        $isMasterPasswordEmpty = $this->get(ScmcAdm::class)->masterPasswdEmpty();
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
            ->add(
                'newmasterpassword',
                PasswordType::class, [
                'label' => false,
                'required' => true,
                'constraints' => new NotBlank(['message' => _('New master password can not be empty.')]),
                'attr' => [
                    'placeholder' => _('New master password'),
                    'autocomplete' => 'off',
                    ]
                ]
            )
            ->add(
                'repeatmasterpassword',
                PasswordType::class, [
                    'label' => false,
                    'required' => true,
                    'constraints' => new NotBlank(['message' => _('Repeat of new master password can not be empty.')]),
                    'attr' => [
                        'placeholder' => _('Repeat new master password'),
                        'autocomplete' => 'off',
                    ]
                ]
            )
            ->add('submit',
                SubmitType::class,
                array(
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
     * @return Form|FormInterface
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
     * @return array|RedirectResponse
     * @Route("/userpassword/set/{user}", name="admin_scmc_set_user_password")
     * @Template("StsblScmcBundle:Admin:setuserpassword.html.twig")
     * @throws \IServ\CoreBundle\Exception\ShellExecException
     */
    public function setUserPasswordAction(Request $request, $user)
    {
        $user = $this->getUserEntity($user);
        $FullName = $user->getName();
        
        $form = $this->getNewUserPasswordForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->getClickedButton()->getName() === 'cancel') {
            // go back, if user pressed cancel
            return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user->getId()]);
        } else if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $password = $data['userpassword'];
            
            $this->get(Flash::class)->addBag($this->get(ScmcAdm::class)->setUserPasswd($user->getUsername(), $password));

            return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user->getId()]);
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
                if ($privilege->getPriv() === Privilege::ACCESS_FRONTEND) {
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
            
            return [
                'password_form' => $form->createView(),
                'act' => $user->getUsername(),
                'fullname' => $FullName,
                'permissionnotice' => $permissionNotice,
                'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc'
            ];
        }
    }

    /**
     * Displays form to delete a password for a user
     *
     * @param Request $request
     * @param string $user
     * @return array|Response
     * @Route("/userpasswords/delete/{user}", name="admin_scmc_delete_user_password")
     * @Template("StsblScmcBundle:Admin:deleteuserpassword.html.twig")
     * @throws \IServ\CoreBundle\Exception\ShellExecException
     */
    public function deleteUserPasswordAction(Request $request, $user)
    {
        $user = $this->getUserEntity($user);
        $FullName = $user->getName();
        
        $builder = $this->createFormBuilder();
        $builder->add('actions', FormActionsType::class);
        
        $builder->get('actions')
            ->add(
                'approve',
                SubmitType::class,
                array(
                    'label' => _('Yes'),
                    'buttonClass' => 'btn-danger',
                    'icon' => 'ok'
                )
            )
            ->add(
                'cancel',
                SubmitType::class,
                array(
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
                $this->get(Flash::class)->addBag($this->get(ScmcAdm::class)->deleteUserPasswd($user->getUsername()));

                return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user->getId()]);
            } else {
                return $this->redirectToRoute('admin_scmc_userpassword_show', ['id' => $user->getId()]);
            }
        } else {
            $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('admin_scmc'));
            $this->addBreadcrumb(_('User passwords'), $this->generateUrl('admin_scmc_userpassword_index'));
            $this->addBreadcrumb($FullName, $this->generateUrl('admin_scmc_userpassword_show', ['id' => $user->getId()]));
            $this->addBreadcrumb(_('Delete user password'));
            
            return [
                'fullname' => $FullName,
                'act' => $user->getUsername(),
                'delete_form' => $form->createView(),
                'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc'
            ];
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
        /* @var $repository UserRepository */
        $repository = $this->getDoctrine()->getRepository('IServCoreBundle:User');
        
        /* @var $userObject \IServ\CoreBundle\Entity\User */
        $userEntity = $repository->findOneBy(['username' => $act]);

        return $userEntity;
    }

    /**
     * Get form for room inclusion mode
     *
     * @return \Symfony\Component\Form\Form
     */
    private function getRoomInclusionForm()
    {
        /* @var $builder \Symfony\Component\Form\FormBuilder */
        $builder = $this->get('form.factory')->createNamedBuilder('file_distribution_room_inclusion');

        $mode = self::getRoomMode();

        if ($mode === true) {
            $mode = 1;
        } else {
            $mode = 0;
        }

        $builder
            ->add('mode', BooleanType::class, [
                'label' => false,
                'choices' => [
                    _('All rooms except the following') => '1',
                    _('The following rooms') => '0',
                ],
                'preferred_choices' => [(string)$mode],
                'constraints' => [new NotBlank()],
            ])
            ->add('submit', SubmitType::class, [
                'label' => _('Save'),
                'buttonClass' => 'btn-success',
                'icon' => 'pro-floppy-disk'
            ])
        ;

        return $builder->getForm();
    }

    /**
     * index action for room admin
     *
     * @param Request $request
     * @return array
     * @throws \IServ\CoreBundle\Exception\ShellExecException
     */
    public function roomIndexAction(Request $request)
    {
        $ret = parent::indexAction($request);
        $form = $this->getRoomInclusionForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mode = (boolean)$form->getData()['mode'];

            // log if mode is changed
            if ($mode !== self::getRoomMode()) {
                if ($mode === true) {
                    $text = 'Raumrichtlinie geändert auf "Alle, außer den folgenden"';
                } else {
                    $text = 'Raumrichtlinie geändert auf "Folgende"';
                }

                $this->initalizeLogger();
                $this->log($text);
                $this->get(Flash::class)->addBag($this->get(ScmcAdm::class)->newConfig());
            }

            $content = json_encode(['invert' => $mode]);

            file_put_contents(self::ROOM_CONFIG_FILE, $content);
            $this->get('iserv.flash')->success(_('Room settings updated successful.'));
        }

        $ret['room_inclusion_form'] = $form->createView();

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices(): array
    {
        $deps = parent::getSubscribedServices();
        $deps[] = ScmcAdm::class;
        $deps[] = Flash::class;
        $deps[] = Logger::class;

        return $deps;
    }
}
