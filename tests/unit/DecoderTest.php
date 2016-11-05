<?php
namespace Crossjoin\Json\Tests\Unit;

use Crossjoin\Json\Decoder;

/**
 * Class DecoderTest
 *
 * @package Crossjoin\Json\Tests\Unit
 * @author Christoph Ziegenberg <ziegenberg@crossjoin.com>
 *
 * @coversDefaultClass \Crossjoin\Json\Decoder
 */
class DecoderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    public function getJsonOptions()
    {
        $options = array(0);
        $options[] = \JSON_HEX_QUOT;
        $options[] = \JSON_HEX_TAG;
        $options[] = \JSON_HEX_AMP;
        $options[] = \JSON_HEX_APOS;
        $options[] = \JSON_NUMERIC_CHECK;
        $options[] = \JSON_FORCE_OBJECT;
        if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
            $options[] = \JSON_PRETTY_PRINT;
            $options[] = \JSON_UNESCAPED_SLASHES;
            $options[] = \JSON_UNESCAPED_UNICODE;
        }
        if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
            $options[] = \JSON_PARTIAL_OUTPUT_ON_ERROR;
        }
        if (version_compare(PHP_VERSION, '5.6.6', '>=')) {
            $options[] = \JSON_PRESERVE_ZERO_FRACTION;
        }

        return $options;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        $values   = array();
        $values[] = '';
        $values[] = null;
        $values[] = 1;
        $values[] = 1.23;
        $values[] = true;
        $values[] = array();
        $values[] = new \stdClass();
        $values[] = 'foo';
        $values[] = 'äöüßÄÖÜ';
        $values[] = '👍';

        $ascii = '';
        for ($i = 0; $i < 128; $i++) {
            $ascii .= chr($i);
        }
        $values[] = $ascii;

        return $values;
    }

    /**
     * @return array
     */
    public function dataDecodeValidDataWithoutBom()
    {
        $data = array();

        foreach (
            array(Decoder::UTF8, Decoder::UTF16BE, Decoder::UTF16LE, Decoder::UTF32BE, Decoder::UTF32LE) as $encoding
        ) {
            foreach ($this->getJsonOptions() as $option) {
                foreach ($this->getValues() as $value) {
                    $json = \json_encode($value, $option);
                    if ($encoding !== Decoder::UTF8) {
                        $json = iconv('UTF-8', $encoding . '//IGNORE', $json);
                    }

                    if ($option === \JSON_FORCE_OBJECT && is_array($value)) {
                        $value = (object)$value;
                    }

                    $data[] = array($json, $value, $encoding);
                }
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function dataDecodeValidDataWithBom()
    {
        $data = $this->dataDecodeValidDataWithoutBom();

        foreach ($data as &$value) {
            $bom = '';
            switch ($value[2]) {
                case Decoder::UTF8;
                    $bom = chr(239) . chr(187) . chr(191);
                    break;
                case Decoder::UTF16BE;
                    $bom = chr(254) . chr(255);
                    break;
                case Decoder::UTF16LE;
                    $bom = chr(255) . chr(254);
                    break;
                case Decoder::UTF32BE;
                    $bom = chr(0) . chr(0) . chr(254) . chr(255);
                    break;
                case Decoder::UTF32LE;
                    $bom = chr(255) . chr(254) . chr(0) . chr(0);
                    break;
            }

            if ($bom !== '') {
                $value[0] = $bom . $value[0];
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function dataInvalidIgnoreBomValues()
    {
        return array(
            array(1),
            array(1.23),
            array('string'),
            array(array('foo')),
            array(new \stdClass()),
            array(fopen('php://memory', 'r'))
        );
    }

    /**
     * @return array
     */
    public function dataInvalidEncodingValue()
    {
        return array(
            array(1),
            array(1.23),
            array(true),
            array(array('foo')),
            array(new \stdClass()),
            array(fopen('php://memory', 'r'))
        );
    }

    /**
     * @return array
     */
    public function dataInvalidJson()
    {
        return array(
            array(''),
            array('{]'),
            array('"?\udc4d"'),
            array('"\ud83d?"'),
        );
    }

    /**
     * @covers ::__construct
     * @covers ::setIgnoreByteOrderMark
     * @covers ::getIgnoreByteOrderMark
     */
    public function testIgnoreValidByteOrderMarkValues()
    {
        $decoder = new Decoder();
        static::assertTrue($decoder->getIgnoreByteOrderMark());

        $decoder = new Decoder(true);
        static::assertTrue($decoder->getIgnoreByteOrderMark());

        $decoder = new Decoder(false);
        static::assertFalse($decoder->getIgnoreByteOrderMark());
    }

    /**
     * @param mixed $value
     *
     * @dataProvider dataInvalidIgnoreBomValues
     *
     * @expectedException \Crossjoin\Json\Exception\InvalidArgumentException
     * @expectedExceptionCode 1478195542
     * @covers ::setIgnoreByteOrderMark
     */
    public function testIgnoreInvalidByteOrderMarkValues($value)
    {
        new Decoder($value);
    }

    /**
     * @param string $json
     *
     * @dataProvider dataInvalidEncodingValue
     *
     * @expectedException \Crossjoin\Json\Exception\InvalidArgumentException
     * @expectedExceptionCode 1478195652
     * @covers ::getEncoding
     */
    public function testEncodingValueOfInvalidType($json)
    {
        $decoder = new Decoder();
        $decoder->getEncoding($json);
    }

    /**
     * @param string $json
     * @param mixed $expectedData
     * @param string $expectedEncoding
     *
     * @dataProvider dataDecodeValidDataWithoutBom
     *
     * @covers ::decode
     * @covers ::getEncoding
     */
    public function testDecodeValidDataWithoutBom($json, $expectedData, $expectedEncoding)
    {
        $decoder = new Decoder(false);
        static::assertEquals($expectedEncoding, $decoder->getEncoding($json));
        static::assertEquals($expectedData, $decoder->decode($json));
    }

    /**
     * @param string $json
     * @param mixed $expectedData
     * @param string $expectedEncoding
     *
     * @dataProvider dataDecodeValidDataWithBom
     *
     * @covers ::decode
     * @covers ::getEncoding
     */
    public function testDecodingValidDataWithIgnoredBom($json, $expectedData, $expectedEncoding)
    {
        $decoder = new Decoder(true);
        static::assertEquals($expectedEncoding, $decoder->getEncoding($json));
        static::assertEquals($expectedData, $decoder->decode($json));
    }

    /**
     * @param string $json
     * @param string $expectedData
     *
     * @dataProvider dataDecodeValidDataWithBom
     *
     * @expectedException \Crossjoin\Json\Exception\EncodingNotSupportedException
     * @expectedExceptionCode 1478092834
     * @covers ::decode
     * @covers ::getEncoding
     */
    public function testDecodingValidDataWithPreservedBom($json, $expectedData, $expectedEncoding)
    {
        $decoder = new Decoder(false);
        $decoder->decode($json);
    }

    /**
     * @param string $json
     *
     * @dataProvider dataInvalidJson
     *
     * @expectedException \Crossjoin\Json\Exception\ConversionFailedException
     * @covers ::decode
     */
    public function testDecodeInvalidJson($json)
    {
        $decoder = new Decoder(false);
        $decoder->decode($json);
    }
}
