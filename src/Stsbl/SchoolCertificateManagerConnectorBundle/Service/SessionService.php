<?php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Service;

use IServ\CoreBundle\Security\Core\SecurityHandler;
use IServ\CoreBundle\Service\Shell;

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
 * Service container for SCMC session management
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class SessionService 
{
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
     * Authentificate with master and user password
     * 
     * @param string $masterPassword
     * @param string $userPassword
     * @return string|bool
     */
    public function openSession($masterPassword, $userPassword)
    {
        $shell = $this->shell;
        $act = $this->securityHandler->getToken()->getUser()->getUsername();
        $sessionPassword = $this->securityHandler->getSessionPassword();
        $shell->exec('/usr/bin/sudo', ['/usr/lib/iserv/scmc_session_open'], null, ['SCMC_ACT' => $act, 'SCMC_MASTERPW' => $masterPassword, 'SESSPW' => $sessionPassword, 'SCMC_USERPW' => $userPassword]);
        
        $this->handleShellExitCode($shell);
        $output = $shell->getOutput();
        $ret = array_shift($output);
        
        if ($ret == "Wrong") {
            return 'wrong';
        } else if ($ret == "Wrong UserPassword") {
            return 'wrong userpassword';
        } else if (strpos($ret, 'SESSDATA:') === 0) {
            $sessionData = explode(',', str_replace('SESSDATA:', '', $ret));
 
            $this->securityHandler->getToken()->setAttribute('scmc_authentificated', true);
            $this->securityHandler->getToken()->setAttribute('scmc_sessiontoken', $sessionData[0]);
            $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', $sessionData[1]);
            $this->securityHandler->getToken()->setAttribute('scmc_salt', $sessionData[2]);
            
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
        $output = $shell->getOutput();
        $ret = array_shift($output);
        
        if ($ret == "True") {
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
