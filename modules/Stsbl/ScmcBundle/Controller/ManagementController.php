<?php

namespace Stsbl\ScmcBundle\Controller;

use IServ\CoreBundle\Controller\AbstractPageController;
use IServ\CoreBundle\Form\Type\BooleanType;
use IServ\CoreBundle\Service\Config;
use IServ\CoreBundle\Service\Flash;
use IServ\CoreBundle\Service\Logger;
use IServ\CoreBundle\Traits\LoggerTrait;
use IServ\FileBundle\Form\Type\UniversalFileType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\ScmcBundle\Menu\MenuBuilder;
use Stsbl\ScmcBundle\Service\ScmcAdm;
use Stsbl\ScmcBundle\Traits\LoggerInitializationTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\IsTrue;

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
 * School Certificate Manager Connector Main Controller
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("scmc", schemes="https")
 * @Security("is_granted('PRIV_SCMC_ACCESS_FRONTEND') and token.hasAttribute('scmc_authenticated') and token.getAttribute('scmc_authenticated') == true")
 */
class ManagementController extends AbstractPageController
{
    use FormTrait, LoggerInitializationTrait, LoggerTrait;
    
    /**
     * Get year choices for up- and download form
     *
     * @return int[] Option label as key
     */
    private function getYearChoices(): array
    {
        $config = $this->get(Config::class);

        $ret = [
            __('Year %s', 5) => 5,
            __('Year %s', 6) => 6,
            __('Year %s', 7) => 7,
            __('Year %s', 8) => 8,
            __('Year %s', 9) => 9,
            __('Year %s', 10) => 10,
        ];

        // add year 11 + 12 on demand
        if ($config->get('SCMCSchoolType') === 'gymnasium' ||
            $config->get('SCMCSchoolType') === 'stadtteilschule') {
            $ret[__('Year %s', 11)] = 11;
            $ret[__('Year %s', 12)] = 12;
        }
        
        // add year 13 on demand
        if ($config->get('SCMCSchoolType') === 'stadtteilschule') {
            $ret[__('Year %s', 13)] = 13;
        }
        
        return $ret;
    }

    /**
     * School Certificate Manager Connector Main Page
     *
     * @Route("/index", name="manage_scmc_index")
     * @Template()
     */
    public function index(): array
    {
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('manage_scmc_forward'));
        $this->addBreadcrumb(_('Start Page'), $this->generateUrl('manage_scmc_forward'));

        $menu = $this->get(MenuBuilder::class)->createSCMCMenu();
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
                $qb->expr()->like('l.text', ':pattern2')
            ))
            ->orderBy('l.date', 'DESC')
            ->setMaxResults(10)
            ->setParameter('module', 'School Certificate Manager Connector')
            ->setParameter('pattern1', 'Zeugnisdaten vom Server "%" heruntergeladen')
            ->setParameter('pattern2', 'Zeugnisdaten auf den Server "%" hochgeladen')
        ;
        
        return [
            'menu' => $menu,
            'lastActions' => $qb->getQuery()->getResult(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc',
        ];
    }

    /**
     * School Certificate Manager Connector Upload Page
     *
     * @Route("/upload", name="manage_scmc_upload")
     * @Template()
     */
    public function uploadAction(Request $request): array
    {
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('manage_scmc_forward'));
        $this->addBreadcrumb(_('Data Upload'), $this->generateUrl('manage_scmc_upload'));

        $menu = $this->get(MenuBuilder::class)->createSCMCMenu();
        $form = $this->createUploadForm();
        $form->handleRequest($request);
        
        return [
            'menu' => $menu,
            'form' => $form->createView(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc'
        ];
    }

    /**
     * @Route("/upload/zip", name="manage_scmc_upload_zip", methods={"POST"})
     */
    public function uploadZipAction(Request $request): RedirectResponse
    {
        $form = $this->createUploadForm();
        $form->handleRequest($request);
        if (!$form->isValid()) {
            $this->handleFormErrors($form);
            return $this->redirectToRoute('manage_scmc_upload');
        }

        $data = $form->getData();
        $data['class_data'] = array_merge($data['class_data'], [$form->get('class_data')->get('picker')->getData()]);

        // replacement for count constraint
        if (!is_array($data['class_data']) || !isset($data['class_data'][0]) || null === $data['class_data'][0]) {
            $this->get('iserv.flash')->error(_('Please select a file to upload.'));
            return $this->redirectToRoute('manage_scmc_upload');
        }

        $scmcAdm = $this->get(ScmcAdm::class);
        $this->get(Flash::class)->addBag($scmcAdm->putData($data['server'], $data['class_data'], $data['years']));
        
        return $this->redirectToRoute('manage_scmc_upload');
    }

    /**
     * School Certificate Manager Connector Download Page
     *
     * @Route("/download", name="manage_scmc_download")
     * @Template()
     */
    public function downloadAction(Request $request): array
    {
        // track path
        $this->addBreadcrumb(_('Certificate Management'), $this->generateUrl('manage_scmc_forward'));
        $this->addBreadcrumb(_('Data Download'), $this->generateUrl('manage_scmc_download'));

        $menu = $this->get(MenuBuilder::class)->createSCMCMenu();
        $form = $this->createDownloadForm();
        $form->handleRequest($request);
        
        return [
            'menu' => $menu,
            'form' => $form->createView(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc'
        ];
    }

    /**
     * @Route("/download/zip", name="manage_scmc_download_zip", methods={"POST"})
     */
    public function downloadZipAction(Request $request): RedirectResponse
    {
        $form = $this->createDownloadForm();
        $form->handleRequest($request);
        if (!$form->isValid()) {
            $this->handleFormErrors($form);
            return $this->redirectToRoute('manage_scmc_download');
        }
        $data = $form->getData();

        /* @var $scmcAdm \Stsbl\ScmcBundle\Service\ScmcAdm */
        $scmcAdm = $this->get(ScmcAdm::class);
        $getData = $scmcAdm->getData($data['server'], $data['years']);

        $this->get(Flash::class)->addBag($getData[0]);
        // assume error, if no prepared response is given
        if (!$getData[1] instanceof Response) {
            return $this->redirectToRoute('manage_scmc_download');
        }

        return $getData[1];
    }
    
    /**
     * Gets the scmc upload form
     */
    private function createUploadForm(): FormInterface
    {
        $builder = $this->createFormBuilder();
        $builder->setAction($this->generateUrl('manage_scmc_upload_zip'));
        
        $builder
            ->add('server', EntityType::class, [
                'class' => 'StsblScmcBundle:Server',
                'label' => _('Select destination server'),
                'attr' => [
                    'help_text' => _('If your administrator has configured multiple servers (for example a primary and backup server), you can select the destination server.')
                    ]
            ])
            ->add('class_data', UniversalFileType::class, [
                'label' => _('Zip file with class data'),
                // FIXME UninversalFileType breaks constraint
                //'constraints' => [new Count(['min' => 1, 'minMessage' => _('Please select a file to upload.')])],
                'attr' => [
                    'help_text' => _('The zip file with the class data. It must contain sub folders with the class lists sorted by age group (Jahrgang5, Jahrgang6, ...). For more information please refer the WZeugnis Documentation.')
                    ]
            ])
            ->add('years', ChoiceType::class, [
                'label' => _('Limit upload to these years'),
                'multiple' => true,
                'choices' => $this->getYearChoices(),
                'required' => false,
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
     * Gets the scmc download form
     */
    private function createDownloadForm(): FormInterface
    {
        $builder = $this->createFormBuilder();
        $builder->setAction($this->generateUrl('manage_scmc_download_zip'));
        
        $builder
            ->add('server', EntityType::class, [
                'class' => 'StsblScmcBundle:Server',
                'label' => _('Select destination server'),
                'attr' => [
                    'help_text' => _('If your administrator has configured multiple servers (for example a primary '.
                        'and backup server), you can select the destination server.')
                    ]
            ])
            ->add('years', ChoiceType::class, [
                'label' => _('Limit download to these years'),
                'multiple' => true,
                'choices' => $this->getYearChoices(),
                'required' => false,
                'attr' => [
                    'class' => 'select2',
                    'help_text' => _('You can limit the download to particular years. Only the selected years will be '.
                        'included in the Zip file.'),
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

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices(): array
    {
        $deps = parent::getSubscribedServices();
        $deps[] = Config::class;
        $deps[] = Flash::class;
        $deps[] = ScmcAdm::class;
        $deps[] = MenuBuilder::class;
        $deps[] = Logger::class;

        return $deps;
    }
}
