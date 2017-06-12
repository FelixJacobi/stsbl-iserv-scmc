<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Admin/ServerAdmin.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Admin;

use Doctrine\ORM\EntityRepository;
use IServ\AdminBundle\Admin\AbstractAdmin;
use IServ\CrudBundle\Mapper\FormMapper;
use IServ\CrudBundle\Mapper\ListMapper;
use IServ\CrudBundle\Mapper\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

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
 * Server Management
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class ServerAdmin extends AbstractAdmin
{
    /**
     * {@inheritdoc}
     */
    protected function configure() 
    {
        $this->title = _('Servers');
        $this->itemTitle = _('Server');
        $this->id = 'scmc_server';
        $this->routesPrefix = 'admin/scmc/server';
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureFormFields(FormMapper $formMapper) 
    {
        $formMapper
            ->add('host', null, [
                'label' => _('Host'),
                'query_builder' => function (EntityRepository $er) {
                    $subQb = $er->createQueryBuilder('s');
            
                    $subQb
                        ->resetDqlParts()
                        ->select('h')
                        ->from('StsblSchoolCertificateManagerConnectorBundle:Server', 's')
                        ->where('s.host = h.name')
                    ;
                
                    return $er->createQueryBuilder('h')
                        ->where($subQb->expr()->not($subQb->expr()->exists($subQb)))
                        ->orderBy('h.name', 'ASC')
                    ;
                }
            ])
            ->add('tomcatType', ChoiceType::class, [
                'label' => _('Tomcat version'),
                'choices' => [
                    _('Tomcat 6') => 'tomcat6',
                    _('Tomcat 7') => 'tomcat7',
                    _('Tomcat 8') => 'tomcat8',
                ]
            ])
            ->add('webDomain', null, ['label' => _('Domain')])
            ->add('group', null, ['label' => _('Assigned group')])
            ->add('sshAccount', null, ['label' => _('SSH account')])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureListFields(ListMapper $listMapper) 
    {
        $listMapper
            ->addIdentifier('host', null, ['label' => _('Host')])
            ->add('webDomain', null, ['label' => _('Domain')])
            ->add('group', null, ['label' => _('Assigned group')])
            ->add('sshAccount', null, ['label' => _('SSH account'), 'responsive' => 'desktop'])
            ->add('tomcatType', null, ['label' => _('Tomcat version'), 'responsive' => 'desktop'])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureShowFields(ShowMapper $showMapper) 
    {
        $showMapper
            ->add('host', null, ['label' => _('Host')])
            ->add('tomcatType', null, ['label' => _('Tomcat version')])
            ->add('webDomain', null, ['label' => _('Domain')])
            ->add('group', null, ['label' => _('Assigned group')])
            ->add('sshAccount', null, ['label' => _('SSH account')])
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
}
