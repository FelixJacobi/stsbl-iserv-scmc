<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Command/MasterPasswordCommand.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Command;

use IServ\CoreBundle\Service\Shell;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \PDO;

/**
 * Util to manage SCMC sessions
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license GNU General Public License <http://gnu.org/licenses/gpl-3.0>
 */
class SessionCommand extends ContainerAwareCommand {
    const MASTERPASSWORD_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.pwd';
    
    const SALT_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.salt';
    
    /**
     * @var Shell
     */
    private $shell;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('stsbl:scmc:session')
            ->setDescription('Manage SCMC sessions.')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('action', 'a', InputOption::VALUE_REQUIRED)
                ))
            );
    }
    
    /**
     * {@inheritdoc}
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var $shell \iServ\CoreBundle\Service\Shell
         */
        $shell = $this->getContainer()->get('iserv.shell');
        $this->shell = $shell;
    }

        /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getOption('action');
        if ($action == 'open') {
            $this->open($input, $output);
        } else if ($action == 'close') {
            $this->close($input, $output);
        } else {
            throw new RuntimeException('Unknown action "'.$action.'".');
        }
    }
    
    /**
     * Opens a new session for the given account if the correct master password is given.
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function open(InputInterface $input, OutputInterface $output)
    {
        if (!isset($_SERVER['SCMC_MASTERPW']) or !isset($_SERVER['SCMC_ACT'])) {
            throw new \RuntimeExecption('Environment variables are missing.');
        }
        $suppliedMasterPassword = $_SERVER['SCMC_MASTERPW']; 
        $act = $_SERVER['SCMC_ACT'];
        
        if (empty($suppliedMasterPassword)) {
            throw new \RuntimeException('Master password is missing.');
        }
        
        if (empty($act)) {
            throw new \RuntimeException('Account is missing.');
        }
        
        $privilegeDB = $this->getIServDBConnection();
        $statement = $privilegeDB->prepare("SELECT count(*) FROM members WHERE actuser = :act AND actgrp IN (SELECT act FROM privileges_assign WHERE privilege = 'scmc_access_frontend')");
        $statement->bindParam(':act', $act, \PDO::PARAM_STR);
        $statement->execute();
        if ($statement->fetchColumn() < 1) {
            throw new \RuntimeException('User '.$act.' has not enough privileges to authentificate.');
        }
        
        if (filesize(self::MASTERPASSWORD_FILE) == 0) {
            throw new \RuntimeException('Masterpassword file is empty!');
        }
        
        $masterPasswordHash = file_get_contents(self::MASTERPASSWORD_FILE);
        $masterPasswordSalt = file_get_contents(self::SALT_FILE);
        
        $hashOptions = [
            'cost' => 11,
            'salt' => $masterPasswordSalt,
        ];
                
        $suppliedMasterPasswordHash = password_hash($suppliedMasterPassword, PASSWORD_BCRYPT, $hashOptions);
        
        if ($suppliedMasterPasswordHash !== $masterPasswordHash) {
            $output->writeln('Wrong');
            return;
        }
        
        $shell = $this->shell->exec('/usr/bin/env', ['pwgen', '-n', '-s', '60', '1']);
        $password = $this->shell->getOutput();
                
        $sessionPassword = $password[0];
        $sessionPasswordSalt = base64_encode(mcrypt_create_iv(22, MCRYPT_DEV_URANDOM));
        $sessionPasswordHashOptions = [
            'cost' => 11,
            'salt' => $sessionPasswordSalt
        ];
        $sessionPasswordHash = password_hash($sessionPassword, PASSWORD_BCRYPT, $sessionPasswordHashOptions);
        
        $shell = $this->shell->exec('/usr/bin/env', ['pwgen', '-n', '-s', '60', '1']);
        $password = $this->shell->getOutput();
        $sessionToken = $password[0];
        
        $sessionDB = $this->getSessionDBConnection();
        $sessionDB->beginTransaction();
        try {
            $statement = $sessionDB->prepare("INSERT INTO scmc_sessions (sessiontoken, sessionpw, sessionpwsalt, act, created) VALUES (:sessionToken, :sessionPasswordHash, :sessionPasswordSalt, :account, now());", array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $statement->execute([':sessionToken' => $sessionToken, ':sessionPasswordHash' => $sessionPasswordHash, ':sessionPasswordSalt' => $sessionPasswordSalt, ':account' => $act]);
        } catch (\PDOException $e) {
            $sessionDB->rollBack();
            throw new \RuntimeException('Error during executing statement: '.$e->getMessage());
        }
        
        $sessionDB->commit();
        
        $output->writeln('SESSDATA:'.$sessionToken.','.$sessionPassword);
    }
    
    /**
     * Destroys an opened session
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function close(InputInterface $input, OutputInterface $output)
    {
        if (!isset($_SERVER['SCMC_ACT']) or !isset($_SERVER['SCMC_SESSIONPW']) or !isset($_SERVER['SCMC_SESSIONTOKEN'])) {
            throw new \RuntimeException('Environment variables are missing.');
        }
        
        $sessionToken = $_SERVER['SCMC_SESSIONTOKEN'];
        $sessionPassword = $_SERVER['SCMC_SESSIONPW'];
        $act = $_SERVER['SCMC_ACT'];
        
        if (empty($sessionToken)) {
            throw new \RuntimeException('Session token is missing.');
        }
        
        if (empty($sessionPassword)) {
            throw new \RuntimeException('Session password is missing.');
        }
        
        if(empty($act)) {
            throw new \RuntimeException('Account is missing.');
        }
        
        $sessionDB = $this->getSessionDBConnection(); 
        
        $statement = $sessionDB->prepare('SELECT count(*) FROM scmc_sessions WHERE sessiontoken = :sessionToken AND act = :account');
        $statement->execute([':sessionToken' => $sessionToken, ':account' => $act]);
        
        if ($statement->fetchColumn() < 1) {
            throw new \RuntimeException("Couldn't find session with that token or/and account.");
        }
        
        $statement = $sessionDB->prepare('SELECT sessionpwsalt FROM scmc_sessions WHERE sessiontoken = :sessionToken AND act = :account');
        
        $statement->execute([':sessionToken' => $sessionToken, ':account' => $act]);
        
        $sessionPasswordSalt = $statement->fetchColumn();
        
        $sessionPasswordHashOptions = [
            'cost' => 11,
            'salt' => $sessionPasswordSalt
        ];
        $sessionPasswordHash = password_hash($sessionPassword, PASSWORD_BCRYPT, $sessionPasswordHashOptions);
        
        $statement = $sessionDB->prepare('SELECT count(*) FROM scmc_sessions WHERE sessiontoken = :sessionToken AND sessionpw = :sessionPasswordHash AND act = :account');
        
        $statement->execute(array(':sessionToken' => $sessionToken, ':sessionPasswordHash' => $sessionPasswordHash, ':account' => $act));
        
        if ($statement->fetchColumn() < 1) {
            throw new \RuntimeException("Session password hash does not match.");
        }
        
        $sessionDB->beginTransaction();
        try {
            $statement = $sessionDB->prepare('DELETE FROM scmc_sessions WHERE sessiontoken = :sessionToken AND sessionpw = :sessionPasswordHash AND act = :account');
            $statement->execute(array(':sessionToken' => $sessionToken, ':sessionPasswordHash' => $sessionPasswordHash, ':account' => $act));
        } catch (\PDOException $e) {
            $sessionDB->rollBack();
            throw new \RuntimeException('Error during executing PDOStatement: '.$e->getMessage());
        }
        
        $sessionDB->commit();
        
        // until here no errors, assume it worked
        $output->writeln('True');
    }
    
    /**
     * Creates a new connection to the session database table
     * 
     * @return \Doctrine\DBAL\Connection 
     */
    private function getSessionDBConnection()
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
    private function getIServDBConnection()
    {
        $connectionFactory = $this->getContainer()->get('doctrine.dbal.connection_factory');
        $iservDB = $connectionFactory->createConnection(['pdo' => new \PDO("pgsql:dbname=iserv", 'symfony')]);
        
        return $iservDB;
    }
}
