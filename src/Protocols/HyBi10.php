<?php declare(strict_types = 1);

namespace IPub\WebSockets\Protocols;

use IPub\WebSockets\Http;

/**
 * HyBi10 webSocket protocol
 *
 * @package        iPublikuj:WebSockets!
 * @subpackage     Protocols
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class HyBi10 extends RFC6455
{

	/**
	 * {@inheritdoc}
	 */
	public function getVersion(): int
	{
		return 6;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isVersion(Http\IRequest $httpRequest): bool
	{
		$version = (int) (string) $httpRequest->getHeader('Sec-WebSocket-Version');

		return $version >= 6 && $version < 13;
	}

}
