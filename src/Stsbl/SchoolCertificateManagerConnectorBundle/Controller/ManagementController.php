<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/ManagementController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use IServ\CoreBundle\Form\Type\BooleanType;
use IServ\CoreBundle\Traits\LoggerTrait;
use IServ\CrudBundle\Entity\FlashMessageBag;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\FormTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\LoggerInitializationTrait;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\IsTrue;
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
 * School Certificate Manager Connector Main Controller
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("scmc", schemes="https")
 * @Security("is_granted('PRIV_SCMC_ACCESS_FRONTEND') and token.hasAttribute('scmc_authentificated') and token.getAttribute('scmc_authentificated') == true")
 */
class ManagementController extends PageController 
{
    use FormTrait, LoggerTrait, LoggerInitializationTrait, SecurityTrait, FlashMessageBagTrait;
    
    /*
     * @var \Stsbl\SchoolCertificateManagerConnectorBundle\Menu\MenuBuilder
     */
    private $menuBuilder;
    
    /**
     * Get year choices for up- and download form
     * 
     * @return array
     */
    private function getYearChoices()
    {
        $ret = [
            __('Year %s', 5) => 5,
            __('Year %s', 6) => 6,
            __('Year %s', 7) => 7,
            __('Year %s', 8) => 8,
            __('Year %s', 9) => 9,
            __('Year %s', 10) => 10,
            __('Year %s', 11) => 11,
            __('Year %s', 12) => 12,
        ];
        
        // add year 13 on demand
        if ($this->get('iserv.config')->get('SCMCSchoolType') === 'stadtteilschule') {
            $ret[__('Year %s', 13)] = 13;
        }
        
        return $ret;
    }


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
        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $qb = $this
            ->getDoctrine()
            ->getRepository('IServCoreBundle:Log')
            ->createQueryBuilder('l')
        ;
        
        $qb
            ->select('l')
            ->where($qb->expr()->eq('l.module', ':module'))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->like('l.text', ':pattern1'),
                $qb->expr()->like('l.text', ':pattern2'))
            )
            ->orderBy('l.date', 'DESC')
            ->setMaxResults(10)
            ->setParameter('module', 'School Certificate Manager Connector')
            ->setParameter('pattern1', 'Zeugnisdaten vom Server "%" heruntergeladen')
            ->setParameter('pattern2', 'Zeugnisdaten auf den Server "%" hochgeladen')
        ;
        
        return ['menu' => $menu, 'lastActions' => $qb->getQuery()->getResult()];
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
     * @return Symfony\Component\HttpFoundation\RedirectResponse
     * @Method("POST")
     * @Route("/upload/zip", name="scmc_upload_zip")
     */
    public function uploadZipAction(Request $request)
    {
        $form = $this->getUploadForm();
        $form->handleRequest($request);
        if (!$form->isValid()) {
            $this->handleFormErrors($form);
            return $this->redirectToRoute('scmc_upload');
        }

        $data = $form->getData();
        /* @var $scmcAdm \Stsbl\SchoolCertificateManagerConnectorBundle\Service\ScmcAdm */
        $scmcAdm = $this->get('stsbl.scmc.service.scmcadm');
        $this->createFlashMessagesFromBag($scmcAdm->putData($data['server'], $data['class_data'], $data['years']));
        
        return $this->redirectToRoute('scmc_upload');
    }
    
    /**
     * School Certificate Manager Connector Dwonload Page
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
        $data = $form->getData();

        /* @var $scmcAdm \Stsbl\SchoolCertificateManagerConnectorBundle\Service\ScmcAdm */
        $scmcAdm = $this->get('stsbl.scmc.service.scmcadm');
        $getData = $scmcAdm->getData($data['server'], $data['years']);

        $this->createFlashMessagesFromBag($getData[0]);
        // assume error, if no prepared response is given
        if (!$getData[1] instanceof Response) {
            return $this->redirectToRoute('scmc_download');
        }

        $this->initalizeLogger();
        $this->log(sprintf('Zeugnisdaten vom Server "%s" heruntergeladen', (string)$data['server']->getHost()));

        return $getData[1];
    }
    
    /**
     * Gets the scmc upload formular
     * 
     * @return \Symfony\Component\Form\FormInterface
     */
    private function getUploadForm()
    {
        $builder = $this->createFormBuilder();
        $builder->setAction($this->generateUrl('scmc_upload_zip'));
        
        $builder
            ->add('server', EntityType::class, [
                'class' => 'StsblSchoolCertificateManagerConnectorBundle:Server',
                'label' => _('Select destination server'),
                'attr' => [
                    'help_text' => _('If your administrator has configured multiple servers (for example a primary and backup server), you can select the destination server.')
                    ]
            ])
            ->add('class_data', FileType::class, [
                'label' => _('Zip file with class data'),
                'constraints' => [new NotBlank(['message' => _('Please select a file to upload.')])],
                'attr' => [
                    'help_text' => _('The zip file with the class data. It must contain sub folders with the class lists sorted by age group (Jahrgang5, Jahrgang6, ...). For more information please refer the WZeugnis Documentation.')
                    ]
            ])
            ->add('years', ChoiceType::class, [
                'label' => _('Limit upload to these years'),
                'multiple' => true,
                'choices' => $this->getYearChoices(),
                'attr' => [
                    'class' => 'select2',
                    'help_text' => _('You can limit the upload to particular years. Only the course lists of the selected years will deleted and replaced on the server.'),
                ]
            ])
            ->add('confirm', BooleanType::class, [
                'label' => _('Confirmation'),
                'constraints' => [new IsTrue(['message' => _('You need to confirm that a new upload will delete all existing data on the server.')])],
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
            ->add('server', EntityType::class, [
                'class' => 'StsblSchoolCertificateManagerConnectorBundle:Server',
                'label' => _('Select destination server'),
                'attr' => [
                    'help_text' => _('If your administrator has configured multiple servers (for example a primary and backup server), you can select the destination server.')
                    ]
            ])
            ->add('years', ChoiceType::class, [
                'label' => _('Limit download to these years'),
                'multiple' => true,
                'choices' => $this->getYearChoices(),
                'attr' => [
                    'class' => 'select2',
                    'help_text' => _('You can limit the download to particular years. Only the selected years will be included in the Zip file.'),
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
