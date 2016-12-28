<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Traits/MasterPasswordTrait.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Traits;

/**
 * Common functions for handling the master password
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
trait MasterPasswordTrait {
    /**
     * Checks via sudo, if the masterpassword is empty
     * 
     * @return bool
     */
    protected function isMasterPasswordEmpty()
    {
        $shell = $this->get('iserv.shell');
        $shell->exec('/usr/bin/sudo', array('/usr/lib/iserv/scmc_masterpassword_empty'));
        
        $ret = $shell->getOutput();
        
        if ($ret[0] == "True") {
            return true;
        } else if ($ret[0] == "False") {
            return false;
        } else {
            throw new \RuntimeException('Unexpected return from scmc_masterpassword_empty.');
        }
    }
    
    /**
     * Updates the master password
     * 
     * @param string $oldMasterPassword
     * @param string $newMasterPassword
     * @return bool|string
     */
    protected function updateMasterPassword($oldMasterPassword, $newMasterPassword)
    {
        $securityHandler = $this->get('iserv.security_handler');
        $sessionPassword = $securityHandler->getSessionPassword();
        // echo $sessionPassword;
        $act = $securityHandler->getToken()->getUser()->getUsername();
        /* @var $shell \IServ\CoreBundle\Service\Shell */
        $shell = $this->get('iserv.shell');
        $shell->exec('/usr/bin/sudo', array('/usr/lib/iserv/scmc_masterpassword_update'), null, array('SCMC_OLDMASTERPW' => $oldMasterPassword, 'SCMC_NEWMASTERPW' => $newMasterPassword, 'SCMC_ACT' => $act, 'SESSPW' => $sessionPassword));
        $output = $shell->getOutput();
        $ret = array_shift($output);
        var_dump($output);
        
        if ($ret == "True") {
            return true;
        } else if ($ret == "False") {
            return false;
        } else if ($ret == "Wrong") {
            return 'wrong';
        } else {
            throw new \RuntimeException('Unexpected return from scmc_masterpassword_update.');
        }
    }
}
