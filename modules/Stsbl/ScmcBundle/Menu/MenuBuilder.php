<?php

namespace Stsbl\ScmcBundle\Menu;

use IServ\CoreBundle\Menu\AbstractMenuBuilder;

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
 * Builds the SCMC navigation
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class MenuBuilder extends AbstractMenuBuilder
{
    /**
     * Creates the scmc menu
     * 
     * @return \Knp\Menu\ItemInterface
     */
    public function createSCMCMenu()
    {
        $menu = $this->factory->createItem('manage_scmc_menu');
        
        $menu
            ->addChild('manage_scmc_menu_index', [
                'route' => 'manage_scmc_forward',
                'label' => _('Start Page')
            ])
                ->setExtra('order', 10)
                ->setExtra('icon', 'home')
        ;

        $menu
            ->addChild('manage_scmc_menu_download', [
                'route' => 'manage_scmc_download',
                'label' => _('Data Download')
            ])
                ->setExtra('order', 20)
                ->setExtra('icon', 'drive-download')
                ->setExtra('icon_style', 'fugue')
        ;

        $menu
            ->addChild('manage_scmc_menu_upload', [
                'route' => 'manage_scmc_upload',
                'label' => _('Data Upload')
            ])
                ->setExtra('order', 30)
                ->setExtra('icon', 'drive-upload')
                ->setExtra('icon_style', 'fugue')
        ;
        
        /*$menu
            ->addChild('manage_scmc_menu_status', [
                'route' => 'manage_scmc_status',
                'label' => _('Server Status')
            ])
                ->setExtra('order', 40)
                ->setExtra('icon', 'status')
                ->setExtra('icon_style', 'fugue')
        ;*/
        
        $menu
            ->addChild('manage_scmc_menu_logout', [
                'route' => 'manage_scmc_logout',
                'label' => _('Logout')
            ])
                ->setExtra('order', 101)
                ->setExtra('icon', 'lock')
        ;
        
        // re-order menu elements
        
        return $menu;
    }
}
