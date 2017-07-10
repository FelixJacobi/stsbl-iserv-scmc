<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/AccessController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access list for the school certificate manager connector
 *
 * ALL routes in this controller needs a different prefix than scmc_
 * to prevent triggering of ResponseListener!
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("scmc/access")
 * @Security("is_granted('PRIV_SCMC_ACCESS_LIST')")
 */
class AccessController extends PageController
{
    /**
     * List available server
     *
     * @param Request $request
     * @return array
     * @Route("", name="access_scmc_index")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $this->addBreadcrumb(_('Certificate Grade Input'));

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $qb = $this->getDoctrine()->getRepository('StsblSchoolCertificateManagerConnectorBundle:Server')->createQueryBuilder('s');

        $qb
            ->select('s')
            ->where($qb->expr()->orX(
                $qb->expr()->in('s.group', $this->getUser()->getGroups()->toArray()),
                $qb->expr()->isNull('s.group')
            ))
        ;

        return ['servers' => $qb->getQuery()->getResult()];
    }
}
