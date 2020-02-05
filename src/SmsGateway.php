<?php

namespace ADT\Viaphone;

use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

/**
 * https://viaphonev2.docs.apiary.io
 */
class SmsGateway
{

	/** @var string  */
	protected $url = 'https://api.viaphoneapp.com/v2/';

	/** @var string */
	protected $smsSenderApiKey;

	/** @var string */
	protected $lang = 'en';

	/**
	 * SmsGateway constructor.
	 *
	 * @param string $apiKey
	 */
	public function __construct($apiKey) {
		$this->smsSenderApiKey = $apiKey;
	}

	/**
	 * @param string $path
	 * @param array $queryParams
	 * @return string
	 */
	protected function getUrl($path, array $queryParams = []) {
		$url = $this->url . $path;

		if ($queryParams) {
			$url = new Url($url);
			$url->setQuery($queryParams);
		}

		return (string) $url;
	}

	/**
	 * @param string $url
	 * @param array $data
	 * @param string $method
	 *
	 * @return string|NULL|array
	 */
	protected function request($url, $data = NULL, $method = IRequest::GET)
	{
		$curl = curl_init();

		$optArray = [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,

			CURLOPT_HTTPHEADER => [
				"Cache-Control: no-cache",
				"Content-Type: application/json",
				"X-API-key: " . $this->smsSenderApiKey,
				"Accept-Language: " . $this->lang,
			],
		];

		if (! empty($data)) {
			$optArray[CURLOPT_POSTFIELDS] = Json::encode($data);
		}

		curl_setopt_array($curl, $optArray);

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			throw new \Exception('Nepodarilo sa odoslať požiadavok.' . "\n" . print_r($err, TRUE));
		}

		return !empty($response) ? $response : [];
	}

	/**
	 * @param array $data
	 *
	 * @return bool
	 */
	public function send($data) {

		$data['type'] = 'message';
		$data['is_outgoing'] = true;
		$data['valid_to'] = (new DateTime('+1 day'))->format('Y-m-d');

		return $this->request($this->getUrl("records"), $data, IRequest::POST);
	}

	/**
	 * @param DateTime $updatedAt
	 * @return array|mixed
	 */
	public function getRecords($updatedAt) {
		$updatedAfterParam = $updatedAt ? '?updated_at=gte:' . urlencode($updatedAt->format('c')) : '';
		// TODO: Docasne type
		// $rq = $this->getUrl("records" . $updatedAfterParam . ($updatedAfterParam ? '&' : '?') . 'sort=updated_at');
		$rq = $this->getUrl("records" . $updatedAfterParam . ($updatedAfterParam ? '&' : '?') . 'sort=updated_at&type=message');
		$rawData = $this->request($rq);

		$data = !empty($rawData) ? Json::decode($rawData, true) : [];

		return $data['data'];
	}

	/**
	 * @param array $data
	 * @return array|NULL|string
	 */
	public function addNumber(array $data) {
		return Json::decode($this->request($this->getUrl("devices"), $data, IRequest::POST));
	}

	public function sendMessageWithDownloadLink($phoneNumber) {
		$device = $this->readDevice($phoneNumber);

		return $device ? (bool)$this->request($this->getUrl("devices/$device->uuid/requests"), ['type' => 'download-link'], IRequest::POST) : false;
	}

	public function readDevice($phoneNumber) {
		return $this->readAllDevices(['phone_number' => $phoneNumber])[0] ?? false;
	}

	public function readAllDevices($params = []) {
		$devices = Json::decode($this->request($this->getUrl("devices"), $params, IRequest::GET));

		return $devices->data ?? [];
	}

	public function updateDevice($phoneNumber, array $data) {
		$device = $this->readDevice($phoneNumber);

		return $device ? Json::decode($this->request($this->getUrl("devices/$device->uuid"), $data, IRequest::PATCH)) : false;
	}

}