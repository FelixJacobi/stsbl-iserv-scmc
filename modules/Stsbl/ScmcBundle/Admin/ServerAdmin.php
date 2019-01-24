<?php

namespace Stsbl\ScmcBundle\Admin;

use Doctrine\ORM\EntityRepository;
use IServ\AdminBundle\Admin\AbstractAdmin;
use IServ\CoreBundle\Service\Flash;
use IServ\CoreBundle\Service\Logger;
use IServ\CoreBundle\Traits\LoggerTrait;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Mapper\FormMapper;
use IServ\CrudBundle\Mapper\ListMapper;
use IServ\CrudBundle\Mapper\ShowMapper;
use Stsbl\ScmcBundle\Crud\Batch;
use Stsbl\ScmcBundle\Entity\Server;
use Stsbl\ScmcBundle\Security\Privilege;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

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
 * Server Management
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class ServerAdmin extends AbstractAdmin
{
    use ScmcAdmTrait, LoggerTrait;

    /**
     * @var Flash
     */
    private $flash;

    public function __construct()
    {
        parent::__construct(Server::class);
    }

    /**
     * @required
     */
    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @required
     */
    public function setFlash(Flash $flash): void
    {
        $this->flash = $flash;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() 
    {
        $this->title = _('Servers');
        $this->itemTitle = _('Server');
        $this->id = 'scmc_server';
        $this->routesPrefix = 'admin/scmc/server';

        $this->options['help'] = 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc';

        $this->templates['crud_batch_confirm'] = 'StsblSchoolCertificateManagerConnectorBundle:Crud:admin_scmc_server_batch_confirm.html.twig';
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureFormFields(FormMapper $formMapper) 
    {
        $formMapper
            ->add('host', null, [
                'label' => _('Host'),
                'attr' => [
                    'help_text' => _('Select the host which should be the target for up- and downloading.'),
                ],
                'query_builder' => function (EntityRepository $er) use ($formMapper) {
                    $subQb = $er->createQueryBuilder('s');
            
                    $subQb
                        ->resetDqlParts()
                        ->select('h')
                        ->from('StsblSchoolCertificateManagerConnectorBundle:Server', 's')
                        ->where('s.host = h.id')
                    ;
                
                    $qb = $er->createQueryBuilder('h');

                    $qb
                        ->where($subQb->expr()->not($subQb->expr()->exists($subQb)))
                    ;

                    // add current object on editing
                    if ($formMapper->getObject() != null) {
                        $qb
                            ->orWhere($qb->expr()->eq('h.id', ':currentHost'))
                            ->setParameter('currentHost', $formMapper->getObject()->getHost())
                        ;
                    }

                    $qb
                        ->orderBy('h.name', 'ASC')
                    ;

                    return $qb;
                }
            ])
            ->add('tomcatType', ChoiceType::class, [
                'label' => _('Tomcat version'),
                'attr' => [
                    'help_text' => _('The version of Tomcat server which is running on the certificate management server.'),
                ],
                'choices' => [
                    _('Tomcat 6') => 'tomcat6',
                    _('Tomcat 7') => 'tomcat7',
                    _('Tomcat 8') => 'tomcat8',
                ]
            ])
            ->add('webDomain', null, [
                'label' => _('Domain'),
                'attr' => [
                    'help_text' => _('The domain, under which the reverse proxy is reachable. Must be different from the main domain or an alias domain of the portal server.'),
                ],
            ])
            ->add('group', null, [
                'label' => _('Assigned group'),
                'attr' => [
                    'help_text' => _('By default a link to the reverse proxy is shown to all users with the privilege "Show link to certificate management". You can limit the visibility of the link to one group.'),
                ],
            ])
            ->add('sshAccount', null, [
                'label' => _('SSH account'),
                'attr' => [
                    'help_text' => _('The account which is used to up- and download the data. The account must be member of the tomcat6/7/8 group on the certificate management server. You must upload a SSH key for this user in the server overview.'),
                ],
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureListFields(ListMapper $listMapper) 
    {
        $listMapper
            ->addIdentifier('host', null, [
                'label' => _('Host'),
            ])
            ->add('webDomain', null, [
                'label' => _('Domain'),
            ])
            ->add('group', null, [
                'label' => _('Assigned group'),
            ])
            ->add('sshAccount', null, [
                'label' => _('SSH account'),
                'responsive' => 'desktop',
            ])
            ->add('tomcatType', null, [
                'label' => _('Tomcat version'),
                'template' => 'StsblSchoolCertificateManagerConnectorBundle:List:field_tomcat_version.html.twig',
                'responsive' => 'desktop',
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureShowFields(ShowMapper $showMapper) 
    {
        $showMapper
            ->add('host', null, [
                'label' => _('Host')
            ])
            ->add('tomcatType', null, [
                'label' => _('Tomcat version'),
                'template' => 'StsblSchoolCertificateManagerConnectorBundle:Show:field_tomcat_version.html.twig',
            ])
            ->add('webDomain', null, [
                'label' => _('Domain')
            ])
            ->add('group', null, [
                'label' => _('Assigned group')
            ])
            ->add('sshAccount', null, [
                'label' => _('SSH account')
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRoutePattern($action, $id, $entityBased = true)
    {
        if ('index' === $action) {
            return sprintf('/%s', $this->routesPrefix.'s');
        } else if ('show' === $action || 'edit' === $action) {
            return sprintf('/%s/%s/%s', $this->routesPrefix, $action, '{id}');
        } else if ('add' === $action) {
            return sprintf('/%s/%s', $this->routesPrefix, $action);
        } else if ('batch' === $action || 'batch/confirm' === $action) {
            return sprintf('/%s/%s', $this->routesPrefix.'s', $action);
        } else {
            return parent::getRoutePattern($action, $id, $entityBased);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function prepareBreadcrumbs()
    {
        return array(
            _('Certificate Management') => $this->router->generate('admin_scmc')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function postPersist(CrudInterface $object)
    {
        $this->logger->writeForModule(sprintf('Zeugnisserver "%s" hinzugefügt', (string)$object->getHost()), 'School Certificate Manager Connector');
        $this->newConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function postUpdate(CrudInterface $object, array $previousData = null)
    {
        /* @var $object Server */
        if ((string)$object->getHost() != $previousData['host']) {
            $this->logger->writeForModule(sprintf('Zeugnisserver "%s" verändert und umbenannt nach "%s"', $previousData['host'], (string)$object->getHost()), 'School Certificate Manager Connector');
        } else {
            $this->logger->writeForModule(sprintf('Zeugnisserver "%s" verändert', (string)$object->getHost()), 'School Certificate Manager Connector');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postRemove(CrudInterface $object)
    {
        /* @var $object Server */
        $this->logger->writeForModule(sprintf('Zeugnisserver "%s" gelöscht', (string)$object->getHost()), 'School Certificate Manager Connector');
        $this->flash->addBag($this->scmcAdm->deleteKey($object));
        $this->newConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function loadBatchActions()
    {
        $res = parent::loadBatchActions();
        $res->add(new Batch\UploadSSHKeyAction($this));
        $res->add(new Batch\DeleteSSHKeyAction($this));
        
        return $res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAuthorized()
    {
        return $this->isGranted(Privilege::ADMIN);
    }
}
