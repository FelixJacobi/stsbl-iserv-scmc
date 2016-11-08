<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Security/Authorization/AccessDeniedHandler.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Security\Authorization;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * The AccessDeniedHandler handles AccessDeniedException thrown in the scmc area.
 * 
 * Users with access privilege will redirected to the login form and after that back to the requested URI.
 * Users without access privilege will get the normal access denied page.
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license GNU General Public License <http://gnu.org/licenses/gpl-3.0>
 */
class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    /**
     * @var \Symfony\Component\Security\Core\Authorization\AuthorizationChecker
     */
    private $authorizationChecker;
    
    /**
     * @var \Symfony\Component\Routing\RouterInterface
     */
    private $router;
    
    /**
     * The constructor
     * Injeccts token storage and router into the class
     * 
     * @param TokenStorageInterface $tokenStorage
     * @param RouterInterface $router
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, RouterInterface $router)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->router = $router;
    }
    
    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, AccessDeniedException $accessDeniedException)
    {
        // Check if access was denied to scmc area and user has privileges, but is not logged in
        if (preg_match('#^/scmc(?!/login)#', $request->getPathInfo()) && $this->authorizationChecker->isGranted('PRIV_SCMC_ACCESS_FRONTEND')) {
            /* @var $session \Symfony\Component\HttpFoundation\Session\Session */
            $session = $request->getSession();

            // Add a warning and save the denied URI
            $session->getFlashBag()->add('warning', _('Please login to access certificate management section.'));
            $session->set('_security.scmc.target_path', $request->getUri());
            
            // Redirect to scmc login
            return new RedirectResponse($this->router->generate('scmc_login'));
        } 
        // don't return anything for non-scmc area or non-privileged users to fallback to default handling
        else {
            return null;
        }
    }

}
