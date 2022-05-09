<?php

namespace ADT\ViaPhone;

use GuzzleHttp\Client;
use Nette\Http\IRequest;
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

	protected string $url = 'https://api.viaphoneapp.com/v2/';
	protected string $lang = 'en';
	protected Client $client;
	protected array $headers;


	public function __construct(string $apiKey)
	{
		$this->client = new Client(['base_uri' => $this->url]);
		$this->headers = [
			'X-API-Key' => $apiKey,
			'Accept-Language', $this->lang,
		];
	}


	protected function request(string $url, string $method, array $data = [])
	{
		$options = ['headers' => $this->headers];
		if ($method === IRequest::GET) {
			$options['json'] = $data;
		} elseif($method === IRequest::POST || $method === IRequest::PATCH) {
			$options['json'] = $data;
		} else {
			throw new \Exception('Unsupported method type');
		}

		$response = $this->client->request($method, $url, $options);

		if ($response->getHeader('Content-Type')[0] === 'audio/mpeg') {
			return $response;
		}

		try {
			return (object) Json::decode($response->getBody());
		}
		catch (JsonException $e) {
			throw new \Exception("Server returned a malformed response", 0, $e);
		}
	}


	public function sendSmsMessage(
		string $text,
		string $contactPhoneNumber,
		string $contactName = null,
		$device = null,
		?string $note = null,
		?string $uuid = null,
		?int $validFor = null
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

		if ($validFor) {
			$data['valid_for'] = $validFor;
		}

		return $this->request('records', IRequest::POST, $data);
	}
	

	public function getRecord(string $uuid)
	{
		return $this->request("records/$uuid", IRequest::GET);
	}


	public function getRecords(
		array $criteria = [],
		string $sortBy = 'updated_at',
		string $sortOrder = 'desc',
		int $limit = 100,
		int $offset = null
	): array
	{
		$query = [
			'limit' => $limit,
			'offset' => $offset,
			'sort' => ($sortOrder === 'desc' ? '-' : '') . $sortBy
		];
		foreach ($criteria as $criterionKey => $criterionValue) {
			if ($criterionKey === 'updated_at') {
				$criterionValue = $criterionValue->format('c');
			}
			$query[$criterionKey] = $criterionValue;
		}


		$response = $this->request('records', IRequest::GET, $query);

		return $response->data ?? [];
	}


	public function addDevice(string $phoneNumber, string $name, string $email)
	{
		return $this->request('devices', IRequest::POST, ['phone_number' => $phoneNumber, 'name' => $name, 'email' => $email]);
	}


	public function sendDownloadLink($device)
	{
		if (!is_object($device)) {
			$device = $this->getDevice($device);
		}

		return $device ? $this->request("devices/$device->uuid/requests", IRequest::POST, ['type' => 'download-link']) : null;
	}


	public function getDevice($phoneNumber)
	{
		$devices = $this->getDevices(['phone_number' => $phoneNumber]);
		if (isset($devices[0])) {
			return (object) $devices[0];
		}

		return null;
	}


	public function getDevices(array $params = [])
	{
		return $this->request('devices', IRequest::GET, $params)->data ?? [];
	}


	public function updateDevice($device, array $data)
	{
		if (!is_object($device)) {
			$device = $this->getDevice($device);
		}

		return $device ? $this->request("devices/$device->uuid", IRequest::PATCH, $data) : null;
	}


	public function getCallRecord(string $uuid)
	{
		return $this->request("records/$uuid/recording", IRequest::GET);
	}


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

		return $this->request("records", IRequest::POST, $data);
	}
}
