<?php

namespace Stsbl\ScmcBundle\EventListener;

use IServ\AdminBundle\EventListener\AdminMenuListenerInterface;
use IServ\CoreBundle\Event\MenuEvent;
use IServ\CoreBundle\EventListener\MainMenuListenerInterface;

/*
 * The MIT License
 *
 * Copyright 2020 Felix Jacobi.
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
class MenuListener implements MainMenuListenerInterface, AdminMenuListenerInterface 
{
    /**
     * @param \IServ\CoreBundle\Event\MenuEvent $event
     */
    public function onBuildMainMenu(MenuEvent $event)
    {
        $menu = $event->getMenu(self::ORGANISATION);

        // check if user is privileged
        if ($event->getAuthorizationChecker()->isGranted('PRIV_SCMC_ACCESS_LIST')) {
            $item = $menu->addChild('access_scmc', array(
                'route' => 'access_scmc_index',
                'label' => _('Certificate Grade Input'),
                'extras' => array(
                    'icon' => 'paper-bag--pencil',
                    'icon_style' => 'fugue',
                ),
            ));
            $item->setExtra('orderNumber', 20);
        }

        // check if user is privileged
        if ($event->getAuthorizationChecker()->isGranted('PRIV_SCMC_ACCESS_FRONTEND')) {
            $item = $menu->addChild('manage_scmc', array(
                'route' => 'manage_scmc_forward',
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
