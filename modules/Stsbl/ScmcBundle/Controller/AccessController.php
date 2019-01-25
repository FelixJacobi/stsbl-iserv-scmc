<?php declare(strict_types = 1);

namespace Stsbl\ScmcBundle\Controller;

use IServ\CoreBundle\Controller\AbstractPageController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stsbl\ScmcBundle\Entity\Server;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Access list for the school certificate manager connector
 *
 * ALL routes in this controller needs a different prefix than scmc_
 * to prevent triggering of ResponseListener!
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("cgi", schemes="https")
 * @Security("is_granted('PRIV_SCMC_ACCESS_LIST')")
 */
class AccessController extends AbstractPageController
{
    /**
     * List available server
     *
     * @Route("", name="access_scmc_index")
     * @Security("is_granted('PRIV_SCMC_ACCESS_LIST')")
     * @Template()
     */
    public function indexAction(): array
    {
        $this->addBreadcrumb(_('Certificate Grade Input'));

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $qb = $this->getDoctrine()->getRepository(Server::class)->createQueryBuilder('s');

        $qb
            ->select('s')
            ->where($qb->expr()->orX(
                $qb->expr()->in('s.group', ':groups'),
                $qb->expr()->isNull('s.group')
            ))
            ->setParameter('groups', $this->getUser()->getGroups()->toArray())
        ;

        return [
            'servers' => $qb->getQuery()->getResult(),
            'help' => 'https://it.stsbl.de/documentation/mods/stsbl-iserv-scmc',
        ];
    }
}
