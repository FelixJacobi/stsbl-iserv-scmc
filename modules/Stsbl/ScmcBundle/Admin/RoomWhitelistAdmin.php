<?php

namespace Stsbl\ScmcBundle\Admin;

use Doctrine\ORM\EntityRepository;
use IServ\AdminBundle\Admin\AbstractAdmin;
use IServ\CoreBundle\Service\Logger;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Mapper\AbstractBaseMapper;
use IServ\CrudBundle\Mapper\FormMapper;
use Stsbl\ScmcBundle\Entity\Room;
use Stsbl\ScmcBundle\Security\Privilege;
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
 * Room whitelist Admin
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class RoomWhitelistAdmin extends AbstractAdmin
{
    use ScmcAdmTrait;

    /**
     * @var Logger
     */
    private $logger;
    
    /**
     * {@inheritdoc}
     */
    protected function configure() 
    {
        parent::configure();
        
        $this->title = _('Allowed Rooms');
        $this->itemTitle = _('Room');
        $this->id = 'scmc_rooms';
        $this->options['help'] = 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc';
        $this->templates['crud_index'] = 'StsblSchoolCertificateManagerConnectorBundle:Crud:admin_scmc_rooms_index.html.twig';
    }
    
    /**
     * Set logger
     * 
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAllowedToView(CrudInterface $object = null, UserInterface $user = null) 
    {
        // disable show action, it is useless here
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isAllowedToAdd(UserInterface $user = null) 
    {
        return true;
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
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function configureFields(AbstractBaseMapper $mapper) 
    {
        $options = [
            'label' => _('Room'),
        ];
        
        if ($mapper instanceof FormMapper) {
            $options['query_builder'] = function (EntityRepository $er) {
                $subQb = $er->createQueryBuilder('sr');
            
                $subQb
                    ->resetDqlParts()
                    ->select('sr')
                    ->from('StsblSchoolCertificateManagerConnectorBundle:Room', 'sr')
                    ->where($subQb->expr()->eq('sr.room', 'r.id'))
                ;
                
                return $er->createQueryBuilder('r')
                    ->where($subQb->expr()->not($subQb->expr()->exists($subQb)))
                    ->orderBy('r.name', 'ASC')
                ;
            };
        }
        
        $mapper->add('room', null, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function getRoutePattern($action, $id, $entityBased = true)
    {
        if ('index' === $action) {
            return sprintf('/%s%s', $this->routesPrefix, 'scmc/rooms');
        } else if ('batch' === $action || 'batch/confirm' === $action) {
            return sprintf('/%s%s/%s', $this->routesPrefix, 'scmc/rooms', $action);
        } else {
            return sprintf('/%s%s/%s', $this->routesPrefix, 'scmc/room', $action);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    protected function buildRoutes() 
    {
        parent::buildRoutes();
        
        $this->routes[self::ACTION_INDEX]['_controller'] = 'StsblSchoolCertificateManagerConnectorBundle:Admin:roomIndex';
    }
    
    /**
     * {@inheritdoc}
     */
    public function prepareBreadcrumbs() 
    {
        $ret = parent::prepareBreadcrumbs();
        $ret[_('Certificate Management')] = $this->router->generate('admin_scmc');
        return $ret;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAuthorized() 
    {
        return $this->isGranted(Privilege::ADMIN);
    }
    
    /* LOGGING */
    
    /**
     * {@inheritdoc}
     */
    public function postPersist(CrudInterface $object) 
    {
        /* @var $object Room */
        $this->logger->writeForModule(sprintf('Raum "%s" zur Raumliste hinzugefügt', (string)$object->getRoom()), 'School Certificate Manager Connector');
        $this->newConfig();
    }
    
    /**
     * {@inheritdoc}
     */
    public function postRemove(CrudInterface $object) 
    {
        /* @var $object Room */
        $this->logger->writeForModule(sprintf('Raum "%s" aus der Raumliste entfernt', (string)$object->getRoom()), 'School Certificate Manager Connector');
        $this->newConfig();
    }
}
