<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Command/UpdateMasterPasswordCommand.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Command;

use IServ\CoreBundle\Service\Shell;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
 * Util to update the scmc master password
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class MasterPasswordCommand extends ContainerAwareCommand 
{
    use CommonTrait;
    
    const MASTERPASSWORD_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.pwd';
    
    const SALT_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.salt';
    
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
    protected function configure()
    {
        $this
            ->setName('stsbl:scmc:masterpassword')
            ->setDescription('Update the master password for accessing the SCMC frontend.')
            ->setDefinition(
                new InputDefinition(array(
                    new InputOption('action', 'a', InputOption::VALUE_REQUIRED)
                ))
            )
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getOption('action');
        if ($action == 'isEmpty') {
            $this->isEmpty($input, $output);
        } else if ($action == 'update') {
            $this->update($input, $output);
        } else {
            throw new \RuntimeException('Unknown action "'.$action.'".');
        }
    }
    
    /**
     * Update the master password with the values from the environment
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function update(InputInterface $input, OutputInterface $output)
    {
        try {
            // don't require SCMC_OLDMASTERPW to be set here, it would cause an exception if the password is changed for the first time.
            if (!isset($_SERVER['SCMC_NEWMASTERPW']) or !isset($_SERVER['SCMC_ACT']) or !isset($_SERVER['SESSPW'])) {
                throw new \RuntimeException('Environment variables are missing.');
            }
            
            if(empty($_SERVER['SCMC_NEWMASTERPW'])) {
                throw new \RuntimeException('New master password is missing.');
            }

            $this->authAdmin();
            
            $oldMasterPasswordHash = file_get_contents(self::MASTERPASSWORD_FILE);
            $oldMasterPasswordSalt = file_get_contents(self::SALT_FILE);
            
            $hashOptions = [
                'cost' => 11,
                'salt' => $oldMasterPasswordSalt,
            ];
            
            if (empty($_SERVER['SCMC_OLDMASTERPW']) && filesize(self::MASTERPASSWORD_FILE) !== 0) {
                $output->writeln('Wrong');
                return;
            }
            if (filesize(self::MASTERPASSWORD_FILE) !== 0) {
                $suppliedPasswordHash = password_hash($_SERVER['SCMC_OLDMASTERPW'], PASSWORD_BCRYPT, $hashOptions);
                if ($suppliedPasswordHash !== $oldMasterPasswordHash) {
                    $output->writeln('Wrong');
                    return;
                }
            }
            
            $newSalt = base64_encode(mcrypt_create_iv(22, MCRYPT_DEV_URANDOM));
            
            $saltFile = new \SplFileObject(self::SALT_FILE, 'w');
            $saltFile->fwrite($newSalt);
            
            $hashOptions['salt'] = $newSalt;
            $newMasterPasswordHash = password_hash($_SERVER['SCMC_NEWMASTERPW'], PASSWORD_BCRYPT, $hashOptions);
            
            $pwdFile = new \SplFileObject(self::MASTERPASSWORD_FILE, 'w');
            $pwdFile->fwrite($newMasterPasswordHash);
        
            $output->writeln('True');
        } catch (\RuntimeException $e) {
            // catch all exceptions
            $output->writeln('False');
        }
    }
    
    /**
     * Checks if master password is empty and write result to output.
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function isEmpty(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists(self::MASTERPASSWORD_FILE) || filesize(self::MASTERPASSWORD_FILE) === 0) {
            $output->writeln('True');
        } else {
            $output->writeln('False');
        }
    }
}
