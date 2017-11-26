<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Entity/RoomList.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use IServ\CrudBundle\Entity\CrudInterface;
use IServ\RoomBundle\Entity\Room as IServRoom;
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
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 * @ORM\Entity
 * @ORM\Table(name="scmc_rooms")
 */
class Room implements CrudInterface
{
    /**
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="\IServ\RoomBundle\Entity\Room")
     * @ORM\JoinColumn(name="room_id", referencedColumnName="id", onDelete="CASCADE")
     * @Assert\NotBlank()
     *
     * @var IServRoom
     */
    private $room;

    /**
     * Gets a string representation of the object.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->room;
    }

    /**
     * Gets a unique ID of the object which can be used to reference the entity in a URI.
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set room
     *
     * @param IServRoom $room
     *
     * @return Room
     */
    public function setRoom(IServRoom $room = null)
    {
        $this->room = $room;

        return $this;
    }

    /**
     * Get room
     *
     * @return IServRoom
     */
    public function getRoom()
    {
        return $this->room;
    }
}
