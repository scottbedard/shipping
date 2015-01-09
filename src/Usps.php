<?php namespace Bedard\Shipping;

/*
 * Domestic Codes
 *    00  First-Class Mail Parcel
 *    01  First-Class Mail Large Envelope
 *    02  First-Class Mail Stamped Letter
 *    03  First-Class Mail Postcards
 *    1   Priority Mail
 *    2   Priority Mail Express Hold For Pickup
 *    3   Priority Mail Express
 *    4   Standard Post
 *    6   Media Mail Parcel
 *    7   Library Mail Parcel
 *    13  Priority Mail Express Flat Rate Envelope
 *    15  First-Class Mail Large Postcards
 *    16  Priority Mail Flat Rate Envelope
 *    17  Priority Mail Medium Flat Rate Box
 *    22  Priority Mail Large Flat Rate Box
 *    27  Priority Mail Express Flat Rate Envelope Hold For Pickup
 *    28  Priority Mail Small Flat Rate Box
 *    29  Priority Mail Padded Flat Rate Envelope
 *    30  Priority Mail Express Legal Flat Rate Envelope
 *    31  Priority Mail Express Legal Flat Rate Envelope Hold For Pickup
 *    33  Priority Mail Hold For Pickup
 *    34  Priority Mail Large Flat Rate Box Hold For Pickup
 *    35  Priority Mail Medium Flat Rate Box Hold For Pickup
 *    36  Priority Mail Small Flat Rate Box Hold For Pickup
 *    37  Priority Mail Flat Rate Envelope Hold For Pickup
 *    38  Priority Mail Gift Card Flat Rate Envelope
 *    39  Priority Mail Gift Card Flat Rate Envelope Hold For Pickup
 *    40  Priority Mail Window Flat Rate Envelope
 *    41  Priority Mail Window Flat Rate Envelope Hold For Pickup
 *    42  Priority Mail Small Flat Rate Envelope
 *    43  Priority Mail Small Flat Rate Envelope Hold For Pickup
 *    44  Priority Mail Legal Flat Rate Envelope
 *    45  Priority Mail Legal Flat Rate Envelope Hold For Pickup
 *    46  Priority Mail Padded Flat Rate Envelope Hold For Pickup
 *    47  Priority Mail Regional Rate Box A
 *    48  Priority Mail Regional Rate Box A Hold For Pickup
 *    49  Priority Mail Regional Rate Box B
 *    50  Priority Mail Regional Rate Box B Hold For Pickup
 *    53  First-Class Package Service Hold For Pickup
 *    55  Priority Mail Express Flat Rate Boxes
 *    56  Priority Mail Express Flat Rate Boxes Hold For Pickup
 *    58  Priority Mail Regional Rate Box C
 *    59  Priority Mail Regional Rate Box C Hold For Pickup
 *    61  First-Class Package Service
 *    62  Priority Mail Express Padded Flat Rate Envelope
 *    63  Priority Mail Express Padded Flat Rate Envelope Hold For Pickup
 *    78  First-Class Mail Metered Letter
 *
 * International Codes
 *    1   Priority Mail Express International
 *    2   Priority Mail International
 *    4   Global Express Guaranteed (GXG)
 *    8   Priority Mail International Flat Rate Envelope
 *    9   Priority Mail International Medium Flat Rate Box
 *    10  Priority Mail Express International Flat Rate Envelope
 *    11  Priority Mail International Large Flat Rate Box
 *    12  USPS GXG Envelopes
 *    15  First-Class Package International Service
 *    16  Priority Mail International Small Flat Rate Box
 *    17  Priority Mail Express International Legal Flat Rate Envelope
 *    18  Priority Mail International Gift Card Flat Rate Envelope
 *    19  Priority Mail International Window Flat Rate Envelope
 *    20  Priority Mail International Small Flat Rate Envelope
 *    22  Priority Mail International Legal Flat Rate Envelope
 *    23  Priority Mail International Padded Flat Rate Envelope
 *    24  Priority Mail International DVD Flat Rate priced box
 *    25  Priority Mail International Large Video Flat Rate priced box
 *    26  Priority Mail Express International Flat Rate Boxes
 *    27  Priority Mail Express International Padded Flat Rate Envelope
 */

use DOMDocument;
use Exception;

class Usps {
    
    /**
     * @var string              USPS credentials
     */
    protected $userId;

    /**
     * @var string              USPS endpoint
     */
    protected $endpoint = 'http://production.shippingapis.com/ShippingAPI.dll';

    /**
     * @var string              Origin postal code
     */
    protected $originPostalCode;

    /**
     * @var string              Destination postal code
     */
    protected $destinationPostalCode;

    /**
     * @var string              Destination country code
     */
    protected $destinationCountry;

    /**
     * @var array               Package dimensions [ length, width, height, ounces ]
     */
    protected $dimensions;

    /**
     * @var string (numeric)    Package value
     */
    protected $value = 0;

    /**
     * Set up credentials and environment
     * @param   string  $userId
     */
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Changes the USPS endpoint to the testing server
     */
    public function useTestingServer()
    {
        $this->endpoint = 'http://testing.shippingapis.com/ShippingAPI.dll';

        return $this;
    }

    /**
     * Sets the origin postal code
     * @param   integer / string
     */
    public function setOrigin($originPostalCode)
    {
        $this->originPostalCode = $this->validatePostalCode($originPostalCode);

        return $this;
    }

    /**
     * Sets the destination postal code and country
     * @param   integer / string    $destination
     */
    public function setDestination($destination)
    {
        if ($destination == 'US' || $destination == 'USA' || $destination == 'United States') {
            throw new Exception('The destination must be a postal code for domestic shipments.');
        }

        // Domestic
        if (!preg_match('/[a-z]/i', $destination)) {
           $this->destinationCountry = 'US';
           $this->destinationPostalCode = $this->validatePostalCode($destination);
        }

        // International
        else {
            $this->destinationCountry = $destination;
            $this->destinationPostalCode = FALSE;
        }

        return $this;
    }

    /**
     * Validates a domestic postal code
     * @param   integer / string
     * @return  boolean
     */
    private function validatePostalCode($postalCode)
    {
        if (!preg_match('/^\d{5}(?:[-\s]\d{4})?$/', strval($postalCode))) {
            throw new Exception('Invalid postal code "'.strval($postalCode).'".');
        }

        return strval($postalCode);
    }

    /**
     * Validate and set the package dimensions
     */
    public function setDimensions($dimensions)
    {
        // Set omitted weight dimensions to zero
        if (!isset($dimensions['pounds'])) {
            $dimensions['pounds'] = 0;
        }
        if (!isset($dimensions['ounces'])) {
            $dimensions['ounces'] = 0;
        }

        // Check for and validate required dimension keys
        $keys = ['length', 'width', 'height', 'pounds', 'ounces'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $dimensions)) {
                throw new Exception('Missing dimensions key "'.$key.'".');
            }
            if (!is_numeric($dimensions[$key]) || $dimensions[$key] < 0) {
                throw new Exception('Invalid dimensions value "'.$key.'".');
            }
        }

        // Convert the weight dimensions if needed
        $dimensions['ounces'] += $dimensions['pounds'] * 16;
        $dimensions['pounds'] = floor($dimensions['ounces'] / 16);
        $dimensions['ounces'] -= $dimensions['pounds'] * 16;

        $this->dimensions = $dimensions;

        return $this;
    }

    /**
     * Sets the package value
     */
    public function setValue($value)
    {
        if (!is_numeric($value) || $value < 0) {
            throw new Exception('Invalid package value.');
        }

        $this->value = number_format($value, 2);

        return $this;
    }

    /**
     * Prepares the XML to send to USPS
     * @return  string (XML)
     */
    private function prepareData()
    {
        // Prepare domestic data
        if ($this->destinationCountry == 'US') {
            $data = 
                'API=RateV4&XML=<RateV4Request USERID="'.$this->userId.'">
                    <Revision/>
                    <Package ID="1">
                        <Service>ONLINE</Service>
                        <ZipOrigination>'.$this->originPostalCode.'</ZipOrigination>
                        <ZipDestination>'.$this->destinationPostalCode.'</ZipDestination>
                        <Pounds>'.$this->dimensions['pounds'].'</Pounds>
                        <Ounces>'.$this->dimensions['ounces'].'</Ounces>
                        <Container>VARIABLE</Container>
                        <Size>REGULAR</Size>
                        <Machinable>TRUE</Machinable>
                    </Package>
                </RateV4Request>';
        }

        // Prepare international data
        else {
            $data = 
                'API=IntlRateV2&XML=<IntlRateV2Request USERID="'.$this->userId.'"> 
                    <Package ID="1ST"> 
                        <Pounds>'.$this->dimensions['pounds'].'</Pounds> 
                        <Ounces>'.$this->dimensions['ounces'].'</Ounces> 
                        <Machinable>True</Machinable> 
                        <MailType>All</MailType> 
                        <GXG> 
                            <POBoxFlag>N</POBoxFlag> 
                            <GiftFlag>Y</GiftFlag> 
                        </GXG>
                        <ValueOfContents>'.$this->value.'</ValueOfContents> 
                        <Country>'.$this->destinationCountry.'</Country> 
                        <Container>RECTANGULAR</Container> 
                        <Size>REGULAR</Size> 
                        <Width>'.$this->dimensions['width'].'</Width>
                        <Length>'.$this->dimensions['length'].'</Length>
                        <Height>'.$this->dimensions['height'].'</Height>
                        <Girth>0</Girth> 
                        <CommercialFlag>N</CommercialFlag> 
                    </Package> 
                </IntlRateV2Request>';
        }

        return $data;
    }

    /**
     * Processes domestic repsponses
     * @return  array
     */
    private function parseDomesticResponse(DOMDocument $dom)
    {
        $rates = [];
        $postage_list = $dom->getElementsByTagName('Postage');

        foreach ($postage_list as $postage) {
            $code = $postage->getAttribute('CLASSID');
            $cost = $postage->getElementsByTagName('Rate')->item(0)->nodeValue;
            if ($cost == 0) continue;
            $name = preg_replace('/&lt;(.*)&gt;/is', '', $postage->getElementsByTagName('MailService')->item(0)->nodeValue);

            // Fix duplicate class IDs
            if ($code === '0') {
                if ($name == 'First-Class Mail Parcel')         $code = '00';
                if ($name == 'First-Class Mail Large Envelope') $code = '01';
                if ($name == 'First-Class Mail Stamped Letter') $code = '02';
                if ($name == 'First-Class Mail Postcards')      $code = '03';
            }

            $rates[] = [
                'code' => $code,
                'name' => $name,
                'cost' => (float) $cost,
            ];
        }

        return $rates;
    }

    /**
     * Processes international repsponses
     * @return  array
     */
    private function parseInternationalResponse(DOMDocument $dom)
    {
        $rates = [];
        $postage_list = $dom->getElementsByTagName('Service');

        foreach ($postage_list as $postage) {
            $code = $postage->getAttribute('ID');
            $cost = $postage->getElementsByTagName('Postage')->item(0)->nodeValue;
            if ($cost == 0) continue;
            $name = preg_replace('/&lt;(.*)&gt;/is', '', $postage->getElementsByTagName('SvcDescription')->item(0)->nodeValue);

            $rates[] = [
                'code' => $code,
                'name' => $name,
                'cost' => (float) $cost,
            ];
        }

        return $rates;
    }

    /**
     * Send the rate request off to USPS and parse the response
     * @return  array
     */
    public function calculate()
    {
        // Ensure we have an origin, destination, dimensions
        if (is_null($this->originPostalCode) ||
            is_null($this->destinationPostalCode) ||
            is_null($this->destinationCountry ||
            is_null($this->dimensions))) {
            throw new Exception('Failed to calculate rate, missing required information.');
        }

        // Build our XML data
        $data = $this->prepareData();

        // Send the request to USPS
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
        $response = curl_exec($ch);

        // Load the XML from our response
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXml($response);

        // Pass dom to the appropriate parser
        $rates = $this->destinationCountry == 'US'
            ? $this->parseDomesticResponse($dom)
            : $this->parseInternationalResponse($dom);

        // Sort and return the results
        $cost = [];
        foreach ($rates as $key => $row) {
            $cost[$key] = $row['cost'];
        }
        array_multisort($cost, SORT_ASC, $rates);
        return $rates;
    }
}