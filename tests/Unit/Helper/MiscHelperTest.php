<?php

namespace PhpAmqpLib\Tests\Unit\Helper\Protocol;

use PhpAmqpLib\Helper\MiscHelper;

class MiscHelperTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider getInputOutputForSplitSecondsMicroseconds
     * @param mixed $input
     * @param array $expected
     */
    public function testSplitSecondsMicroseconds($input, $expected)
    {
        $this->assertEquals($expected, MiscHelper::splitSecondsMicroseconds($input));
    }

    public function getInputOutputForSplitSecondsMicroseconds()
    {
        return array(
            array(0, array(0, 0)),
            array(0.3, array(0, 300000)),
            array('0.3', array(0, 300000)),
            array(3, array(3, 0)),
            array('3', array(3, 0)),
            array(3.0, array(3, 0)),
            array('3.0', array(3, 0)),
            array(3.1, array(3, 100000)),
            array('3.1', array(3, 100000)),
            array(3.123456, array(3, 123456)),
            array('3.123456', array(3, 123456)),
        );
    }

    public function testHexDump()
    {
        $htmlOutput = false;
        $uppercase = false;
        $return = true;
        $res = MiscHelper::hexdump('FM', $htmlOutput, $uppercase, $return);
        $this->assertRegExp('/000\s+46 4d\s+FM/', $res);
        $uppercase = true;
        $res = MiscHelper::hexdump('FM', $htmlOutput, $uppercase, $return);
        $this->assertRegExp('/000\s+46 4D\s+FM/', $res);
    }
}
