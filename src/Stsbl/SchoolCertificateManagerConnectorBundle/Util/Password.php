<?php
// src/Stsbl/SchoolCertificateManagerConnectorBundle/Util/Password.php
namespace Stsbl\SchoolCertificateManagerConnectorBundle\Util;

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
 * Util for password generation and management
 *
 * @author Felix Jacobi <felix.jacobi@stsbl.de>
 * @license MIT license <https://opensource.org/licenses/MIT>
 */
class Password 
{
    /**
     * Generates salt to hash a password
     */
    public static function generateSalt()
    {
        return base64_encode(mcrypt_create_iv(22, MCRYPT_DEV_URANDOM));
    }
    
    /**
     * Generates a hash from a plaintext password
     * 
     * @param string $password
     * @param string $salt
     * @param int $cost
     * @return string
     */
    public static function generateHash($password, $salt, $cost = 11)
    {
        $HashOptions = [
            'cost' => $cost,
            'salt' => $salt
        ];
        
        return password_hash($password, PASSWORD_BCRYPT, $HashOptions);
    }
}
