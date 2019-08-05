<?php declare(strict_types = 1);

namespace Stsbl\ScmcBundle\Controller;

use Doctrine\ORM\QueryBuilder;
use IServ\CoreBundle\Controller\AbstractPageController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/*
 * The MIT License
 *
 * Copyright 2018 Felix Jacobi.
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
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("/public/scmc")
 */
class PublicController extends AbstractPageController
{
    /**
     * Shows block information
     *
     * @Route("/block", name="public_scmc_block")
     * @Template()
     */
    public function blockAction(Request $request): Response
    {
        /* @var $qb QueryBuilder */
        $qb = $this->getDoctrine()->getRepository('IServRoomBundle:Room')->createQueryBuilder('r');
        /* @var $subQb QueryBuilder */
        $subQb = $this->getDoctrine()->getRepository('StsblScmcBundle:Room')->createQueryBuilder('sr');

        $qb
            ->select('r')
            ->orderBy('r.name', 'ASC')
        ;

        $subQb
            ->select('sr')
            ->where($qb->expr()->eq('sr.room', 'r.id'))
        ;

        if (AdminController::getRoomMode() === true) {
            $qb
                ->where($qb->expr()->not($qb->expr()->exists($subQb->getDQL())))
            ;
        } else {
            $qb
                ->where($qb->expr()->exists($subQb->getDQL()))
            ;
        }

        $parameter = [
            'rooms' => $qb->getQuery()->getResult(),
            'invert' => AdminController::getRoomMode(),
        ];

        /* @var $template Template */
        $template = $request->get('_template');

        $response = new Response();
        $response
            ->setStatusCode(Response::HTTP_FORBIDDEN)
        ;

        return $this->render($template->getTemplate(), $parameter, $response);
    }

    /**
     * Shows unavailable information
     *
     * @Route("/unavailable", name="public_scmc_unavailable")
     * @Template()
     */
    public function unavailableAction(Request $request): Response
    {
        /* @var $template Template */
        $template = $request->get('_template');

        $response = new Response();
        $response
            ->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE)
        ;

        return $this->render($template->getTemplate(), [], $response);
    }
}
