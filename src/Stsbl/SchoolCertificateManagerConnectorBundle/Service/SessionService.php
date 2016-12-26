<?php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Service;

use IServ\CoreBundle\Security\Core\SecurityHandler;
use IServ\CoreBundle\Service\Shell;

/**
 * Service container for SCMC session management
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license GNU General Public License <http://gnu.org/licenses/gpl-3.0>
 */
class SessionService {
    /**
     * @var SecurityHandler
     */
    private $securityHandler;
    
    /**
     * @var Shell
     */
    private $shell;
    
    /**
     * @var string
     */
    private $sessionToken = null;
    
    /**
     * @var string
     */
    private $sessionPassword = null;

    /**
     * The constructor
     * 
     * @param SecurityHandler $securityHandler
     * @param Shell $shell
     */
    public function __construct(SecurityHandler $securityHandler, Shell $shell)
    {
        $this->securityHandler = $securityHandler;
        $this->shell = $shell;
        
        $this->setSessionData();
    }
    
    /**
     * Checks if the user is authentificiated
     * 
     * @return bool
     */
    public function isAuthentificated()
    {
        if ($this->securityHandler->getToken()->hasAttribute('scmc_authentificated')) {
            $authentificated = $this->securityHandler->getToken()->getAttribute('scmc_authentificated');
        } else {
            $authentificated = false;
            $this->securityHandler->getToken()->setAttribute('scmc_authentificated', null);
        }
        
        return $authentificated;
    }

    /**
     * Authentificate with master password
     * 
     * @param string $masterPassword
     * @return bool
     */
    public function openSession($masterPassword)
    {
        $shell = $this->shell;
        $act = $this->securityHandler->getToken()->getUser()->getUsername();
        $sessionPassword = $this->securityHandler->getSessionPassword();
        $shell->exec('/usr/bin/sudo', ['/usr/lib/iserv/scmc_session_open'], null, ['SCMC_ACT' => $act, 'SCMC_MASTERPW' => $masterPassword, 'SESSPW' => $sessionPassword]);
        
        $this->handleShellExitCode($shell);
        $ret = $shell->getOutput();
        
        if ($ret[0] == "Wrong") {
            return false;
        } else if (strpos($ret[0], 'SESSDATA:') === 0) {
            $sessionData = explode(',', str_replace('SESSDATA:', '', $ret[0]));
 
            $this->securityHandler->getToken()->setAttribute('scmc_authentificated', true);
            $this->securityHandler->getToken()->setAttribute('scmc_sessiontoken', $sessionData[0]);
            $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', $sessionData[1]);
            
            // recall setSessionData to set password and token received from command
            $this->setSessionData();
            return true;
        } else {
            throw new \RuntimeException('Unexpected return from scmc_session_open.');
        }
    }
    
    /**
     * Closes an existing session
     * 
     * @return bool
     */
    public function closeSession()
    {
        $shell = $this->shell;
        $act = $this->securityHandler->getToken()->getUser()->getUsername();

        $shell->exec('/usr/bin/sudo', array('/usr/lib/iserv/scmc_session_close'), null, array('SCMC_ACT' => $act, 'SCMC_SESSIONTOKEN' => $this->sessionToken, 'SCMC_SESSIONPW' => $this->sessionPassword));
        
        $this->handleShellExitCode($shell);
        $ret = $shell->getOutput();
        
        if ($ret[0] == "True") {
            // delete data from session
            $this->securityHandler->getToken()->setAttribute('scmc_authentificated', null);
            $this->securityHandler->getToken()->setAttribute('scmc_sessiontoken', null);
            $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', null);
            return true;
        } else {
            throw new \RuntimeException('Failed to close session.');
        }
    }
    
    /**
     * Reads the session token and password from SecurityHandler and inject into the class
     */
    private function setSessionData()
    {
        if ($this->securityHandler->getToken()->hasAttribute('scmc_sessiontoken')) {
            $this->sessionToken = $this->securityHandler->getToken()->getAttribute('scmc_sessiontoken');
        }
        
        if ($this->securityHandler->getToken()->hasAttribute('scmc_sessionpassword')) {

            $this->sessionPassword = $this->securityHandler->getToken()->getAttribute('scmc_sessionpassword');
        }       
    }
    
    /**
     * Handles shell exit code
     * 
     * @param Shell $shell
     */
    private function handleShellExitCode(Shell $shell)
    {
        $exitCode = $shell->getExitCode();
        $errorOutput = $shell->getOutput();
        
        if (is_null($exitCode)) {
            throw new \RuntimeException('Shell does not returned an exit code.');
        }
        
        if ($exitCode !== 0) {
            if (array_count_values($errorOutput) > 0) {
                throw new \RuntimeException("Unexpected shell error:\n".implode("\n", $errorOutput));
            } else {
                throw new \RuntimeException('Unexpected shell error.');
            }
        }
    }
    
    /**
     * Returns session token
     * 
     * @return string
     */
    public function getSessionToken()
    {
        return $this->sessionToken;
    }
    
    /**
     * Returns session password
     * 
     * @return string
     */
    public function getSessionPassword()
    {
        return $this->sessionPassword;
    }
}
