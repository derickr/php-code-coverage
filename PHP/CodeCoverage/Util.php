<?php
/**
 * PHP_CodeCoverage
 *
 * Copyright (c) 2009, Sebastian Bergmann <sb@sebastian-bergmann.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   PHP
 * @package    CodeCoverage
 * @author     Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright  2009 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://github.com/sebastianbergmann/php-code-coverage
 * @since      File available since Release 1.0.0
 */

/**
 * Utility methods.
 *
 * @category   PHP
 * @package    CodeCoverage
 * @author     Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright  2009 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://github.com/sebastianbergmann/php-code-coverage
 * @since      Class available since Release 1.0.0
 */
class PHP_CodeCoverage_Util
{
    /**
     * Returns the files and lines a test method wants to cover.
     *
     * @param  string $className
     * @param  string $methodName
     * @return array
     */
    public static function getLinesToBeCovered($className, $methodName)
    {
        $result          = array();
        $codeToCoverList = array();

        if (($pos = strpos($methodName, ' ')) !== FALSE) {
            $methodName = substr($methodName, 0, $pos);
        }

        try {
            $class      = new ReflectionClass($className);
            $method     = new ReflectionMethod($className, $methodName);
            $docComment = $class->getDocComment() . $method->getDocComment();

            foreach (array('setUp', 'assertPreConditions', 'assertPostConditions', 'tearDown') as $templateMethod) {
                if ($class->hasMethod($templateMethod)) {
                    $reflector = $class->getMethod($templateMethod);
                    $docComment .= $reflector->getDocComment();
                    unset($reflector);
                }
            }

            if (preg_match_all('/@covers[\s]+([\!<>\:\.\w]+)([\s]+<extended>)?/', $docComment, $matches)) {
                foreach ($matches[1] as $i => $method) {
                    $codeToCoverList = array_merge(
                        $codeToCoverList,
                        self::resolveCoversToReflectionObjects(
                          $method, !empty($matches[2][$i])
                        )
                    );
                }

                foreach ($codeToCoverList as $codeToCover) {
                    $fileName  = $codeToCover->getFileName();
                    $startLine = $codeToCover->getStartLine();
                    $endLine   = $codeToCover->getEndLine();

                    if (!isset($result[$fileName])) {
                        $result[$fileName] = array();
                    }

                    $result[$fileName] = array_unique(
                      array_merge(
                        $result[$fileName], range($startLine, $endLine)
                      )
                    );
                }
            }
        }

        catch (ReflectionException $e) {
        }

        return $result;
    }

    /**
     * @param  string  $method
     * @param  boolean $extended
     * @return array
     */
    protected static function resolveCoversToReflectionObjects($method, $extended)
    {
        $codeToCoverList = array();

        if (strpos($method, '::') !== FALSE) {
            list($className, $methodName) = explode('::', $method);

            if ($methodName{0} == '<') {
                $classes = array($className);

                if ($extended) {
                    $classes = array_merge(
                      $classes,
                      class_implements($className),
                      class_parents($className)
                    );
                }

                foreach ($classes as $className)
                {
                    $class   = new ReflectionClass($className);
                    $methods = $class->getMethods();
                    $inverse = isset($methodName{1}) && $methodName{1} == '!';

                    if (strpos($methodName, 'protected')) {
                        $visibility = 'isProtected';
                    }

                    else if (strpos($methodName, 'private')) {
                        $visibility = 'isPrivate';
                    }

                    else if (strpos($methodName, 'public')) {
                        $visibility = 'isPublic';
                    }

                    foreach ($methods as $method) {
                        if ($inverse && !$method->$visibility()) {
                            $codeToCoverList[] = $method;
                        }

                        else if (!$inverse && $method->$visibility()) {
                            $codeToCoverList[] = $method;
                        }
                    }
                }
            } else {
                $classes = array($className);

                if ($extended) {
                    $classes = array_merge($classes, class_parents($className));
                }

                foreach ($classes as $className) {
                    $codeToCoverList[] = new ReflectionMethod(
                      $className, $methodName
                    );
                }
            }
        } else {
            $classes = array($method);

            if ($extended) {
                $classes = array_merge(
                  $classes,
                  class_implements($method),
                  class_parents($method)
                );
            }

            foreach ($classes as $className) {
                $codeToCoverList[] = new ReflectionClass($className);
            }
        }

        return $codeToCoverList;
    }
}
?>