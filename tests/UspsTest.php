<?php
 
use Bedard\Shipping\Usps;
use PHPUnit_Framework_Assert as Assert;
 
class UspsTest extends PHPUnit_Framework_TestCase {

    /**
     * @var string  A real user ID is only required for testCalculateRate().
     */
    private $userId = '123ABCDE4567';
 
    /**
     * Tests the construction of the Usps object
     */
    public function test_credentials()
    {
        $usps = new Usps($this->userId);
        $this->assertEquals($this->userId, Assert::readAttribute($usps, 'userId'));
    }

    public function test_testing_server()
    {
        $usps = new Usps($this->userId);
        $usps->useTestingServer();  
        $this->assertEquals('http://testing.shippingapis.com/ShippingAPI.dll', Assert::readAttribute($usps, 'endpoint'));
    }

    /**
     * Sets the package origin with a string
     */
    public function test_set_origin()
    {
        $usps = new Usps($this->userId);
        $usps->setOrigin('12345-6789');
        $this->assertEquals('12345-6789', Assert::readAttribute($usps, 'originPostalCode'));
    }

    /**
     * Exception is thrown if the origin is not valid
     * @expectedException   Exception
     */
    public function test_exception_thrown_for_invalid_postal_code()
    {
        $usps = new Usps($this->userId);
        $usps->setOrigin('Invalid');
    }

    /**
     * Set a domestic destination
     */
    public function test_set_domestic_destination()
    {
        $usps = new Usps($this->userId);
        $usps->setDestination(12345);
        $this->assertEquals('12345', Assert::readAttribute($usps, 'destinationPostalCode'));
        $this->assertEquals('US', Assert::readAttribute($usps, 'destinationCountry'));
    }

    /**
     * Exception is thrown if destination is set to US
     * @expectedException   Exception
     */
    public function test_exception_thrown_for_us_destination()
    {
        $usps = new Usps($this->userId);
        $usps->setDestination('US');
    }

    /**
     * Set an international destination
     */
    public function test_set_international_destination()
    {
        $usps = new Usps($this->userId);
        $usps->setDestination('Canada');
        $this->assertEquals(FALSE, Assert::readAttribute($usps, 'destinationPostalCode'));
        $this->assertEquals('Canada', Assert::readAttribute($usps, 'destinationCountry'));
    }

    /**
     * Set the dimensions
     */
    public function test_set_package_dimensions()
    {
        $usps = new Usps($this->userId);
        $dimensions = [
            'length'    => 1,
            'width'     => 2.5,
            'height'    => '3',
            'pounds'    => 2,
            'ounces'    => 4
        ];
        $usps->setDimensions($dimensions);
        $this->assertEquals($dimensions, Assert::readAttribute($usps, 'dimensions'));
    }

    /**
     * Check that weight dimensions are still set when omitted
     */
    public function test_set_package_dimensions_without_weight()
    {
        $usps = new Usps($this->userId);
        $usps->setDimensions([
            'length'    => 1,
            'width'     => 2.5,
            'height'    => '3',
        ]);
        $this->assertEquals([
            'length'    => 1,
            'width'     => 2.5,
            'height'    => '3',
            'pounds'    => 0,
            'ounces'    => 0
        ], Assert::readAttribute($usps, 'dimensions'));
    }

    /**
     * Ensure all dimensions keys are required
     * @expectedException   Exception
     */
    public function test_exception_thrown_for_missing_dimensions_key()
    {
        $usps = new Usps($this->userId);
        $usps->setDimensions([
            'length'    => 1,
            'width'     => 2,
            // 'height'    => 3,
            'ounces'    => 4
        ]);
    }

    /**
     * Ensure all dimensions keys are valid
     * @expectedException   Exception
     */
    public function test_exception_thrown_for_invalid_dimensions()
    {
        $usps = new Usps($this->userId);
        $usps->setDimensions([
            'length'    => 1,
            'width'     => 2,
            'height'    => 3,
            'ounces'    => 'Invalid'
        ]);
    }

    /**
     * Set the package value
     */
    public function test_set_value()
    {
        $usps = new Usps($this->userId);
        $usps->setValue(49.99);
        $this->assertEquals(49.99, Assert::readAttribute($usps, 'value'));
    }

    /**
     * Ensure package value is valid
     * @expectedException   Exception
     */
    public function test_exception_thrown_for_invalid_value()
    {
        $usps = new Usps($this->userId);
        $usps->setValue(-5);
    }

    /**
     * Parse a mocked domestic response
     */
    public function test_parse_domestic_response()
    {
        $mockResponse = file_get_contents(__DIR__.'/responses/usps_domestic.txt');
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXml($mockResponse);
        $usps = new USPS($this->userId);
        $reflectionOfRate = new ReflectionClass('Bedard\Shipping\Usps');
        $method = $reflectionOfRate->getMethod('parseDomesticResponse');
        $method->setAccessible(TRUE);
        $rates = $method->invokeArgs($usps, [$dom]);
        $this->assertEquals(count($rates), 28);
        foreach ($rates as $rate) {
            $this->assertTrue(array_key_exists('code', $rate));
            $this->assertTrue(array_key_exists('name', $rate));
            $this->assertTrue(array_key_exists('cost', $rate));
        }
    }

    /**
     * Parse a mocked international response
     */
    public function test_parse_international_response()
    {
        $mockResponse = file_get_contents(__DIR__.'/responses/usps_international.txt');
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXml($mockResponse);
        $usps = new USPS($this->userId);
        $reflectionOfRate = new ReflectionClass('Bedard\Shipping\Usps');
        $method = $reflectionOfRate->getMethod('parseInternationalResponse');
        $method->setAccessible(TRUE);
        $rates = $method->invokeArgs($usps, [$dom]);
        $this->assertEquals(count($rates), 20);
        foreach ($rates as $rate) {
            $this->assertTrue(array_key_exists('code', $rate));
            $this->assertTrue(array_key_exists('name', $rate));
            $this->assertTrue(array_key_exists('cost', $rate));
        }
    }

    /**
     * Exceptions should be thrown if we call calculate() prematurely
     * @expectedException   Exception
     */
    public function test_exception_thrown_for_missing_information()
    {
        $usps = new Usps($this->userId);
        $rates = $usps->useTestingServer()
            ->calculate();
    }
 
    /**
     * Parse an actual domestic response from the USPS testing server.
     * A valid userId is required to run the full test.
     */
    public function test_domestic_response()
    {
        $usps = new Usps($this->userId);
        $rates = $usps->useTestingServer()
            ->setOrigin('12345')
            ->setDestination('90210')
            ->setDimensions([
                'length'    => 1,
                'width'     => 2,
                'height'    => 3,
                'pounds'    => 1
            ])
            ->setValue(49.99)
            ->calculate();
        $this->assertTrue(is_array($rates));
        // $this->assertTrue(count($rates) > 1);
        // foreach ($rates as $rate) {
        //     $this->assertTrue(array_key_exists('code', $rate));
        //     $this->assertTrue(array_key_exists('name', $rate));
        //     $this->assertTrue(array_key_exists('cost', $rate));
        // }
    }

    /**
     * Parse an actual international response from the USPS testing server.
     * A valid userId is required to run the full test.
     */
    public function test_international_response()
    {
        $usps = new Usps($this->userId);
        $rates = $usps->useTestingServer()
            ->setOrigin('12345')
            ->setDestination('Canada')
            ->setDimensions([
                'length'    => 1,
                'width'     => 2,
                'height'    => 3,
                'pounds'    => 1
            ])
            ->setValue(49.99)
            ->calculate();
        $this->assertTrue(is_array($rates));
        // $this->assertTrue(count($rates) > 1);
        // foreach ($rates as $rate) {
        //     $this->assertTrue(array_key_exists('code', $rate));
        //     $this->assertTrue(array_key_exists('name', $rate));
        //     $this->assertTrue(array_key_exists('cost', $rate));
        // }
    }
}