<?php

namespace ADT\ViaPhone;

use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Ramsey\Uuid\Uuid;

/**
 * https://viaphonev2.docs.apiary.io
 */
class ViaPhone
{
	const TYPE_SMS = 'message';
	const TYPE_CALL = 'call';

	const CALL_STATE_WAITING = 'waiting';
	const CALL_STATE_ONGOING = 'ongoing';
	const CALL_STATE_ANSWERED = 'answered';
	const CALL_STATE_NOT_ANSWERED = 'not_answered';
	const CALL_STATE_REFUSED = 'refused';

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
	protected function getUrl(string $path, array $queryParams = [])
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
	 * @return bool|object|string
	 * @throws \Exception
	 */
	protected function request(string $url, array $data = [], string $method = IRequest::GET)
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
			try {
				$optArray[CURLOPT_POSTFIELDS] = Json::encode($data);
			}
			catch (JsonException $e) {
				throw new \Exception("Malformed request data", 0, $e);
			}
		}

		curl_setopt_array($curl, $optArray);

		$response = curl_exec($curl);

		$curlInfo = curl_getinfo($curl);

		if ($curlInfo['http_code'] === 204) {
			return true;
		};

		$errno = curl_errno($curl);
		$error = curl_error($curl);

		curl_close($curl);

		if ($errno) {
			throw new \Exception($error, $errno);
		}

		if ($curlInfo['content_type'] === 'audio/mpeg') {
			return $response;
		}

		try {
			return (object) Json::decode($response);
		}
		catch (JsonException $e) {
			throw new \Exception("Server returned a malformed response", 0, $e);
		}
	}

	/**
	 * @param string $text
	 * @param string $contactPhoneNumber
	 * @param string|null $contactName
	 * @param object|string $device
	 * @param string|null $note
	 * @param string|null $uuid
	 * @return bool|object
	 * @throws \Exception
	 */
	public function sendSmsMessage(
		string $text,
		string $contactPhoneNumber,
		string $contactName = null,
		$device = null,
		?string $note = null,
		?string $uuid = null,
		?\DateTimeInterface $validTo = null
	)
	{
		$data = [
			'type' => self::TYPE_SMS,
			'uuid' => $uuid ?? Uuid::uuid4(),
			'text' => $text,
			'device' => is_object($device) ? $device->phone_number : $device,
			'contact' => [
				'phone_number' => $contactPhoneNumber,
				'name' => $contactName,
			],
			'note' => $note
		];

		if ($validTo) {
			$data['valid_to'] = $validTo->format('Y-m-d');
		}

		return $this->request($this->getUrl("records"), $data, IRequest::POST);
	}
	
	/**
	 * @param string $uuid
	 * @return object
	 * @throws \Exception
	 */
	public function getRecord(string $uuid)
	{
		return $this->request($this->getUrl("records/$uuid"), [], IRequest::GET);
	}

	/**
	 * @param array $criteria
	 * @param string $sortBy
	 * @param string $sortOrder
	 * @param int $limit
	 * @param int|null $offset
	 * @return array
	 * @throws \Exception
	 */
	public function getRecords(array $criteria = [], string $sortBy = 'updated_at', string $sortOrder = 'desc', int $limit = 100, int $offset = null)
	{
		$url = '?';

		foreach ($criteria as $criterionKey => $criterionValue) {
			if ($url !== '?') {
				$url .= '&';
			}

			if ($criterionKey == 'updated_at') {
				$criterionValue = $criterionValue->format('c');
			}
			$url .= $criterionKey . '=gte:' . urlencode($criterionValue);
		}

		if ($url !== '?') {
			$url .= '&';
		}

		$url .= 'sort=' . ($sortOrder === 'desc' ? '-' : '') . $sortBy;

		$response = $this->request($this->getUrl("records" . $url), ['limit' => $limit, 'offset' => $offset]);

		return $response->data ?? [];
	}

	/**
	 * @param string $phoneNumber
	 * @param string $name
	 * @param string $email
	 * @return object
	 * @throws \Exception
	 */
	public function addDevice(string $phoneNumber, string $name, string $email)
	{
		return $this->request($this->getUrl("devices"), ['phone_number' => $phoneNumber, 'name' => $name, 'email' => $email], IRequest::POST);
	}

	/**
	 * @param object|string $device
	 * @return null|object
	 * @throws \Exception
	 */
	public function sendDownloadLink($device)
	{
		if (!is_object($device)) {
			$device = $this->getDevice($device);
		}

		return $device ? $this->request($this->getUrl("devices/$device->uuid/requests"), ['type' => 'download-link'], IRequest::POST) : null;
	}

	/**
	 * @param $phoneNumber
	 * @return null|object
	 * @throws \Exception
	 */
	public function getDevice($phoneNumber)
	{
		$devices = $this->getDevices(['phone_number' => $phoneNumber]);
		if (isset($devices[0])) {
			return (object) $devices[0];
		}

		return null;
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws \Exception
	 */
	public function getDevices(array $params = [])
	{
		return $this->request($this->getUrl("devices"), $params, IRequest::GET)->data ?? [];
	}

	/**
	 * @param object|string $device
	 * @param array $data
	 * @return null|object
	 * @throws \Exception
	 */
	public function updateDevice($device, array $data)
	{
		if (!is_object($device)) {
			$device = $this->getDevice($device);
		}

		return $device ? $this->request($this->getUrl("devices/$device->uuid"), $data, IRequest::PATCH) : null;
	}

	/**
	 * @param string $uuid
	 * @return string|null
	 * @throws \Exception
	 */
	public function getCallRecord($uuid)
	{
		return $this->request($this->getUrl("records/$uuid/recording"), [], IRequest::GET);
	}

	/**
	 * @param string $contactPhoneNumber
	 * @param string|null $contactName
	 * @param object|string $device
	 * @return bool|object
	 * @throws \Exception
	 */
	public function call(string $contactPhoneNumber, string $contactName = null, $device = null)
	{
		$data = [
			'type' => static::TYPE_CALL,
			'uuid' => Uuid::uuid4(),
			'device' => is_object($device) ? $device->phone_number : $device,
			'contact' => [
				'phone_number' => $contactPhoneNumber,
				'name' => $contactName,
			],
			'call_state' => static::CALL_STATE_WAITING,
			'started_at' => (new \DateTime())->format('c'),
		];

		return $this->request($this->getUrl("records"), $data, IRequest::POST);
	}
}
