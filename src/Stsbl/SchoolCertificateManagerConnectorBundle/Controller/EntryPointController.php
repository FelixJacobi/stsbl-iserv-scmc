<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/EntryPointController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Stsbl\SchoolCertificateManagerConnectorBundle\Traits\SecurityTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * School Certificate Manager Connector Entry Point
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license GNU General Public License <http://gnu.org/licenses/gpl-3.0>
 * @Route("scmc")
 */
class EntryPointController extends PageController {
    use SecurityTrait;
    
    /**
     * Check if user is already authentificated and redirect to index or login
     * 
     * @param Request $request
     * @Route("", name="scmc_forward")
     */
    public function forwardAction(Request $request)
    {
        if(!$this->isManager()) {
            throw $this->createAccessDeniedException("You don't have the privileges to access the connector.");
        }
        
        if ($this->get('stsbl.scmc.service.session')->isAuthentificated()) {
            return $this->forward('StsblSchoolCertificateManagerConnectorBundle:Management:index');
        } else {
            return $this->forward('StsblSchoolCertificateManagerConnectorBundle:Security:login');
        }
    }
}
