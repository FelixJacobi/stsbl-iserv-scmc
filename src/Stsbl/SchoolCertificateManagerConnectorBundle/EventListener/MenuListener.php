<?php
// src/Stsbl/SchoolCertificateManagerConnector/EventListener/MenuListener.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\EventListener;

use IServ\AdminBundle\EventListener\AdminMenuListenerInterface;
use IServ\CoreBundle\Event\MenuEvent;
use IServ\CoreBundle\EventListener\MainMenuListenerInterface;

/**
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */

class MenuListener implements MainMenuListenerInterface, AdminMenuListenerInterface {
    /**
     * @param \IServ\CoreBundle\Event\MenuEvent $event
     */
    public function onBuildMainMenu(MenuEvent $event)
    {
        // check if user is privileged
        if ($event->getAuthorizationChecker()->isGranted('PRIV_SCMC_ACCESS_FRONTEND')) {            
            $menu = $event->getMenu(self::ORGANISATION);
            $item = $menu->addChild('scmc', array(
                'route' => 'scmc_forward',
                'label' => _('Certificate Management'),
                'extras' => array(
                  'icon' => 'paper-bag--pencil',
                  'icon_style' => 'fugue',
                ),
            ));
            $item->setExtra('orderNumber', 20);
        }
    }
    
    /**
     * @param \IServ\CoreBundle\Event\MenuEvent $event
     */
    public function onBuildAdminMenu(MenuEvent $event)
    {
        // check if user is privileged
        if ($event->getAuthorizationChecker()->isGranted('PRIV_SCMC_ADMIN'))
        {
            $menu = $event->getMenu();
            $block = $menu->getChild('modules');

            $item = $block->addChild('admin_scmc', array(
                'route' => 'admin_scmc',
                'label' => _('Certificate Management'),
            ));
            $item->setExtra('icon', 'paper-bag--pencil');
            $item->setExtra('icon_style', 'fugue');
        }
    }
}
