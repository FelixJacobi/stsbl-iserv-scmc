<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/SecurityTrait.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

/**
 * Common functions for checking privileges in Controller
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
trait SecurityTrait 
{
    /**
     * Checks if the user can access the connector
     * 
     * @return bool
     */
    protected function isManager()
    {
        return $this->isGranted('PRIV_SCMC_ACCESS_FRONTEND');
    }
    
    /**
     * Checks if the user is admin
     * 
     * @return bool
     */
    protected function isAdmin()
    {
        return $this->isGranted('PRIV_SCMC_ADMIN');
    }
}
