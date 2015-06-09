<?php
/**
 * Console Getopt+ (Getopt Plus) tests
 *
 * PHP version 5
 *
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * + Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 * + Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation and/or
 * other materials provided with the distribution.
 * + The name of its contributors may not be used to endorse or promote products
 * derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  PHP
 * @package   Console_GetoptPlus
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2008 Michel Corne
 * @license   http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version   SVN: $Id$
 * @link      http://pear.php.net/package/Console_GetoptPlus
 */
if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'Console_GetoptPlus_AllTests::main');
}

require_once 'PHPUnit/Framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';
// adds the path of the package if this is a raw install
file_exists("../../Console/") and set_include_path('../..' . PATH_SEPARATOR . get_include_path());
require_once 'GetoptPlusTest.php';
require_once 'GetoptPlus/ExceptionTest.php';
require_once 'GetoptPlus/GetoptTest.php';
require_once 'GetoptPlus/HelpTest.php';

/**
 * Console Getopt+ (Getopt Plus) tests
 *
 * Run the tests from the tests directory.
 * #phpunit  Console_GetoptPlus_AllTests AllTests.php
 *
 * To run the code coverage test, 2 steps:
 * #phpunit --report reports/coverage  Console_GetoptPlus_AllTests AllTests.php
 * browse the results in index.html file in reports/coverage
 *
 * The code coverage is close to 100%.
 *
 * @category  PHP
 * @package   Console_GetoptPlus
 * @author    Michel Corne <mcorne@yahoo.com>
 * @copyright 2008 Michel Corne
 * @license   http://www.opensource.org/licenses/bsd-license.php The BSD License
 * @version   Release:@package_version@
 * @link      http://pear.php.net/package/Console_GetoptPlus
 */
class Console_GetoptPlus_AllTests
{
    /**
     * Runs the test suite
     *
     * @return void
     * @access public
     * @static
     */
    public static function main()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    /**
     * Runs the test suite
     *
     * @return object the PHPUnit_Framework_TestSuite object
     * @access public
     * @static
     */
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('Console_GetoptPlus Tests');
        $suite->addTestSuite('tests_GetoptPlus_ExceptionTest');
        $suite->addTestSuite('tests_GetoptPlus_GetoptTest');
        $suite->addTestSuite('tests_GetoptPlus_HelpTest');
        $suite->addTestSuite('tests_GetoptPlusTest');
        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'Console_GetoptPlus_AllTests::main') {
    Console_GetoptPlus_AllTests::main();
}

?>