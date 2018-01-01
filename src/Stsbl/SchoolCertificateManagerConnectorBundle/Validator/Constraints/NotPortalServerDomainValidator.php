<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Validator/Constraints/NotPortalServerDomainValidator.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Validator\Constraints;

use IServ\CoreBundle\Service\Config;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

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
 */
class NotPortalServerDomainValidator extends ConstraintValidator
{
    /**
     * @var Config
     */
    private $config;

    /**
     * The constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        /* @var $constraint NotPortalServerDomain */
        if ((string)$value == $this->config->get('Domain')) {
            $this->context->buildViolation($constraint->getIsPortalServerDomainMessage())->atPath('webDomain')->addViolation();
        }

        if((string)$value == 'www.'.$this->config->get('Domain')) {
            $this->context->buildViolation($constraint->getIsWWWHomepageMessage())->atPath('webDomain')->addViolation();
        }

        if ((string)$value == $this->config->get('Hostname')) {
            $this->context->buildViolation($constraint->getIsPortalServerHostNameMessage())->atPath('webDomain')->addViolation();
        }

        foreach ($this->config->get('AliasDomains') as $aliasDomain) {
            if ((string)$value === 'www.'.$aliasDomain) {
                $this->context->buildViolation($constraint->getIsWWWHomepageMessage())->atPath('webDomain')->addViolation();
            }
            if ((string)$value === $aliasDomain) {
                $this->context->buildViolation($constraint->getIsAliasDomainMessage())->atPath('webDomain')->addViolation();
            }
        }
    }
}