<?php declare(strict_types = 1);

/**
 * MessageEvent.php
 *
 * @copyright      More in LICENSE.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:WebSockets!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           15.11.19
 */

namespace IPub\WebSockets\Events\Application;

use IPub\WebSockets\Application;
use IPub\WebSockets\Entities;
use IPub\WebSockets\Http;
use Symfony\Contracts\EventDispatcher;

/**
 * Message received event
 *
 * @package        iPublikuj:WebSockets!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class MessageEvent extends EventDispatcher\Event
{

	/** @var Application\IApplication */
	private $application;

	/** @var Entities\Clients\IClient */
	private $client;

	/** @var Http\IRequest */
	private $httpRequest;

	/** @var string */
	private $message;

	/**
	 * @param Application\IApplication $application
	 * @param Entities\Clients\IClient $client
	 * @param Http\IRequest $httpRequest
	 * @param string $message
	 */
	public function __construct(
		Application\IApplication $application,
		Entities\Clients\IClient $client,
		Http\IRequest $httpRequest,
		string $message
	) {
		$this->application = $application;
		$this->client = $client;
		$this->httpRequest = $httpRequest;
		$this->message = $message;
	}

	/**
	 * @return Application\IApplication
	 */
	public function getApplication(): Application\IApplication
	{
		return $this->application;
	}

	/**
	 * @return Entities\Clients\IClient
	 */
	public function getClient(): Entities\Clients\IClient
	{
		return $this->client;
	}

	/**
	 * @return Http\IRequest
	 */
	public function getHttpRequest(): Http\IRequest
	{
		return $this->httpRequest;
	}

	/**
	 * @return string
	 */
	public function getMessage(): string
	{
		return $this->message;
	}

}
