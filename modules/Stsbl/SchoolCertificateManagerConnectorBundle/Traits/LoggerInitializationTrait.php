<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Traits/LoggerInitalizationTrait.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Traits;

/**
 * Trait with common function to initialize the LoggerTrait from CoreBundle.
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
trait LoggerInitializationTrait
{
    /**
     * Initializes the logger
     */
    protected function initalizeLogger()
    {  
        // set module context for logging
        $this->logModule = 'School Certificate Manager Connector';
        
        $logger = $this->get('iserv.logger');
        $this->setLogger($logger);
    }
}
