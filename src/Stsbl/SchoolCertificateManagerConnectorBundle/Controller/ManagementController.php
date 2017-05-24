<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/ManagementController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use IServ\CoreBundle\Traits\LoggerTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\FormTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\LoggerInitalizationTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Util\Password as PasswordUtil;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
 * School Certificate Manager Connector Main Controller
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("scmc", schemes="https")
 * @Security("is_granted('PRIV_SCMC_ACCESS_FRONTEND') and token.hasAttribute('scmc_authentificated') and token.getAttribute('scmc_authentificated') == true")
 */
class ManagementController extends PageController 
{
    use FormTrait, LoggerTrait, LoggerInitalizationTrait, SecurityTrait;
    
    /*
     * @var \Stsbl\SchoolCertificateManagerConnectorBundle\Menu\MenuBuilder
     */
    private $menuBuilder;


    public function setMenuBuilder()
    {
        /* @var $menuBuilder \Stsbl\SchoolCertificateManagerConnectorBundle\Menu\MenuBuilder */
        $menuBuilder = $this->get('stsbl.scmc.menu_builder');
        $this->menuBuilder = $menuBuilder;
    }

    /**
     * School Certificate Manager Connector Main Page
     * 
     * @return array
     * @Route("/index", name="scmc_index")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('scmc_forward'));
        $this->addBreadcrumb(_('Start Page'), $this->generateUrl('scmc_forward'));
        
        $this->setMenuBuilder();
        $menu = $this->menuBuilder->createSCMCMenu();
        return ['menu' => $menu];
    }
    
    /**
     * School Certificate Manager Connector Upload Page
     * 
     * @return array
     * @Route("/upload", name="scmc_upload")
     * @Template()
     */
    public function uploadAction(Request $request)
    {
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('scmc_forward'));
        $this->addBreadcrumb(_('Data Upload'), $this->generateUrl('scmc_upload'));
        
        $this->setMenuBuilder();
        $menu = $this->menuBuilder->createSCMCMenu();
        $form = $this->getUploadForm();
        $form->handleRequest($request);
        
        return ['menu' => $menu, 'form' => $form->createView()];
    }
    
    /**
     * School Certificate Manager Connector Upload Page
     * 
     * @return array
     * @Route("/download", name="scmc_download")
     * @Template()
     */
    public function downloadAction(Request $request)
    {
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('scmc_forward'));
        $this->addBreadcrumb(_('Data Download'), $this->generateUrl('scmc_download'));
        
        $this->setMenuBuilder();
        $menu = $this->menuBuilder->createSCMCMenu();
        $form = $this->getDownloadForm();
        $form->handleRequest($request);
        
        return ['menu' => $menu, 'form' => $form->createView()];
    }
    
    /**
     * School Certificate Manager Connector Upload Page
     * 
     * @return Symfony\Component\HttpFoundation\Response|Symfony\Component\HttpFoundation\RedirectResponse
     * @Method("POST")
     * @Route("/download/zip", name="scmc_download_zip")
     */
    public function downloadZipAction(Request $request)
    {
        $form = $this->getDownloadForm();
        $form->handleRequest($request);
        if (!$form->isValid()) {
            $this->handleFormErrors($form);
            return $this->redirectToRoute('scmc_download');
        }
        
        $this->initalizeLogger();
        $this->log('Zeugnisdaten vom Server heruntergeladen');

        $securityHandler = $this->get('iserv.security_handler');
        $sessionPassword = $securityHandler->getSessionPassword();
        $act = $securityHandler->getToken()->getUser()->getUsername();
        /* @var $shell \IServ\CoreBundle\Service\Shell */
        $shell = $this->get('iserv.shell');
        $shell->exec('sudo', [
            '/usr/lib/iserv/scmc_get_data',
            $act,
            1
        ], null, [
            'SESSPW' => $sessionPassword,
            'IP' => $request->getClientIp(),
            'IPFWD' => @$_SERVER['HTTP_X_FORWARDED_FOR'],
            'SCMC_SESSIONTOKEN' => $securityHandler->getToken()->getAttribute('scmc_sessiontoken'),
            'SCMC_SESSIONPW' => PasswordUtil::generateHash($securityHandler->getToken()->getAttribute('scmc_sessionpassword'), $securityHandler->getToken()->getAttribute('scmc_salt'), 11),
        ]);
        
        $zipPath = null;
        $output = [];
        foreach ($shell->getOutput() as $line) {
            if (preg_match('|^path=|', $line)) {
                $zipPath = preg_replace('|^path=|', '', $line);
            } else {
                $output[] = $line;
            }
        }
        
        if (count($shell->getError()) > 0) {
            $this->get('iserv.flash')->error(join("\n", $shell->getError()));
        }
        
        if(count($output) > 0) {
            $this->get('iserv.flash')->success(join("\n", $output));
        }
        
        if ($zipPath == null) {
            $this->get('iserv.flash')->error(_('Something went wrong.'));
            return $this->redirectToRoute('scmc_download');
        }
        
        $zipContent = file_get_contents($zipPath);
        unlink($zipPath);
        $quoted = sprintf('"%s"', addcslashes('zeugnis-download-'.date('d-m-Y-G-i-s').'.zip', '"\\'));
            
        $response = new Response($zipContent);
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$quoted);
        
        return $response;
    }
    
    /**
     * Gets the scmc upload formular
     * 
     * @return \Symfony\Component\Form\FormInterface
     */
    private function getUploadForm()
    {
        $builder = $this->createFormBuilder();
        
        $builder
            ->add('server', ChoiceType::class, [
                'label' => _('Select destination server'),
                'attr' => [
                    'help_text' => _('If your administrator has configured multiple servers (for example a primary and backup server), you can select the destination server.')
                    ]
                ])
            ->add('class_data', FileType::class, [
                'label' => _('Zip file with class data'),
                'attr' => [
                    'help_text' => _('The zip file with the class data. It must contain sub folders with the class lists sorted by age group (Jahrgang5, Jahrgang6, ...). For more information please refer the WZeugnis Documentation.')
                    ]
                ])
            ->add('confirm', \IServ\CoreBundle\Form\Type\BooleanType::class, [
                    'label' => _('Confirmation'),
                    'data' => false,
                    'attr' => [
                        'help_text' => _('Before you can upload new class data, you have to confirm that will lead to loosing all data currently stored on the certificate server.')
                    ]
                ])
            ->add('submit', SubmitType::class, [
                'label' => _('Upload data'), 
                'buttonClass' => 'btn-success', 
                'icon' => 'arrow-up'
                ])
            ;
        
        return $builder->getForm();
    }
    
    /**
     * Gets the scmc download formular
     * 
     * @return \Symfony\Component\Form\FormInterface
     */
    private function getDownloadForm()
    {
        $builder = $this->createFormBuilder();
        $builder->setAction($this->generateUrl('scmc_download_zip'));
        
        $builder
            ->add('server', ChoiceType::class, [
                'label' => _('Select destination server'),
                'attr' => [
                    'help_text' => _('If your administrator has configured multiple servers (for example a primary and backup server), you can select the destination server.')
                    ]
                ])
            ->add('submit', SubmitType::class, [
                'label' => _('Download data'), 
                'buttonClass' => 'btn-success', 
                'icon' => 'arrow-down'
                ])
            ;
        
        return $builder->getForm();
    }
}
