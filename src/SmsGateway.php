<?php

namespace ADT\Viaphone;

use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\Utils\Json;
use Nette\Utils\Strings;

/**
 * https://viaphonev2.docs.apiary.io
 */
class SmsGateway
{

	/** @var string  */
	protected $url = 'https://api.viaphoneapp.com/v2/';

	/** @var string */
	protected $singleRecipient;

	/** @var string */
	protected $smsSenderApiKey;

	/** @var string */
	protected $lang = 'en';

	/**
	 * SmsGateway constructor.
	 *
	 * @param array $smsSenderOptions
	 * [
	 * 	'apiKey' => string,
	 * 	'singleRecipient' => callback|string
	 * ]
	 */
	public function __construct($smsSenderOptions) {
		$this->smsSenderApiKey = $smsSenderOptions['apiKey'];

		if (isset($smsSenderOptions['singleRecipient'])) {
			if (is_callable($smsSenderOptions['singleRecipient'])) {
				$this->singleRecipient = call_user_func($smsSenderOptions['singleRecipient']);
			} else {
				$this->singleRecipient = $smsSenderOptions['singleRecipient'];
			}
		} else {
			$this->singleRecipient = null;
		}
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

		if ($this->singleRecipient !== null) {
			if (isset($data['contact']['name'])) {
				$data['text'] = $data['contact']['name'] . ': ' . $data['text'];
			}
			$data['contact']['phone_number'] = $this->singleRecipient;
			$data['device'] = $this->singleRecipient;
		}

		if (! empty($data)) {
			$optArray[CURLOPT_POSTFIELDS] = json_encode($data);
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
	public function sendSms($data) {

		return $this->request($this->getUrl("records"), $data, IRequest::POST);
	}

	/**
	 * Odstranění první 0 (kvůli SK číslům, které mohou začínat na 0)
	 *
	 * @param string $phone
	 * @return string
	 */
	public static function replaceCode($phone) {

		if (Strings::startsWith($phone, 0)) {
			return mb_substr($phone, 1);
		}

		return $phone;
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