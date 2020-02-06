<?php

namespace ADT\ViaPhone;

use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\Utils\Json;
use Ramsey\Uuid\Uuid;

/**
 * https://viaphonev2.docs.apiary.io
 */
class ViaPhone
{
	const TYPE_SMS = 'message';
	const TYPE_CALL = 'call';

	/** @var string */
	protected $url = 'https://api.viaphoneapp.com/v2/';

	/** @var string */
	protected $secret;

	/** @var string */
	protected $lang = 'en';

	/**
	 * ViaPhone constructor.
	 *
	 * @param string $apiKey
	 */
	public function __construct($apiKey)
	{
		$this->secret = $apiKey;
	}

	/**
	 * @param string $path
	 * @param array $queryParams
	 * @return string
	 */
	protected function getUrl($path, array $queryParams = [])
	{
		$url = $this->url . $path;

		if ($queryParams) {
			$url = new Url($url);
			$url->setQuery($queryParams);
		}

		return (string)$url;
	}

	/**
	 * @param string $url
	 * @param array $data
	 * @param string $method
	 *
	 * @return object
	 */
	protected function request($url, $data = null, $method = IRequest::GET)
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
				"X-API-key: " . $this->secret,
				"Accept-Language: " . $this->lang,
			],
		];

		if (! empty($data)) {
			$optArray[CURLOPT_POSTFIELDS] = Json::encode($data);
		}

		curl_setopt_array($curl, $optArray);

		$response = curl_exec($curl);

		$errno = curl_errno($curl);
		$error = curl_error($curl);

		curl_close($curl);

		if ($errno) {
			throw new \Exception($error, $errno);
		}

		return Json::decode($response);
	}

	/**
	 * @param string $text
	 * @param string $contactPhoneNumber
	 * @param string|null $contactName
	 * @param string|null $devicePhoneNumber
	 *
	 * @return object
	 */
	public function sendSmsMessage($text, $contactPhoneNumber, $contactName = null, $devicePhoneNumber = null)
	{
		$data = [
			'type' => self::TYPE_SMS,
			'uuid' => Uuid::uuid4(),
			'text' => $text,
			'contact' => [
				'phone_number' => $contactPhoneNumber,
				'name' => $contactName,
			],
			'device' => $devicePhoneNumber,
			'is_outgoing' => true,
			'valid_to' => (new \DateTime('+1 day'))->format('Y-m-d'),
		];

		return $this->request($this->getUrl("records"), $data, IRequest::POST);
	}

	/**
	 * @param array $criteria
	 * @param string $sortBy
	 * @param string $sortOrder
	 * @param int $limit
	 * @param int|null $offset
	 *
	 * @return object
	 */
	public function getRecords(array $criteria = [], $sortBy = 'updated_at', $sortOrder = 'desc', $limit = 100, $offset = null)
	{
		$url = '?';

		foreach ($criteria as $criterionKey => $criterionValue) {
			if ($url !== '?') {
				$url .= '&';
			}

			if ($criterionKey = 'updated_at') {
				$criterionValue = $criterionValue->format('c');
			}
			$url .= $criterionKey . '=gte:' . urlencode($criterionValue);
		}

		if ($url !== '?') {
			$url .= '&';
		}

		$url .= 'sort=' . ($sortOrder === 'desc' ? '-' : '') . $sortBy;
		$rq = $this->getUrl("records" . $url);
		$response = $this->request($this->getUrl($rq), ['limit' => $limit, 'offset' => $offset]);

		return $response->data ?? [];
	}

	/**
	 * @param string $phoneNumber
	 * @param string $name
	 * @param string $email
	 *
	 * @return object
	 */
	public function addDevice($phoneNumber, $name, $email)
	{
		return $this->request($this->getUrl("devices"), ['phoneNumber' => $phoneNumber, 'name' => $name, 'email' => $email], IRequest::POST);
	}

	/**
	 * @param string $phoneNumber
	 */
	public function sendDownloadLink($phoneNumber)
	{
		$device = $this->getDevice($phoneNumber);

		if ($device) {
			$this->request($this->getUrl("devices/$device->uuid/requests"), ['type' => 'download-link'], IRequest::POST)
		}
	}

	/**
	 * @param string $phoneNumber
	 *
	 * @return object|null
	 */
	public function getDevice($phoneNumber)
	{
		return $this->getDevices(['phone_number' => $phoneNumber])[0] ?? null;
	}

	/**
	 * @param array $params
	 *
	 * @return array
	 */
	public function getDevices($params = [])
	{
		$devices = $this->request($this->getUrl("devices"), $params, IRequest::GET);

		return $devices->data ?? [];
	}

	/**
	 * @param string $phoneNumber
	 * @param array $data
	 *
	 * @return object|null
	 */
	public function updateDevice($phoneNumber, array $data)
	{
		$device = $this->getDevice($phoneNumber);

		return $device ? $this->request($this->getUrl("devices/$device->uuid"), $data, IRequest::PATCH) : null;
	}

}
