<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Command/UserPasswordCommand.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Stsbl\SchoolCertificateManagerConnectorBundle\Entity\UserPassword;
use Stsbl\SchoolCertificateManagerConnectorBundle\Util\Password as PasswordUtil;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
 * Set, update and delete individual scmc user passwords
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class UserPasswordCommand extends ContainerAwareCommand {
    use CommonTrait;
    
    const PASSWD_FILE = '/etc/stsbl/scmcpasswd';
    
    
    
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('stsbl:scmc:userpassword')
            ->setDescription('Manage SCMC sessions.')
            ->setDefinition(
                new InputDefinition([
                    new InputOption('action', 'a', InputOption::VALUE_REQUIRED),
                    new InputOption('user', 'u', InputOption::VALUE_REQUIRED)
                ])
            );
    }
    
    /**
     * @var Registry
     */
    private $doctrine;
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getOption('action');
        if ($action == 'set') {
            $this->set($input, $output);
        } else if ($action == 'delete') {
            $this->delete($input, $output);
        } else {
            throw new \RuntimeException('Unknown action "'.$action.'".');
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->initalizeShell();
        /* @var $doctrine \Doctrine\Bundle\DoctrineBundle\Registry */
        $doctrine = $this->getContainer()->get('doctrine');
        $this->doctrine = $doctrine;
    }
    
    /**
     * Set a new password for a user
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function set(InputInterface $input, OutputInterface $output)
    {
        $user = $input->getOption('user');
        $this->authAdmin();
        
        if(!isset($_SERVER['SCMC_USERPW'])) {
            throw new \RuntimeException('Environment variables are missing.');
        }
        
        // delete old entry first
        $this->deleteEntry($user);
        
        // write new entry
        $password = $_SERVER['SCMC_USERPW'];
        $salt = PasswordUtil::generateSalt();
        $hash = PasswordUtil::generateHash($password, $salt, 11);
        $append = [$user, $hash, $salt];
        
        $passwdFileWrite = new \SplFileObject(self::PASSWD_FILE, 'a');
        //$size = $passwdFileWrite->getSize();
        $passwdFileWrite->fputcsv($append);
        /*if ($size == 0) {
            $passwdFileWrite->fwrite("\n");
        }*/
        unset($passwdFileWrite);
        
        // update database
        $doctrine = $this->doctrine;
        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $doctrine->getManager();
        /* @var $object \Stsbl\SchoolCertificateManagerConnectorBundle\Entity\User */
        $object = $em->find('StsblSchoolCertificateManagerConnectorBundle:UserPassword', $user);
        
        if (!is_null($object)) {
            $object->setPassword(true);
            $em->flush();
        } else {
            unset($object);
            /* @var $userRepo \Doctrine\Common\Persistence\ObjectRepository */
            $userRepo = $doctrine->getRepository ('IServCoreBundle:User');
            /* @var $iservUser \IServ\CoreVBundle\Entity\User */
            $iservUser = $userRepo->findOneByUsername($user);
            $object = new UserPassword();
            $object->setAct($iservUser);
            $object->setPassword(true);
            $em->persist($object);
            $em->flush();
        }
    }
    
    /**
     * Deletes an existing password for a user
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function delete(InputInterface $input, OutputInterface $output)
    {
        $user = $input->getOption('user');
        
        $this->authAdmin();
        $this->deleteEntry($user);
        
        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->doctrine->getManager();
        /* @var $object \Stsbl\SchoolCertificateManagerConnectorBundle\Entity\User */
        $object = $em->find('StsblSchoolCertificateManagerConnectorBundle:UserPassword', $user);
        $object->setPassword(false);
        $em->persist($object);
        $em->flush();
    }
    
    /**
     * Deletes the entry of a user from the passwd file
     * 
     * @param string $user
     */
    private function deleteEntry($user)
    {
        // check if user has already an entry
        $userHasEntry = false;
        // replace devil dots in account name
        $findExpression = '/'. str_replace('.', '\.', $user).'\,(.*)\,(.*)/';
        $passwdFileRead = new \SplFileObject(self::PASSWD_FILE, 'r');
        while (!$passwdFileRead->eof()) {
            $line = implode(',', $passwdFileRead->fgetcsv());
            if (preg_match($findExpression, $line)) {
                $userHasEntry = true;
                break;
            }
        }
        
        if ($userHasEntry) {
            // read all lines
            $content = file_get_contents(self::PASSWD_FILE);
            $lines = explode("\n", $content);
            $out = [];
            foreach ($lines as $line) {
                if (!preg_match($findExpression, trim($line))) {
                    if (!empty($line)) {
                        $out[] = trim($line)."\n";
                    }
                }
            }
            
            // write everything back to new file
            $passwdFileOverwrite = new \SplFileObject(self::PASSWD_FILE, 'w');
            foreach($out as $line) {
                $passwdFileOverwrite->fwrite($line);
            }
            unset($passwdFileOverwrite);
        }
    }
}
