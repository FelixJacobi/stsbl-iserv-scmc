<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/EventListener/KernelResponseSubscriber.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\EventListener;

use IServ\CoreBundle\Security\Core\SecurityHandler;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class KernelResponseSubscriber implements EventSubscriberInterface
{
    /**
     * @var Router
     */
    private $router;

    /**
     * @var SecurityHandler
     */
    private $securityHandler;

    /**
     * @var Session
     */
    private $session;

    /**
     * @param Router $router
     * @param SecurityHandler $securityHandler
     * @param Session $session
     */
    public function __construct(Router $router, SecurityHandler $securityHandler, Session $session)
    {
        $this->router = $router;
        $this->securityHandler = $securityHandler;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    /**
     * Redirects user to scmc login form if he tries to access a page directly without auth.
     *
     * @param FilterResponseEvent $event
     * @return RedirectResponse|null
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $route = $event->getRequest()->get('_route');

        if ($route === 'scmc_login' || $route === 'scmc_logout' || $route === 'scmc_forward') {
            return;
        }

        // redirect request without authorization
        if (strpos($route, 'scmc_') === 0 && (!$this->securityHandler->getToken()->hasAttribute('scmc_authenticated') ||
                $this->securityHandler->getToken()->getAttribute('scmc_authenticated') != true)
        ) {
            $this->securityHandler->getToken()->setAttribute('scmc_authenticated', false);
            $this->session->set('scmc_login_notice', _('Please login to access the certificate management area.'));
            $event->setResponse(new RedirectResponse($this->router->generate('scmc_login')));
        }

        // check if password is still valid
        /*if ($this->securityHandler->getToken() != null &&
            $this->securityHandler->getToken()->hasAttribute('scmc_sessionpassword') &&
            strpos($route, 'scmc_') === 0 && !$this->authenticate($this->securityHandler->getUser()->getUsername(), $this->getScmcSessionPassword())
        ) {
            $this->securityHandler->getToken()->setAttribute('scmc_authenticated', false);
            $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', null);
            $this->securityHandler->getToken()->setAttribute('scmc_token', null);
            $this->session->set('scmc_login_notice', _('Your session is expired. Please login again.'));
            $event->setResponse(new RedirectResponse($this->router->generate('scmc_login')));
        }*/
    }
}