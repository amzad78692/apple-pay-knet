<?php

namespace Amzad\ApplePayKnet\Services;

use Amzad\ApplePayKnet\Exceptions\KnetException;

class KnetGateway
{
    /** @var string */
    private $endpoint;

    /** @var string */
    private $id;

    /** @var string */
    private $password;

    /** @var string */
    private $responseUrl;

    /** @var string */
    private $errorUrl;

    public function __construct(
        string $endpoint,
        string $id,
        string $password,
        string $responseUrl,
        string $errorUrl
    ) {
        $this->endpoint    = $endpoint;
        $this->id          = $id;
        $this->password    = $password;
        $this->responseUrl = $responseUrl;
        $this->errorUrl    = $errorUrl;
    }

    /**
     * Submit an Apple Pay payment authorization to KNET.
     *
     * The Apple Pay token fields are mapped to KNET's UDF fields exactly as
     * required by the KNET payment pipe:
     *   udf8  = Apple Pay transactionIdentifier
     *   udf9  = Apple Pay paymentData  (JSON-encoded)
     *   udf10 = Apple Pay paymentMethod (JSON-encoded)
     *
     * @param  string $amount     Payment amount in KWD (e.g. "5.250")
     * @param  string $trackId    Unique order / reference ID
     * @param  array  $appleToken The token object from Apple Pay (event.payment.token)
     * @return array              Parsed KNET response fields
     *
     * @throws KnetException
     */
    public function authorize(string $amount, string $trackId, array $appleToken): array
    {
        $udf8  = $appleToken['transactionIdentifier'] ?? '';
        $udf9  = json_encode($appleToken['paymentData']   ?? []);
        $udf10 = json_encode($appleToken['paymentMethod'] ?? []);

        $xml = <<<XML
            <request>
                <id>{$this->id}</id>
                <password>{$this->password}</password>
                <action>1</action>
                <currency>414</currency>
                <langid>EN</langid>
                <amt>{$amount}</amt>
                <trackid>{$trackId}</trackid>
                <udf8>{$udf8}</udf8>
                <udf9>{$udf9}</udf9>
                <udf10>{$udf10}</udf10>
                <errorURL>{$this->errorUrl}</errorURL>
                <responseURL>{$this->responseUrl}</responseURL>
            </request>
            XML;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->endpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/xml',
                'Accept: application/xml',
                'Content-Length: ' . strlen($xml),
            ],
        ]);

        $response  = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlErrno) {
            throw KnetException::connectionFailed($curlError);
        }

        // KNET returns a plain XML fragment; wrap it so simplexml can parse it
        $wrappedXml = "<response>{$response}</response>";
        $xmlObject  = @simplexml_load_string($wrappedXml);

        if ($xmlObject === false) {
            throw KnetException::connectionFailed('Invalid XML in KNET response: ' . $response);
        }

        $responseArray = json_decode(json_encode($xmlObject), true);

        // KNET success: trackid is present in the response
        if (empty($responseArray['trackid'])) {
            throw KnetException::authorizationFailed(
                (string) ($responseArray['result'] ?? 'UNKNOWN'),
                (string) ($responseArray['error_text'] ?? 'Authorization failed — no trackid in KNET response')
            );
        }

        return $responseArray;
    }
}

