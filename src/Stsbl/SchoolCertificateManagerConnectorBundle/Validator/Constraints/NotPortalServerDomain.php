<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Validator/Constraints/NotPortalServerDomain.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

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
 * @license MIT license <https://opensource.org/licneses/MIT>
 * @Annotation
 */
class NotPortalServerDomain extends Constraint
{

    /**
     * Get error message for the case that the domain is equivalent with the portal server domain.
     *
     * @return string
     */
    public function getIsPortalServerDomainMessage()
    {
        return _('The entered domain must not be equivalent with the domain of the portal server.');
    }

    /**
     * Get error message for the case that the domain is equivalent with the portal server host name.
     *
     * @return string
     */
    public function getIsPortalServerHostNameMessage()
    {
        return _('The entered domain must not be equivalent with the host name of the portal server.');
    }

    /**
     * Get error message for the case that the domain is equivalent with the www group homepage.
     *
     * @return string
     */
    public function getIsWWWHomepageMessage()
    {
        return _('The entered domain must not use the WWW group homepage.');
    }

    /**
     * Get error message for tge case that the domain matches one of the alias domains.
     *
     * @return string
     */
    public function getIsAliasDomainMessage()
    {
        return _('The entered domain must not be one of the alias domains.');
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }

    /**
     * {@inheritdoc}
     */
    public function validatedBy()
    {
        return 'stsbl_scmc_not_portalserver_domain_validator';
    }
}