<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Security/Firewall/ExceptionListener.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Security\Firewall;

use Symfony\Component\HttpFoundation\Request;
use IServ\AdminBundle\Security\Firewall\ExceptionListener as BaseExceptionListener;

/**
 * The listener replaces any target path to the admin location with the admin login.
 *
 * The real request URI is stored in the session with the key `_security.admin.target_path`.
 */
class ExceptionListener extends BaseExceptionListener
{
    protected function setTargetPath(Request $request)
    {
        // Do not save full path for scmc area
        if (preg_match('#^/scmc#', $request->getPathInfo())) {
            // Session isn't required when using HTTP basic authentication mechanism for example
            if ($request->hasSession() && $request->isMethodSafe()) {
                $session = $request->getSession();

                // Store real request URI with custom key and set target path to admin login
                $session->set('_security.scmc.target_path', $request->getUri());

                // FIXME: Need to create full target URI?
                // '_security.'.$this->providerKey.'.target_path' needs private property. Therefore `idesk` hardcoded.
                $session->set('_security.idesk.target_path', '/scmc/login');
            }
        }
        // ... otherwise use default behaviour
        else {
            parent::setTargetPath($request);
        }
    }
}
