<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Command/MasterPasswordCommand.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Command;

use Stsbl\SchoolCertificateManagerConnectorBundle\Util\Password as PasswordUtil;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \PDO;
use \RuntimeException;

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
 * Util to manage SCMC sessions
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class SessionCommand extends ContainerAwareCommand
{
    use CommonTrait;
    
    const MASTERPASSWORD_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.pwd';
    
    const SALT_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.salt';
    
    const PASSWD_FILE = '/etc/stsbl/scmcpasswd';
    
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
        $this->initalizeShell();
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
            throw new \RuntimeException('Unknown action "'.$action.'".');
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
        if (!isset($_SERVER['SCMC_MASTERPW']) or !isset($_SERVER['SCMC_ACT']) or !isset($_SERVER['SESSPW']) or !isset($_SERVER['SCMC_USERPW'])) {
            throw new \RuntimeException('Environment variables are missing.');
        }
        $suppliedMasterPassword = $_SERVER['SCMC_MASTERPW']; 
        $act = $_SERVER['SCMC_ACT'];
        $suppliedUserPassword = $_SERVER['SCMC_USERPW'];
        
        $this->shell->exec('/usr/lib/iserv/scmc_auth_level', [$act]);
        $shellOutput = $this->shell->getOutput();
        $authLevel = array_shift($shellOutput);
        
        if (!preg_match('(admin|user)', $authLevel)) {
          throw new \RuntimeException('User is not logged in via sessauthd.');    
        }
        
        if (empty($suppliedMasterPassword)) {
            throw new \RuntimeException('Master password is missing.');
        }
        
        if (empty($act)) {
            throw new \RuntimeException('Account is missing.');
        }
        
        $privilegeDB = $this->getIServDBConnection();
        $statement = $privilegeDB->prepare("SELECT count(*) FROM users_priv WHERE Act = :act AND privilege = 'scmc_access_frontend'");
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
        $suppliedMasterPasswordHash = PasswordUtil::generateHash($suppliedMasterPassword, $masterPasswordSalt, 11);
        
        if ($suppliedMasterPasswordHash !== $masterPasswordHash) {
            $output->writeln('Wrong');
            return;
        }
        
        $passwdFile = new \SplFileObject(self::PASSWD_FILE, 'r');
        $userPasswordHash = null;
        $userPasswordSalt = null;
        
        while (!$passwdFile->eof()) {
            $data = $passwdFile->fgetcsv();
            if($data[0] == $act) {
                $userPasswordHash = $data[1];
                $userPasswordSalt = $data[2];
                break;
            }
        }
        
        unset($passwdFile);
        
        if ($userPasswordHash == null || $userPasswordSalt == null) {
            throw new \RuntimeException('$userPassword and $userPasswordHash must not be null.');
        }
        
        $suppliedUserPasswordHash = PasswordUtil::generateHash($suppliedUserPassword, $userPasswordSalt, 11);
        
        if ($userPasswordHash !== $suppliedUserPasswordHash) {
            $output->writeln('Wrong UserPassword');
            return;
        }
        
        $shell = $this->shell->exec('/usr/bin/env', ['pwgen', '-n', '-s', '60', '1']);
        $shellOutput = $this->shell->getOutput();
        $sessionPassword = array_shift($shellOutput);
         
        $salt = PasswordUtil::generateSalt();
        $sessionPasswordHash = PasswordUtil::generateHash($sessionPassword, $salt, 11);
        
        $shell = $this->shell->exec('/usr/bin/env', ['pwgen', '-n', '-s', '60', '1']);
        $shellOutput = $this->shell->getOutput();
        $sessionToken = array_shift($shellOutput);
        
        $sessionDB = $this->getSessionDBConnection();
        $sessionDB->beginTransaction();
        try {
            $statement = $sessionDB->prepare("INSERT INTO scmc_sessions (sessiontoken, sessionpw, sessionpwsalt, act, created) VALUES (:sessionToken, :sessionPasswordHash, :sessionPasswordSalt, :account, now());", array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $statement->execute([':sessionToken' => $sessionToken, ':sessionPasswordHash' => $sessionPasswordHash, ':sessionPasswordSalt' => $salt, ':account' => $act]);
        } catch (\PDOException $e) {
            $sessionDB->rollBack();
            throw new \RuntimeException('Error during executing statement: '.$e->getMessage());
        }
        
        $sessionDB->commit();
        
        $output->writeln('SESSDATA:'.$sessionToken.','.$sessionPassword.','.$salt);
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
        
        $statement->execute([':sessionToken' => $sessionToken, ':sessionPasswordHash' => $sessionPasswordHash, ':account' => $act]);
        
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
}
