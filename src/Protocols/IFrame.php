<?php declare(strict_types = 1);

namespace IPub\WebSockets\Protocols;

/**
 * Communication frame interface
 *
 * @package        iPublikuj:WebSockets!
 * @subpackage     Protocols
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
interface IFrame extends IData
{

	/**
	 * Add incoming data to the frame from peer
	 *
	 * @param string $buffer
	 *
	 * @return void
	 */
	public function addBuffer(string $buffer): void;

	/**
	 * Is this the final frame in a fragmented message?
	 *
	 * @return bool
	 */
	public function isFinal(): bool;

	/**
	 * Is the payload masked?
	 *
	 * @return bool
	 */
	public function isMasked(): bool;

	/**
	 * @return int
	 */
	public function getOpCode(): int;

	/**
	 * 32-bit string
	 *
	 * @return string
	 */
	public function getMaskingKey(): string;

}
