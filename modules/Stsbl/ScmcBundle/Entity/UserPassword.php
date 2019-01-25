<?php

namespace Stsbl\ScmcBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use IServ\CoreBundle\Entity\User;
use IServ\CrudBundle\Entity\CrudInterface;

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
 * StsblScmcBundle:UserPassword
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @ORM\Entity
 * @ORM\Table(name="scmc_userpasswords")
 */
class UserPassword implements CrudInterface
{
    /**
     * @ORM\OneToOne(targetEntity="\IServ\CoreBundle\Entity\User", fetch="EAGER")
     * @ORM\JoinColumn(name="act", referencedColumnName="act")
     * @ORM\Id
     *
     * @var User
     */
    private $user;
    
    /**
     * @ORM\Column(name="password", type="boolean")
     *
     * @var bool
     */
    private $password;
    
    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->user->getUsername();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getUser()->getUsername();
    }
    
    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
    
    /**
     * Get password
     *
     * @return bool
     */
    public function hasPassword()
    {
        return $this->password;
    }
    
    /**
     * Set user
     *
     * @param User $user
     * @return UserPassword;
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        
        return $this;
    }
    
    /**
     * Set if user have a password or not
     *
     * @param bool $password
     * @return UserPassword
     */
    public function setPassword($password)
    {
        $this->password = $password;
        
        return $this;
    }

    public function getUsername(): string
    {
        return $this->user->getUsername();
    }
}
