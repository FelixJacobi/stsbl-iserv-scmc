<?php
// src/Stsbl/SchooLCertificateManagerConnectorBundle/Command/CommonTrait.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Command;

use IServ\CoreBundle\Service\Shell;

/**
 * Common database functions for scmc symfony commands executed as root
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
trait CommonTrait {
    /**
     * @var Shell
     */
    protected $shell;
    
    /**
     * Initalizes the shell inside the trait.
     */
    protected function initalizeShell()
    {
        /**
         * @var $shell \IServ\CoreBundle\Service\Shell
         */
        $shell = $this->getContainer()->get('iserv.shell');
        $this->shell = $shell;
    }


    /**
     * Creates a new connection to the session database table
     * 
     * @return \Doctrine\DBAL\Connection 
     */
    protected function getSessionDBConnection()
    {
        $connectionFactory = $this->getContainer()->get('doctrine.dbal.connection_factory');
        $sessionDB = $connectionFactory->createConnection(['pdo' => new \PDO("pgsql:dbname=iserv", 'scmc_session')]);

        return $sessionDB;
    }
    
    /**
     * Creates a new connection to the iserv database as symfony user
     * 
     * @return \Doctrine\DBAL\Connection 
     */
    protected function getIServDBConnection()
    {
        $connectionFactory = $this->getContainer()->get('doctrine.dbal.connection_factory');
        $iservDB = $connectionFactory->createConnection(['pdo' => new \PDO("pgsql:dbname=iserv", 'symfony')]);
        
        return $iservDB;
    }
    
    /**
     * Checks if current account is logged in as an admin via sessauthd and has privilege scmc_admin
     */
    protected function authAdmin()
    {
        if(empty($_SERVER['SCMC_ACT'])) {
            throw new \RuntimeException('Account is missing.');
        }
            
        if(empty($_SERVER['SESSPW'])) {
            throw new \RuntimeException('Session password is missing.');
        }
        
        if ($this->shell == null) {
            throw new \RuntimeException('$shell is null. Did you forget to call initalizeShell()?');
        }
            
        $act = $_SERVER['SCMC_ACT'];
            
        $this->shell->exec('/usr/lib/iserv/scmc_auth_level', [$act]);
        $shellOutput = $this->shell->getOutput();
        $authLevel = array_shift($shellOutput);
        
        if($authLevel !== 'admin') {
            throw new \RuntimeException('User must be authentificated as admin.');
        }
            
        $IServDB = $this->getIServDBConnection();
            
        $statement = $IServDB->prepare('SELECT count(*) FROM users_priv WHERE privilege = :priv AND act = :user');
        $statement->execute(['priv' => 'scmc_admin', 'user' => $act]);
            
        $result = $statement->fetchColumn();
            
        if ($result < 1) {
            throw new \RuntimeException('User has not enough privileges.');
        }
    }
    
    /**
     * Checks if current account is logged in as user
     */
    protected function authUser()
    {
        
    }
}
