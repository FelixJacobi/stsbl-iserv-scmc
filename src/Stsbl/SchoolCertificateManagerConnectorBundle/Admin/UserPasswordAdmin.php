<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Admin/UserPasswordAdmin.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Admin;

use Doctrine\ORM\NoResultException;
use IServ\AdminBundle\Admin\AbstractAdmin;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Mapper\ListMapper;
use IServ\CrudBundle\Mapper\ShowMapper;
use IServ\CrudBundle\Table\Filter;
use IServ\CrudBundle\Table\ListHandler;
use Symfony\Component\Security\Core\User\UserInterface;

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
 * User Password Management
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class UserPasswordAdmin extends AbstractAdmin
{
    /**
     * {@inheritdoc}
     */
    protected function configure() 
    {
        $this->title = _('User passwords');
        $this->itemTitle = _('User');
        $this->id = 'scmc_userpassword';
        $this->routesPrefix = 'admin/scmc/userpassword';

        $this->options['help'] = 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc';
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureListFields(ListMapper $listMapper) 
    {
        $listMapper
            ->addIdentifier('username', null, ['label' => _('Account')])
            ->add('firstname', null, ['label' => _('Firstname'), 'responsive' => 'desktop'])
            ->add('lastname', null, ['label' => _('Lastname')])
        ;
    }

    /**
     * {@inheritdoc}
     */    
    public function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->add('username', null, ['label' => _('Account')])
            ->add('firstname', null, ['label' => _('Firstname')])
            ->add('lastname', null, ['label' => _('Lastname')])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureListFilter(ListHandler $listHandler)
    {
        $listHandler
            ->addListFilter(new Filter\ListSearchFilter('search', ['username', 'firstname', 'lastname']))
            ->addListFilter((new Filter\ListAssociationFilter(_('Groups'), 'groups', 'IServCoreBundle:Group', 'name', 'account'))->setName('groups')->setPickerOptions(array('data-live-search' => 'true')))
        ;
    }
    
    /**
     * {@inheritdoc}
     */    
    public function isAllowedToAdd(UserInterface $user = null)
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */    
    public function isAllowedToEdit(CrudInterface $object = null, UserInterface $user = null)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */    
    public function isAllowedToDelete(CrudInterface $object = null, UserInterface $user = null)
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function loadBatchActions()
    {
        $res = parent::loadBatchActions();
        // we do not need delete here
        $res->remove('delete');
        
        return $res;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRoutePattern($action, $id, $entityBased = true)
    {
        if ('index' === $action) {
            return sprintf('/%s', $this->routesPrefix.'s');
        } else if ('show' === $action) {
            return sprintf('/%s/%s/%s', $this->routesPrefix, $action, '{id}');
        } else {
            return parent::getRoutePattern($action, 'entry', $entityBased);
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
    public function getShowActions(CrudInterface $item)
    {
        /* @var $item \IServ\CoreBundle\Entity\User */
        $links = parent::getShowActions($item);

        $links['setuserpassword'] = array($this->getRouter()->generate('admin_scmc_set_user_password', ['user' => $item->getUsername()]), _('Set user password'), 'pro-keys', 'btn-primary');
        
        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $qb = $this->getObjectManager()->createQueryBuilder($this->class);
        $qb->select('p')
            ->from('StsblSchoolCertificateManagerConnectorBundle:UserPassword', 'p')
            ->where('p.act = :user')
            ->setMaxResults(1)
            ->setParameter('user', $item)
        ;
        
        try {
            /* @var $userPasswordObject \Stsbl\SchoolCertificateManagerConnectorBundle\Entity\UserPassword */
            $userPasswordObject = $qb->getQuery()->getSingleResult();
            $hasPassword = $userPasswordObject->getPassword();
        } catch (NoResultException $e) {
            // assume that user has no password, if he is not listed in table
            $hasPassword = false;
        }
        
        if ($hasPassword) {
            $links['deleteuserpassword'] = array($this->getRouter()->generate('admin_scmc_delete_user_password', ['user' => $item->getUsername]), _('Delete user password'), 'remove-circle');
        }
        
        return $links;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAuthorized()
    {
        return $this->isGranted('PRIV_SCMC_ADMIN');
    }
}
