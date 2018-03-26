<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/EventListener/KernelControllerSubscriber.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\EventListener;

use Doctrine\Common\Annotations\AnnotationReader;
use IServ\CoreBundle\Security\Authorization\AuthorizationChecker;
use IServ\CoreBundle\Security\Core\SecurityHandler;
use IServ\CoreBundle\Security\Exception\ClientSecurityException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Stsbl\SchoolCertificateManagerConnectorBundle\Security\Privilege;
use Stsbl\SchoolCertificateManagerConnectorBundle\Security\ScmcAuth;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

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
 */
class KernelControllerSubscriber implements EventSubscriberInterface
{
    /**
     * @var AuthorizationChecker
     */
    private $authorizationChecker;

    /**
     * @var ControllerResolverInterface
     */
    private $resolver;

    /**
     * @var Router;
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
     * @var ScmcAuth
     */
    private $scmcAuth;

    /**
     * The constructor.
     * 
     * @param ControllerResolverInterface $resolver
     * @param SecurityHandler $securityHandler
     * @param Session $session
     * @param Router $router
     * @param ScmcAuth $scmcAuth
     * @param AuthorizationChecker $authorizationChecker
     */
    public function __construct(ControllerResolverInterface $resolver, SecurityHandler $securityHandler, Session $session, Router $router, ScmcAuth $scmcAuth, AuthorizationChecker $authorizationChecker)
    {
        $this->resolver = $resolver;
        $this->securityHandler = $securityHandler;
        $this->session = $session;
        $this->router = $router;
        $this->scmcAuth = $scmcAuth;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    /**
     * Redirects user to scmc login form if he tries to access a page directly without auth.
     *
     * @param FilterControllerEvent $event
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        try {
            // this handler handles only requests of users with the privilege
            if (!$this->authorizationChecker->isGranted(Privilege::ACCESS_FRONTEND)) {
                return;
            }
        } catch (AuthenticationCredentialsNotFoundException $e) {
            // catch web profiler
            return;
        }

        $originalRequest = $event->getRequest();
        $pathInfo = $originalRequest->getPathInfo();

        // prefilter requests by pathinfo to improve speed:
        // 1100ms => 616ms
        if (!preg_match('#^/scmc#', $pathInfo)) {
            return;
        }

        $requestUri = $originalRequest->getUri();
        $context = new RequestContext();
        $context->fromRequest($originalRequest);
        $matcher = new UrlMatcher($this->router->getRouteCollection(), $context);

        try {
            $originalRequest->attributes->add($matcher->match($pathInfo));
            list($controller, $action) = $this->resolver->getController($originalRequest);
        } catch (ResourceNotFoundException $e) {
            // skip
            return;
        }


        $route = null;

        if (null !== $controller || null !== $action) {
            $reflectionMethod = new \ReflectionMethod($controller, $action);
            $annotationReader = new AnnotationReader();
            $annotations = $annotationReader->getMethodAnnotations($reflectionMethod);
            /* @var $annotation Route */
            $routes = array_filter($annotations, function ($annotation) {
                return $annotation instanceof Route;
            });
            if (count($routes) < 1) {
                // do not handle actions without annotation
                return;
            }
	    $annotation = array_shift($routes);

            $route = $annotation->getName();
        } else {
            // skip unresolvable
            return;
        }

        // do not handle login routes
        if ($route === 'manage_scmc_login' || $route === 'manage_scmc_logout' || $route === 'manage_scmc_forward') {
            return;
        }

        // duplicate original request
        $request = $originalRequest->duplicate(null, null, ['_controller' => 'StsblSchoolCertificateManagerConnectorBundle:Redirect:redirect']);
        $controller = $this->resolver->getController($request);

        // skip unresolvable controller
        if (!$controller) {
            return;
        }

        $self = $this;
        $setPreviousUrl = function() use ($requestUri, $self) {
            $self->session->set('scmc_login_redirect', $requestUri);
        };

        try {
            // redirect request without authorization
            if (strpos($route, 'manage_scmc') === 0 && (!$this->securityHandler->getToken()->hasAttribute('scmc_authenticated') ||
                    $this->securityHandler->getToken()->getAttribute('scmc_authenticated') != true)
            ) {
                $this->securityHandler->getToken()->setAttribute('scmc_authenticated', false);
                $this->session->set('scmc_login_notice', _('Please login to access the certificate management area.'));
                $event->setController($controller);
                call_user_func($setPreviousUrl);
                return;
            }

            // check if password is still valid
            // do not check if previous url was login
            if ($this->securityHandler->getToken() != null &&
                $this->securityHandler->getToken()->hasAttribute('scmc_sessionpassword') &&
                strpos($route, 'manage_scmc') === 0 && false === $this->scmcAuth->authenticate($this->securityHandler->getUser()->getUsername(), $this->scmcAuth->getScmcSessionPassword())
            ) {
                $this->session->set('scmc_login_notice', _('Your session is expired. Please login again.'));
                $this->securityHandler->getToken()->setAttribute('scmc_authenticated', false);
                $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', null);
                $this->securityHandler->getToken()->setAttribute('scmc_token', null);
                $event->setController($controller);
                call_user_func($setPreviousUrl);
                return;
            }
        } catch (ClientSecurityException $e) {
            $this->session->set('scmc_login_notice', _('Your session is expired. Please login again.'));
            // catch missing security cookie
            $this->securityHandler->getToken()->setAttribute('scmc_authenticated', false);
            $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', null);
            $this->securityHandler->getToken()->setAttribute('scmc_token', null);
            $event->setController($controller);
            call_user_func($setPreviousUrl);
            return;
        }
    }
}
