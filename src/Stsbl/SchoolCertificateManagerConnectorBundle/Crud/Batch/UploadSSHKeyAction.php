<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Crud/Batch/UploadSSHKeyAction.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Crud\Batch;

use Doctrine\Common\Collections\ArrayCollection;
use IServ\CrudBundle\Crud\Batch\AbstractBatchAction;
use IServ\CrudBundle\Crud\Batch\FormExtendingBatchActionInterface;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\CrudBundle\Entity\FlashMessageBag;
use Stsbl\SchoolCertificateManagerConnectorBundle\Admin\ServerAdmin;
use Stsbl\SchoolCertificateManagerConnectorBundle\Entity\Server;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\User\UserInterface;
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
 * Batch action for uploading SSH keys
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licneses/MIT>
 */
class UploadSSHKeyAction extends AbstractBatchAction implements FormExtendingBatchActionInterface
{
    /**
     * @var ServerAdmin
     */
    protected $crud;

    /**
     * {@inheritdoc}
     */
    public function execute(ArrayCollection $entities)
    {
        
    }
    
    /**
     * {@inheritdoc}
     */
    public function finalizeForm(FormInterface $form) 
    {
        $form
            ->add('key', FileType::class, [
                'label' => _('SSH key'),
                'constraints' => [new NotBlank(['message' => _('Please select a SSH key file.')])],
            ])
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getConfirmClass() 
    {
        return 'success';
    }

    /**
     * {@inheritdoc}
     */    
    public function getLabel() 
    {
        return _('Upload SSH key');
    }

    /**
     * {@inheritdoc}
     */
    public function getListIcon()
    {
        return 'pro-keys';
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName() 
    {
        return 'upload_ssh_key';
    }

    /**
     * {@inheritdoc}
     */
    public function handleFormData(array $data)
    {
        $bag = new FlashMessageBag();

        if (!$data['key'] instanceof UploadedFile) {
            $bag->addMessage('error', _('You must select a SSH key for uploading.'));
            return $bag;
        }
        /* @var $servers Server */
        $servers = $data['multi'];
        foreach ($servers as $server) {
            $bag->addAll($this->crud->getScmcAdm()->storeKey($server, $data['key']));
            $this->crud->getLogger()->writeForModule(sprintf('Neuen SSH-Schlüssel für Zeugnisserver "%s" hochgeladen', (string)$server->getHost()), 'School Certificate Manager Connector');
            $bag->addMessage('success', __('Uploaded new SSH key for %s.', (string)$server->getHost()));
        }

        return $bag;
    }

    /**
     * {@inheritdoc}
     */
    public function isAllowedToExecute(CrudInterface $object, UserInterface $user)
    {
        return $this->crud->getAuthorizationChecker()->isGranted('PRIV_SCMC_ADMIN');
    }

}
