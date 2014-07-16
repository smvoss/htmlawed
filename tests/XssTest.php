<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Htmlawed\Tests;

use Htmlawed\Htmlawed;
use pQuery;

/**
 * Test some xss strings.
 */
class XssTest extends \PHPUnit_Framework_TestCase {
    /**
     * Assert that a string doesn't have a script tag.
     *
     * @param string $str The string to test.
     * @param string $message The error message if a script tag is found.
     */
    public function assertNoScript($str, $message = '') {
        self::assertFalse(
            (bool)preg_match('`<\s*/?\s*script`i', $str),
            $message
        );
    }

    /**
     * Test a malformed href including a script element.
     *
     * @param array $config
     * @param string $spec
     * @dataProvider provideConfigs
     */
    public function testScriptInHref($config = [], $spec = '') {
        $str = <<<EOT
<a href="<script foo=''">alert('xss')</a>
EOT;

        $filtered = Htmlawed::filter($str, $config, $spec);

        $this->assertNoScript($filtered);
    }

    /**
     * Test that filtering a string twice returns the same strings.
     *
     * @param string $str The string to test.
     * @param array $config The htmlawed config.
     * @param string $spec The htmlawed spec.
     * @dataProvider provideXss
     */
    public function testIdempotence($str, $config = [], $spec = '') {
        $filtered = Htmlawed::filter($str, $config, $spec);
        $filteredAgain = Htmlawed::filter($filtered, $config, $spec);
        $this->assertEquals($filtered, $filteredAgain);
    }

    /**
     * Test that the xss test strings don't have a script tag.
     *
     * @param string $str The string to test.
     * @param array $config The htmlawed config.
     * @param string $spec The htmlawed spec.
     * @dataProvider provideXss
     */
    public function testNoScript($str, $config = [], $spec = '') {
        $filtered = Htmlawed::filter($str, $config, $spec);
        $this->assertNoScript($filtered);
    }

    /**
     * Test the xss strings against a {@link pQuery} dom construction.
     *
     * @param string $str The string to test.
     * @param array $config The htmlawed config.
     * @param string $spec The htmlawed spec.
     * @dataProvider provideXss
     */
    public function testDom($str, $config = [], $spec = '') {
        $filtered = Htmlawed::filter($str, $config, $spec);

        $q = pQuery::parseStr($filtered);

        $ons = ['onclick', 'onmouseover', 'onload', 'onerror'];
        foreach ($ons as $on) {
            if (strpos($filtered, $on) !== false) {
                $elems = $q->query("*[$on]");
                $this->assertSame(0, $elems->count(), "Filtered still has an $on attribute.");
            }
        }

        $elems = ['applet', 'form', 'input', 'textarea', 'iframe', 'script', 'style', 'embed', 'object'];
        foreach ($elems as $elem) {
            $this->assertSame(0, $q->query($elem)->count(), "Filtered still has an $elem element.");
        }
    }

    /**
     * Provide some htmlawed configs.
     *
     * @return array Returns the configs.
     */
    public function provideConfigs() {
        $result = [
            'safe' => [['safe' => 1], ''],
        ];

        $result['vanilla'] = [
            [
                'anti_link_spam' => ['`.`', ''],
                'comment' => 1,
                'cdata' => 3,
                'css_expression' => 1,
                'deny_attribute' => 'on*',
                'unique_ids' => 0,
                'elements' => '*-applet-form-input-textarea-iframe-script-style-embed-object',
                'keep_bad' => 0,
                'schemes' => 'classid:clsid; href: aim, feed, file, ftp, gopher, http, https, irc, mailto, news, nntp, sftp, ssh, telnet; style: nil; *:file, http, https', // clsid allowed in class
                'valid_xhtml' => 0,
                'direct_list_nest' => 1,
                'balance' => 1
            ],
            'object=-classid-type, -codebase; embed=type(oneof=application/x-shockwave-flash)'
        ];

        return $result;
    }

    /**
     * Combine two providers into one.
     *
     * @param callable $a The first provider.
     * @param callable $b The second provider.
     * @return array Returns the combined providers.
     */
    protected function combineProviders(callable $a, callable $b) {
        $a_items = call_user_func($a);
        $b_items = call_user_func($b);

        $result = [];
        foreach ($a_items as $a_key => $a_row) {
            foreach ($b_items as $b_key => $b_row) {
                $result[$a_key.': '.$b_key] = array_merge($a_row, $b_row);
            }
        }
        return $result;
    }

    /**
     * Provide all the xss strings.
     *
     * @return array Returns an array of xss strings.
     */
    public function provideXss() {
        return array_merge($this->provideRSnake(), $this->provideEvasion());
    }

    /**
     * Provide the RSnake strings.
     *
     * @return array Returns the RSnake strings.
     */
    public function provideRSnake() {
        return $this->combineProviders([$this, 'provideRSnakeTests'], [$this, 'provideConfigs']);
    }

    /**
     * Provide the xss evasion strings.
     *
     * @return array Returns the xss evasion strings.
     */
    public function provideEvasion() {
        $result = $this->combineProviders([$this, 'provideEvasionTests'], [$this, 'provideConfigs']);
        return $result;
    }


    /**
     * Provides a list of hacks from RSnake (special thanks).
     *
     * @see https://fuzzdb.googlecode.com/svn-history/r186/trunk/attack-payloads/xss/xss-rsnake.txt
     */
    public function provideRSnakeTests() {
        $lines = explode("\n\n", file_get_contents(__DIR__.'/fixtures/xss-rsnake.txt'));
        array_walk($lines, function(&$line) {
            $line = [trim($line)];
        });

        return $lines;
    }

    /**
     * Provide a list of hacks from owasp.org (special thanks).
     *
     * @see https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet
     */
    public function provideEvasionTests() {
        $lines = explode("\n\n", file_get_contents(__DIR__.'/fixtures/xss-evasion.txt'));

        $result = [];
        foreach ($lines as $line) {
            list($key, $value) = explode("\n", $line, 2);
            $result[$key] = [$value];
        }

        return $result;
    }
}
 