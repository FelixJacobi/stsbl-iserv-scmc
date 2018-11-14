<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/StsblSchoolCertificateManagerConnectorBundle.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle;

use IServ\CoreBundle\Routing\AutoloadRoutingBundleInterface;
use Stsbl\SchoolCertificateManagerConnectorBundle\DependencyInjection\StsblSchoolCertificateManagerConnectorExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license GNU General Public License <http://gnu.org/licenses/gpl-3.0>
 */
class StsblSchoolCertificateManagerConnectorBundle extends Bundle implements AutoloadRoutingBundleInterface
{
    public function getContainerExtension()
    {
        return new StsblSchoolCertificateManagerConnectorExtension();
    }
}
