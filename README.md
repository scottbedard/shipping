# USPS Shipping Calculator
[![Build Status](https://travis-ci.org/scottbedard/shipping.svg?branch=master)](https://travis-ci.org/scottbedard/shipping)

A simple PHP wrapper for USPS shipping calulations.

### Instructions
The first step to requesting a shipping quote is injecting your USPS Web Tools ID.

```php
use Bedard\Shipping\Usps;
$shipment = new Usps('123ABCDE4567');
```

If you would like to run the calculator on the USPS testing server, call ```useTestingServer()```.

```php
$shipment->useTestingServer();
```

Once this is done, it's time to build up the data you'll be sending to USPS. Length, width, and height are all specified in inches. Pounds, ounces, and setValue() are all optional.

```php
$rates = $shipment
    ->setOrigin('12345')
    ->setDestination('90210')
    ->setDimensions([
        'length'    => 12,
        'width'     => 12,
        'height'    => 4,
        'pounds'    => 0,
        'ounces'    => 1
    ])
    ->setValue(49.99)
    ->calculate();
```

The above is an example of a domestic shipment. To request an international rate, simply pass the destination country into ```setDestination()``` instead of a postal code.

```php
$shipment->setDestination('Canada');
```

Once you've called ```calculate()```, shipping rates will be returned in the following format. Results will be ordered by cost in ascending order.

```
 1 => 
    array (size=3)
      'code' => string '01' (length=2)
      'name' => string 'First-Class Mail Large Envelope' (length=31)
      'cost' => float 0.98
  2 => 
    array (size=3)
      'code' => string '00' (length=2)
      'name' => string 'First-Class Mail Parcel' (length=23)
      'cost' => float 2.32
  3 => 
    array (size=3)
      'code' => string '1' (length=1)
      'name' => string 'Priority Mail 2-Day' (length=19)
      'cost' => float 5.75
```