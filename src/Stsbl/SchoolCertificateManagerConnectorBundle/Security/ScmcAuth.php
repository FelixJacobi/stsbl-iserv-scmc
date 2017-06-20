<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Security/ScmcAuth.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Security;

use IServ\CoreBundle\Security\Core\SecurityHandler;
use IServ\CoreBundle\Security\Exception\ClientSecurityException;
use Stsbl\SchoolCertificateManagerConnectorBundle\Security\Exception\ScmcAuthException;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
 * @license MIT license <https://opensource.org/licneses/MIT>
 */
class ScmcAuth implements EventSubscriberInterface
{
    /**
     * The name of the client security token.
     *
     * @var string
     */
    const SECURITYCOOKIE = 'SCMCSESSPW';

    /**
     * @var Request
     */
    private $request;

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
     * The constructor.
     *
     * @param RequestStack $stack
     * @param Router $router
     * @param SecurityHandler $securityHandler
     * @param Session $session
     */
    public function __construct(RequestStack $stack, Router $router, SecurityHandler $securityHandler, Session $session)
    {
        $this->request = $stack->getCurrentRequest();
        $this->router = $router;
        $this->securityHandler = $securityHandler;
        $this->session = $session;
    }

    /**
     * Authenticates given account again against the scmcauthd to ensure for example
     * that a session is still valid.
     *
     * @param string $account
     * @param string|array $password
     * @param string $service
     * @param boolean $sessionPassword
     * @return string
     */
    public function authenticate($account, $password, $service = 'scmcweb', $sessionPassword = true)
    {
        if (!is_string($account)) {
            throw new \InvalidArgumentException(sprintf('$account has to be string. `%s` given.', gettype($account)));
        }

        $args = [];
        $args[] = $account;
        $args[] = $this->securityHandler->getSessionPassword();
        $args[] = $service;
        if (!$sessionPassword) {
            if (!is_array($password) || count($password) != 2) {
                throw new \InvalidArgumentException('For authenticating with real scmc credentials, an array with master and user password must be supplied.');
            }
            $args[] = json_encode($password);
        } else {
            // not used if authenticating with session password
            $args[] = json_encode(['unused', 'unused']);
        }

        // only used on session opening
        $args[] = json_encode(['_dummy' => '']);

        $secondArgs = [];
        if ($sessionPassword) {
            $secondArgs[] = $password;
        }

        return preg_match("/^OK\b/", $this->execute($args, $secondArgs));
    }

    /**
     * Calls the scmcauthd with given arguments and return its result.
     *
     * @param array $args
     * @param array $secondArgs
     * @return string
     */
    private function execute(array $args, array $secondArgs = null)
    {
        $params = json_encode([
            'protocol' => $this->request->server->get('SERVER_PROTOCOL'),
            'encrypted' => $this->request->server->has('HTTPS'),
            'server_addr' => $this->request->server->get('SERVER_ADDR'),
            'server_port' => $this->request->server->get('SERVER_PORT'),
            'client_addr' => $this->request->getClientIp(),
            'client_agent' => $this->request->headers->get('User-Agent')
        ]);
        $args[] = $params;

        if (count($secondArgs) > 0) {
            foreach ($secondArgs as $arg) {
                $args[] = $arg;
            }
        }
        // required to indicate end of input
        $args[] = '';

        if (!$fp = fsockopen('unix:///var/run/scmcauthd/socket')) {
            throw new ScmcAuthException('Cannot connect to scmcauthd!');
        }

        foreach ($args as $v) {
            fwrite($fp, pack('n', strlen($v)) . $v);
        }

        if (!$size = @unpack('n', fread($fp, 2))) {
            throw new ScmcAuthException('Invalid response from scmcauthd!');
        }

        $res = fread($fp, $size[1]);
        fclose($fp);

        return $res;
    }

    /**
     * Does a user login with given account/password
     *
     * @param string $masterPassword
     * @param string $userPassword
     * @param callable $onSuccessCallback
     * @return string
     */
    public function login($masterPassword, $userPassword, $onSuccessCallback = null)
    {

        return $this->open($this->securityHandler->getUser()->getUsername(), $this->securityHandler->getSessionPassword(), $masterPassword, $userPassword, "scmc_sess_open", $onSuccessCallback);
    }

    /**
     * Opens a user session.
     *
     * @param string $act
     * @param string $pwd
     * @param string $masterPassword
     * @param string $userPassword
     * @param string $service
     * @param callable $onSuccessCallback
     * @return boolean|string
     */
    private function open($act, $pwd, $masterPassword, $userPassword, $service, $onSuccessCallback = null)
    {
        $res = $this->execute([$act, $pwd, $service, json_encode([$masterPassword, $userPassword])]);
        $arr = explode(" ", $res);

        if ("OK" !== $arr[0]) {
            array_shift($arr);
            return implode(" ", $arr);
        }

        $sid = $arr[1];
        $spw = $arr[2];

        // Run callback on success after fetching the act for remember me
        if (null !== $onSuccessCallback) {
            call_user_func($onSuccessCallback, $act);
        }

        $sesspw = substr($spw, 0, 32); // return first part of sesspw
        $this->setSecurityCookie(substr($spw, 32)); // store second part in cookie

        $this->securityHandler->getToken()->setAttribute('scmc_authenticated', true);
        $this->securityHandler->getToken()->setAttribute('scmc_sessiontoken', $sid);
        $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', $sesspw);

        return true;
    }

    /**
     * Logs a user out.
     *
     * @param string $account
     * @param string $password
     * @return boolean
     */
    public function close($account)
    {
        $args = [
            $account,
            $this->securityHandler->getSessionPassword(),
            'scmc_sess_close',
            json_encode(['unused', 'unused']),
        ];

        $secondArgs = [$this->getScmcSessionPassword()];

        $res = $this->execute($args, $secondArgs) === 'OK';

        if ($res) {
            $this->securityHandler->getToken()->setAttribute('scmc_authenticated', false);
            $this->securityHandler->getToken()->setAttribute('scmc_sessiontoken', null);
            $this->securityHandler->getToken()->setAttribute('scmc_sessionpassword', null);

            // ignore failures on cookie deleting
            try {
                $this->clearSecurityCookie();
            } catch (ClientSecurityException $e) {

            }
        }

        return $res;
    }

    /**
     * Sets the security cookie
     *
     * @internal This is handled out of the Symfony scope to have a single point of concern.
     *
     * @param string $sessionPassword2 The 2nd half of the session password
     */
    private function setSecurityCookie($sessionPassword2)
    {
        $scp = session_get_cookie_params();
        $_COOKIE[self::SECURITYCOOKIE] = $sessionPassword2; // Store data instantly for follow up read actions!
        setcookie(self::SECURITYCOOKIE, $sessionPassword2, ($scp['lifetime'] ? time() + $scp['lifetime'] : 0), $scp['path'], $scp['domain'], $scp['secure'], $scp['httponly']);
    }

    /**
     * Gets the security cookie
     *
     * @internal This is handled out of the Symfony scope to have a single point of concern.
     *
     * @return string
     */
    public function getSecurityCookie()
    {
        if (!isset($_COOKIE[self::SECURITYCOOKIE])) {
            throw new ClientSecurityException('Missing security cookie!');
        }

        return $_COOKIE[self::SECURITYCOOKIE];
    }

    /**
     * Clears the security cookie
     *
     * @internal This is handled out of the Symfony scope to have a single point of concern.
     */
    public function clearSecurityCookie()
    {
        if (!isset($_COOKIE[self::SECURITYCOOKIE])) {
            throw new ClientSecurityException('Cannot clear security cookie: Cookie not set!');
        }

        $scp = session_get_cookie_params();
        setcookie(self::SECURITYCOOKIE, null, 1, $scp['path'], $scp['domain'], $scp['secure'], $scp['httponly']);
        unset($_COOKIE[self::SECURITYCOOKIE]);
    }

    /**
     * Get full session password
     *
     * @return string
     */
    public function getScmcSessionPassword()
    {
        return $this->securityHandler->getToken()->getAttribute('scmc_sessionpassword') . $this->getSecurityCookie();
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

    /**
     * @return boolean
     */
    public function isAuthenticated()
    {
        return $this->securityHandler->getToken()->hasAttribute('scmc_authenticated') &&
            $this->securityHandler->getToken()->getAttribute('scmc_authenticated') === true;
    }
}