<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Controller/EntryPointController.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Controller;

use IServ\CoreBundle\Controller\PageController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
 * School Certificate Manager Connector Entry Point
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @Route("scmc")
 */
class EntryPointController extends PageController
{
    use SecurityTrait;
    
    /**
     * Check if user is already authentificated and redirect to index or login
     * 
     * @param Request $request
     * @return Response
     * @Route("", name="scmc_forward")
     */
    public function forwardAction(Request $request)
    {
        if(!$this->isManager()) {
            throw $this->createAccessDeniedException("You don't have the privileges to access the connector.");
        }
        
        if ($this->get('stsbl.scmc.security.scmcauth')->isAuthenticated()) {
            return $this->forward('StsblSchoolCertificateManagerConnectorBundle:Management:index');
        } else {
            return $this->forward('StsblSchoolCertificateManagerConnectorBundle:Security:login');
        }
    }
}
