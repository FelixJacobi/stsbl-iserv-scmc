<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Entity/Server.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use IServ\CoreBundle\Entity\Group;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\HostBundle\Entity\Host;
use Symfony\Component\Validator\Constraints as Assert;

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
 * StsblSchoolCertificateManagerConnectorBundle:UserPassword
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @ORM\Entity
 * @ORM\Table(name="scmc_servers")
 */
class Server implements CrudInterface
{
    /**
     * @ORM\OneToOne(targetEntity="\IServ\HostBundle\Entity\Host", fetch="EAGER")
     * @ORM\JoinColumn(name="host", referencedColumnName="name")
     * @ORM\Id
     * 
     * @var Host
     */
    private $host;
    
    /**
     * @ORM\Column(name="tomcatType", type="string", nullable=false)
     * @Assert\Choice(choices = {"tomcat6", "tomcat7", "tomcat7"}, message = "Choose a valid server type.")
     *
     * @var string
     */
    private $tomcatType;
    
    /**
     * @ORM\Column(name="webDomain", type="string", nullable=false)
     * @Assert\Regex("/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/")
     *
     * @var string
     */
    private $webDomain;

    /**
     * @ORM\OneToOne(targetEntity="\IServ\CoreBundle\Entity\Group", fetch="EAGER")
     * @ORM\JoinColumn(name="actGrp", referencedColumnName="act")
     * 
     * @var Group
     */
    private $group = null;

    /**
     * @ORM\Column(name="sshAct", type="string", nullable=false)
     * @Assert\Regex("/^[a-z][a-z0-9._-]*$/")
     */
    private $sshAccount;
    
    /**
     * {@inheritdoc}
     */
    public function __toString() 
    {
        return (string)$this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->host->getName();
    }


    /**
     * Set tomcatType
     *
     * @param string $tomcatType
     *
     * @return Server
     */
    public function setTomcatType($tomcatType)
    {
        $this->tomcatType = $tomcatType;

        return $this;
    }

    /**
     * Get tomcatType
     *
     * @return string
     */
    public function getTomcatType()
    {
        return $this->tomcatType;
    }

    /**
     * Set webDomain
     *
     * @param string $webDomain
     *
     * @return Server
     */
    public function setWebDomain($webDomain)
    {
        $this->webDomain = $webDomain;

        return $this;
    }

    /**
     * Get webDomain
     *
     * @return string
     */
    public function getWebDomain()
    {
        return $this->webDomain;
    }

    /**
     * Set sshAccount
     *
     * @param string $sshAccount
     *
     * @return Server
     */
    public function setSshAccount($sshAccount)
    {
        $this->sshAccount = $sshAccount;

        return $this;
    }

    /**
     * Get sshAccount
     *
     * @return string
     */
    public function getSshAccount()
    {
        return $this->sshAccount;
    }

    /**
     * Set host
     *
     * @param Host $host
     *
     * @return Server
     */
    public function setHost(Host $host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Get host
     *
     * @return Host
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set group
     *
     * @param Group $group
     *
     * @return Server
     */
    public function setGroup(Group $group)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Get group
     *
     * @return \IServ\CoreBundle\Entity\Group
     */
    public function getGroup()
    {
        return $this->group;
    }
}
