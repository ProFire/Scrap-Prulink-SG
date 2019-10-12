<?php
declare(strict_types=1);
namespace ProFire\ScrapPrulinkSG;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

trait ScrapPrulinkSGTrait
{
    /**
     * 
     * Options
     * -------
     * startDate: Format is DD-MMM-YYYY
     * endDate: Format is DD-MMM-YYYY
     * selectedFunds: The fund ID
     * fundPriceType: Optional | Default:BID | Either BID or OFF
     * client.verify: Optional | The PEM file location for GuzzleHTTP to use for verify
     * 
     * @param $options Array Options to POST query
     * @param $success ResponseInterface
     * @param $failure RequestException
     * @return Promise
     */
    public function executeAsync(array $options, $success = null, $failure = null): Promise
    {
        /*DEBUG*/ //pr($options);exit;

        $clientOptions = [
            'base_uri' => 'https://pruaccess.prudential.com.sg/prulinkfund/viewFundPerformance.do',
        ];
        if (isset($options["client.verify"])) {
            $clientOptions["verify"] = $options["client.verify"];
        }
        $client = new client($clientOptions);

        $formParams = [
            "viewType" => "LIN",
            "fundPriceType" => "BID",
            "_selectedFunds" => "1",
            "remarks" => "",
        ];
        if (isset($options["fundPriceType"])) {
            $formParams["fundPriceType"] = $options["fundPriceType"];
        }

        //If the start and end date is of time class, convert to string first
        if (is_a($options["startDate"], "\DateTime") || is_a($options["startDate"], "\DateTimeImmutable")) {
            $options["startDate"] = $options["startDate"]->format("d-M-Y");
        }
        if (is_a($options["endDate"], "\DateTime") || is_a($options["endDate"], "\DateTimeImmutable")) {
            $options["endDate"] = $options["endDate"]->format("d-M-Y");
        }
        /*DEBUG*/ //pr($options);exit;
        
        return $client->postAsync("?", [
            "form_params" => array_merge($formParams, $options),
        ])
        ->then(function(ResponseInterface $res) 
            {
                /*DEBUG*/ //pr($res->getBody()->getContents());
                $xml = new \DOMDocument();
                libxml_use_internal_errors(true);
                $xml->loadHTML($res->getBody()->getContents(), LIBXML_NOWARNING);
                $rawChartInfo = json_decode($xml->getElementById("chartInfo")->getAttribute("value"), true);
                
                $chartData = [];
                foreach ($rawChartInfo["labels"] as $key => $date) {
                    $chartData[$key] = [
                        "date" => new Date($date),
                        "price" => $rawChartInfo["datasets"][0]["data"][$key],
                    ];
                }
                /*DEBUG*/ //pr($chartData);
                return $chartData;
            },
            function (RequestException $e) 
            {
                echo $e->getMessage() . "\n";
                echo $e->getRequest()->getMethod();
            }
        )
        ->then($success, $failure);
    }

    public function execute($options = [], $success = null, $failure = null)
    {
        return $this->executeAsync($options, $success, $failure)->wait();
    }
}