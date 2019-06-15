<?php
/**
 * ServerCommand.php
 *
 * @copyright      More in license.md
 * @license        https://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:WebSockets!
 * @subpackage     Commands
 * @since          1.0.0
 *
 * @date           26.02.17
 */

declare(strict_types = 1);

namespace IPub\WebSockets\Commands;

use Symfony\Component\Console;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Style;
use Symfony\Component\Console\Output;

use Psr\Log;

use IPub\WebSockets\Exceptions;
use IPub\WebSockets\Logger;
use IPub\WebSockets\Server;

/**
 * WebSockets server command
 *
 * @package        iPublikuj:WebSockets!
 * @subpackage     Commands
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class ServerCommand extends Console\Command\Command
{
	/**
	 * @var Server\Server
	 */
	private $server;

	/**
	 * @var Log\LoggerInterface|Log\NullLogger|NULL
	 */
	private $logger;

	/**
	 * @param Server\Server $server
	 * @param Log\LoggerInterface|NULL $logger
	 * @param string|NULL $name
	 */
	public function __construct(
		Server\Server $server,
		?Log\LoggerInterface $logger = NULL,
		?string $name = NULL
	) {
		parent::__construct($name);

		$this->server = $server;
		$this->logger = $logger === NULL ? new Log\NullLogger : $logger;
	}

	/**
	 * @return void
	 */
	protected function configure() : void
	{
		$this
			->setName('ipub:websockets:start')
			->setDescription('Start WebSocket server.');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(Input\InputInterface $input, Output\OutputInterface $output) : void
	{
		$io = new Style\SymfonyStyle($input, $output);

		$io->text([
			'',
			'+------------------+',
			'| WebSocket server |',
			'+------------------+',
			'',
		]);

		if ($this->logger instanceof Logger\Console) {
			$this->logger->setFormatter(new Logger\Formatter\Symfony($io));
		}

		try {
			$this->server->run();

		} catch (Exceptions\TerminateException $ex) {
			$this->server->stop();
		}
	}
}
