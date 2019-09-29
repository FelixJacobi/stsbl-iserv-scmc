<?php declare(strict_types = 1);

namespace Stsbl\ScmcBundle\Controller;

use Braincrafted\Bundle\BootstrapBundle\Form\Type\FormActionsType;
use IServ\CoreBundle\Controller\AbstractPageController;
use IServ\CoreBundle\Service\Logger;
use IServ\CoreBundle\Traits\LoggerTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\ScmcBundle\Entity\UserPassword;
use Stsbl\ScmcBundle\Security\ScmcAuth;
use Stsbl\ScmcBundle\Service\ScmcAdm;
use Stsbl\ScmcBundle\Traits\LoggerInitializationTrait;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;

/*
 * The MIT License
 *
 * Copyright 2019 Felix Jacobi.
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
 * @Security("is_granted('PRIV_SCMC_ACCESS_FRONTEND')")
 */
class SecurityController extends AbstractPageController
{
    use FormTrait, LoggerTrait, LoggerInitializationTrait;

    /**
     * Displays login form
     *
     * @Route("/login", name="manage_scmc_login")
     * @Template()
     *
     * @return array|Response
     */
    public function login(Request $request)
    {
        $auth = $this->get(ScmcAuth::class);
        $scmcAdm = $this->get(ScmcAdm::class);

        if ($auth->isAuthenticated()) {
            // go to index
            return $this->redirect($this->generateUrl('manage_scmc_index'));
        }

        $loginNotice = $this->get('session')->get('scmc_login_notice');
        $this->get('session')->remove('scmc_login_notice');
        
        $error = '';
        $form = $this->getLoginForm();
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->initalizeLogger();

            $ret = $auth->login($data['masterpassword'], $data['userpassword']);

            if ($ret === 'code required') {
                // session requires 2fa code
                return $this->redirectToRoute('manage_scmc_code');
            } elseif (true === $ret) {
                $this->log('Zeugnisverwaltungs-Login erfolgreich');
                $this->addFlash('success', _('You have logged in successfully in the Certificate Management Section.'));

                // assume successful login
                // check if previous url was provided
                $session = $this->get('session');
                if ($session->has('scmc_login_redirect') && $session->get('scmc_login_redirect') !== null) {
                    $url = $session->get('scmc_login_redirect');
                    $session->set('scmc_login_redirect', null);
                } else {
                    $url = $this->generateUrl('manage_scmc_index');
                }

                return $this->redirect($url);
            } elseif ($ret === 'master password wrong') {
                $this->log('Zeugnisverwaltungs-Login: Falsches Masterpasswort');
                $error = _('The master password is wrong.');
            } elseif ($ret === sprintf('user password for %s wrong', $this->getUser()->getUsername())) {
                $this->log('Zeugnisverwaltungs-Login: Falsches Benuterpasswort');
                $error = _('The user password is wrong.');
            } else {
                $this->log('Zeugnisverwaltungs-Login: Allgemeiner Fehler');
                $error = __('Something went wrong: %s', $ret);
            }
        }

        $act = $this->getUser()->getUsername();
        $doctrine = $this->getDoctrine();
        $em = $doctrine->getManager();

        /* @var $object \Stsbl\ScmcBundle\Entity\UserPassword */
        $object = $em->find(UserPassword::class, $act);
        
        if ($object != null) {
            $hasUserPassword = $object->hasPassword();
        } else {
            $hasUserPassword = false;
        }
            
        // parameters
        $view = $form->createView();
        $emptyMasterPassword = $scmcAdm->masterPasswdEmpty();
        
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('manage_scmc_forward'));
        
        return [
            'login_form' => $view,
            'emptyMasterPassword' => $emptyMasterPassword,
            'hasUserPassword' => $hasUserPassword,
            'error' => $error,
            'loginNotice' => $loginNotice,
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc'
        ];
    }
    
    /**
     * Logouts user from current session
     *
     * @param Request $request
     * @return RedirectResponse
     *
     * @Route("/logout", name="manage_scmc_logout")
     * @Security("token.hasAttribute('scmc_authenticated') and token.getAttribute('scmc_authenticated') == true")
     */
    public function logout()
    {
        $auth = $this->get(ScmcAuth::class);

        if (!$auth->close($this->getUser()->getUsername())) {
            throw new \RuntimeException('scmc_sess_close failed!');
        }
            
        $this->initalizeLogger();
        $this->log('Zeugnisverwaltungs-Logout erfolgreich');
        $this->addFlash('success', _('You have logged out successfully from the Certificate Management Section.'));
    
        return $this->redirect($this->generateUrl('manage_scmc_forward'));
    }

    /**
     * Ask user for his OATH code
     *
     * @param Request $request
     * @return array
     *
     * @Route("/code", name="manage_scmc_code")
     * @Template()
     */
    public function codeAction(Request $request)
    {
        $form = $this->createCodeForm();
        $form->handleRequest($request);

        return [
            'form' => $form->createView(),
            'error' => null
        ];
    }

    /**
     * Creates form to enter 2fa code
     */
    private function createCodeForm()
    {
        $builder = $this->get('form.factory')->createNamedBuilder('code');

        $builder
            ->add('code', NumberType::class, [
                'label' => false,
                'constraints' => [new NotBlank(['message' => _('Please enter authorization code.')])],
                'attr' => [
                    'placeholder' => _('Authorization code'),
                    'autofocuse' => 'autofocus'
                ]
            ])
            ->add('actions', FormActionsType::class)
        ;

        $builder->get('actions')
            ->add('continue', SubmitType::class, array(
                    'label' => _('Finish login'),
                    'buttonClass' => 'btn-success',
                    'icon' => 'ok'
                )
            )
            ->add('cancel', SubmitType::class, array(
                'label' => _('Logout'),
                'buttonClass' => 'btn-danger',
                'icon' => 'log-out'
            ))
        ;

        return $builder->getForm();
    }
    
    /**
     * Creates form to login with masterpassword
     * 
     * @return \Symfony\Component\Form\FormInterface
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

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        $deps = parent::getSubscribedServices();
        $deps[] = ScmcAdm::class;
        $deps[] = ScmcAuth::class;
        $deps[] = Logger::class;

        return $deps;
    }
}
