<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Traits/MasterPasswordTrait.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Traits;

/**
 * Common functions for checking privileges in Controller
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license GNU General Public License <http://gnu.org/licenses/gpl-3.0>
 */
trait SecurityTrait {
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
