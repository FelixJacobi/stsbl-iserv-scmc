<?php
// Stsbl/SchoolCertificateManagerConnectorBundle/Crud/Batch/DeleteSSHKeyAction.php
namespace Stsbl\ScmcBundle\Crud\Batch;

use Doctrine\Common\Collections\ArrayCollection;
use IServ\CrudBundle\Crud\Batch\AbstractBatchAction;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Entity\FlashMessageBag;
use Stsbl\ScmcBundle\Entity\Server;
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
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licneses/MIT>
 */
class DeleteSSHKeyAction extends AbstractBatchAction
{
    /**
     * {@inheritdoc}
     */
    public function isAllowedToExecute(CrudInterface $object, UserInterface $user)
    {
        return $this->crud->getAuthorizationChecker()->isGranted('PRIV_SCMC_ADMIN');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'delete_ssh_key';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmClass()
    {
        return 'danger';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return _('Delete SSH key');
    }

    /**
     * {@inheritdoc}
     */
    public function getListIcon()
    {
        return 'pro-disk-remove';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ArrayCollection $entities)
    {
        $bag = new FlashMessageBag();
        /* @var $servers Server */
        foreach ($entities as $server) {
            $bag->addAll($this->crud->getScmcAdm()->deleteKey($server));
            $this->crud->getLogger()->writeForModule(sprintf('SSH-Schlüssel für Zeugnisserver "%s" gelöscht', (string)$server->getHost()), 'School Certificate Manager Connector');
            $bag->addMessage('success', __('Deleted SSH key for %s.', (string)$server->getHost()));
        }

        return $bag;
    }
}