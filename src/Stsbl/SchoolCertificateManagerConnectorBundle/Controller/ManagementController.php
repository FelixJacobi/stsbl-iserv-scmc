<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/ManagementController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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
    use SecurityTrait;
    
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
     * @Template("StsblSchoolCertificateManagerConnectorBundle:Management:index.html.twig")
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
     * @Template("StsblSchoolCertificateManagerConnectorBundle:Management:upload.html.twig")
     */
    public function uploadAction(Request $request)
    {
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('scmc_forward'));
        $this->addBreadcrumb(_('Data Upload'), $this->generateUrl('scmc_upload'));
        
        $this->setMenuBuilder();
        $menu = $this->menuBuilder->createSCMCMenu();
        $form = $this->getUploadForm();
        
        return ['menu' => $menu, 'form' => $form->createView()];
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
}
