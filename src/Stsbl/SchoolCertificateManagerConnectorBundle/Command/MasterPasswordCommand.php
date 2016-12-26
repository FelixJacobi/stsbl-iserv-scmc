<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Command/UpdateMasterPasswordCommand.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Command;

use IServ\CoreBundle\Service\Shell;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * util to update the scmc master password
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class MasterPasswordCommand extends ContainerAwareCommand {
    const MASTERPASSWORD_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.pwd';
    
    const SALT_FILE = '/var/lib/stsbl/scmc/auth/masterpassword.salt';
    
    /**
     * @var Shell
     */
    private $shell;
    
    /**
     * {@inheritdoc}
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var $shell \IServ\CoreBundle\Service\Shell
         */
        $shell = $this->getContainer()->get('iserv.shell');
        $this->shell = $shell;
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
            // don't require SCMC_OLDMASTERPW to be set here, it would cause a exception if the password is changed for the first time.
            if (!isset($_SERVER['SCMC_NEWMASTERPW']) or !isset($_SERVER['SCMC_ACT']) or !isset($_SERVER['SESSPW'])) {
                throw new \RuntimeException('Environment variables are missing.');
            }
            
            if(empty($_SERVER['SCMC_NEWMASTERPW'])) {
                throw new \RuntimeException('New master password is missing.');
            }

            if(empty($_SERVER['SCMC_ACT'])) {
                throw new \RuntimeException('Account is missing.');
            }
            
            if(empty($_SERVER['SESSPW'])) {
                throw new \RuntimeException('Session password is missing.');
            }
            
            $act = $_SERVER['SCMC_ACT'];
            
            $this->shell->exec('/usr/lib/iserv/scmc_auth_level', [$act]);
            $shellOutput = $this->shell->getOutput();
            $authLevel = array_shift($shellOutput);
            
            if($authLevel !== 'admin') {
                throw new \RuntimeException('User must be authentificated as admin.');
            } 
            
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
        if (filesize(self::MASTERPASSWORD_FILE) === 0) {
            $output->writeln('True');
        } else {
                $output->writeln('False');
        }
    }
}
