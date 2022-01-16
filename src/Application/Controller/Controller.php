<?php declare(strict_types = 1);

namespace IPub\WebSockets\Application\Controller;

use Fig\Http;
use IPub\WebSockets\Application;
use IPub\WebSockets\Application\Responses;
use IPub\WebSockets\Exceptions;
use IPub\WebSockets\Router;
use Nette;
use Nette\Security as NS;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Reflector;
use stdClass;

/**
 * WebSockets application controller interface
 *
 * @package        iPublikuj:WebSockets!
 * @subpackage     Application
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 *
 * @property-read stdClass $payload
 * @property-read NS\User $user
 */
abstract class Controller implements IController
{

	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * Special parameter keys
	 *
	 * @internal
	 */
	public const ACTION_KEY = 'action';
	public const SIGNAL_KEY = 'signal';
	public const DEFAULT_ACTION = 'default';

	/** @var Application\Request */
	private $request;

	/** @var Responses\IResponse */
	private $response;

	/** @var stdClass */
	private $payload;

	/** @var bool */
	private $startupCheck = false;

	/** @var array */
	private $globalParams = [];

	/** @var array */
	private $params = [];

	/** @var string */
	private $action;

	/** @var string|null */
	private $signal;

	/** @var string */
	private $name;

	/** @var Nette\DI\Container */
	private $context;

	/** @var IControllerFactory */
	private $controllerFactory;

	/** @var Router\IRouter */
	private $router;

	/** @var Router\LinkGenerator */
	private $linkGenerator;

	/** @var NS\User */
	private $user;

	/**
	 * @param Nette\DI\Container|null $context
	 * @param IControllerFactory|null $controllerFactory
	 * @param Router\IRouter|null $router
	 * @param Router\LinkGenerator|null $linkGenerator
	 * @param NS\User|null $user
	 */
	public function injectPrimary(
		?Nette\DI\Container $context = null,
		?IControllerFactory $controllerFactory = null,
		?Router\IRouter $router = null,
		?Router\LinkGenerator $linkGenerator = null,
		?NS\User $user = null
	) {
		if ($this->controllerFactory !== null) {
			throw new Nette\InvalidStateException(sprintf('Method "%s" is intended for initialization and should not be called more than once.', __METHOD__));
		}

		$this->context = $context;
		$this->controllerFactory = $controllerFactory;
		$this->router = $router;
		$this->linkGenerator = $linkGenerator;
		$this->user = $user;
	}

	public function __construct()
	{
		$this->payload = new stdClass();
	}

	/**
	 * @param Application\Request $request
	 *
	 * @return Responses\IResponse
	 *
	 * @throws Exceptions\BadRequestException
	 * @throws Exceptions\BadSignalException
	 * @throws Exceptions\ForbiddenRequestException
	 * @throws Exceptions\InvalidStateException
	 * @throws ReflectionException
	 */
	public function run(Application\Request $request): Responses\IResponse
	{
		try {
			// STARTUP
			$this->request = $request;
			$this->payload = $this->payload ?? new stdClass();
			$this->name = $request->getControllerName();

			$this->initGlobalParameters();

			$this->checkRequirements(new ReflectionClass($this));

			$this->startup();

			if (!$this->startupCheck) {
				$class = (new ReflectionClass($this))->getMethod('startup')->getDeclaringClass()->getName();

				throw new Exceptions\InvalidStateException(sprintf('Method %s::startup() or its descendant doesn\'t call parent::startup().', $class));
			}

			if ($this->signal !== null) {
				if (!$this->tryCall(call_user_func([$this, 'formatSignalMethod'], $this->signal), $this->params)) {
					$class = static::class;

					throw new Exceptions\BadSignalException(sprintf('There is no handler for signal "%s" in class "%s".', $this->signal, $class));
				}
			}

			// calls $this->action<Action>()
			$this->tryCall(call_user_func([$this, 'formatActionMethod'], $this->action), $this->params);

			$this->sendPayload();

		} catch (Exceptions\AbortException $ex) {
			// SHUTDOWN
			$this->shutdown($this->response);
		}

		return $this->response;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Checks authorization
	 *
	 * @param mixed $element
	 *
	 * @return void
	 *
	 * @throws Exceptions\ForbiddenRequestException
	 * @throws Exceptions\InvalidStateException
	 */
	public function checkRequirements($element): void
	{
		$user = (array) $this->parseAnnotation($element, 'User');

		if (in_array('loggedIn', $user, true) && !$this->getUser()->isLoggedIn()) {
			throw new Exceptions\ForbiddenRequestException();
		}
	}

	/**
	 * @return stdClass
	 */
	public function getPayload(): stdClass
	{
		return $this->payload;
	}

	/**
	 * Sends payload to the output
	 *
	 * @return void
	 *
	 * @throws Exceptions\AbortException
	 */
	public function sendPayload(): void
	{
		if (isset($this->payload->data)) {
			$this->sendResponse(new Responses\MessageResponse($this->payload->data));
		}

		$this->sendResponse(new Responses\NullResponse());
	}

	/**
	 * Sends response and terminates presenter
	 *
	 * @param Responses\IResponse $response
	 *
	 * @return void
	 *
	 * @throws Exceptions\AbortException
	 */
	public function sendResponse(Responses\IResponse $response): void
	{
		$this->response = $response;

		$this->terminate();
	}

	/**
	 * Correctly terminates controller
	 *
	 * @return void
	 *
	 * @throws Exceptions\AbortException
	 */
	public function terminate(): void
	{
		throw new Exceptions\AbortException();
	}

	/**
	 * @param string $destination
	 * @param array $args
	 *
	 * @return string
	 *
	 * @throws Exceptions\InvalidLinkException
	 * @throws ReflectionException
	 */
	public function link(string $destination, array $args = []): string
	{
		return $this->linkGenerator->link($destination, $args);
	}

	/**
	 * Changes current action. Only alphanumeric characters are allowed
	 *
	 * @param string $action
	 *
	 * @return void
	 *
	 * @throws Exceptions\BadRequestException
	 */
	private function changeAction($action): void
	{
		if (is_string($action) && Nette\Utils\Strings::match($action, '#^[a-zA-Z0-9][a-zA-Z0-9_\x7f-\xff]*\z#')) {
			$this->action = $action;

		} else {
			throw new Exceptions\BadRequestException('Action name is not alphanumeric string.', Http\Message\StatusCodeInterface::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @return Nette\Security\User
	 *
	 * @throws Exceptions\InvalidStateException
	 */
	public function getUser(): Nette\Security\User
	{
		if (!$this->user) {
			throw new Exceptions\InvalidStateException('Service User has not been set.');
		}

		return $this->user;
	}

	/**
	 * Formats action method name
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	public static function formatActionMethod($action): string
	{
		return 'action' . $action;
	}

	/**
	 * Formats signal handler method name -> case sensitivity doesn't matter
	 *
	 * @param string $signal
	 *
	 * @return string|null
	 */
	public static function formatSignalMethod($signal): ?string
	{
		return $signal === null ? null : 'handle' . $signal; // intentionally ==
	}

	/**
	 * Converts list of arguments to named parameters
	 *
	 * @param string $class       class name
	 * @param string $method      method name
	 * @param array $args
	 * @param array $supplemental supplemental arguments
	 * @param array $missing      missing arguments
	 *
	 * @return void
	 *
	 * @throws Exceptions\InvalidLinkException
	 * @throws ReflectionException
	 *
	 * @internal
	 */
	public static function argsToParams(string $class, string $method, array &$args, array $supplemental = [], array &$missing = []): void
	{
		$i = 0;
		$rm = new ReflectionMethod($class, $method);

		foreach ($rm->getParameters() as $param) {
			[$type, $isClass] = Application\Reflection::getParameterType($param);
			$name = $param->getName();

			if (array_key_exists($i, $args)) {
				$args[$name] = $args[$i];
				unset($args[$i]);
				$i++;

			} elseif (array_key_exists($name, $args)) { // phpcs:ignore
				// continue with process

			} elseif (array_key_exists($name, $supplemental)) {
				$args[$name] = $supplemental[$name];
			}

			if (!isset($args[$name])) {
				if (!$param->isDefaultValueAvailable() && !$param->allowsNull() && $type !== 'null' && $type !== 'array') {
					$missing[] = $param;
					unset($args[$name]);
				}

				continue;
			}

			if (!Application\Reflection::convertType($args[$name], $type, $isClass)) {
				throw new Exceptions\InvalidLinkException(sprintf(
					'Argument $%s passed to %s() must be %s, %s given.',
					$name,
					$rm->getDeclaringClass()->getName() . '::' . $rm->getName(),
					$type === 'null' ? 'scalar' : $type,
					is_object($args[$name]) ? get_class($args[$name]) : gettype($args[$name])
				));
			}

			$def = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
			if ($args[$name] === $def || ($def === null && $args[$name] === '')) {
				$args[$name] = null; // value transmit is unnecessary
			}
		}

		if (array_key_exists($i, $args)) {
			throw new Exceptions\InvalidLinkException(sprintf('Passed more parameters than method %s::%s() expects.', $class, $rm->getName()));
		}
	}

	/**
	 * @return void
	 */
	protected function startup(): void
	{
		$this->startupCheck = true;
	}

	/**
	 * @param Responses\IResponse $response
	 *
	 * @return void
	 */
	protected function shutdown(Responses\IResponse $response): void
	{
	}

	/**
	 * Call method of object
	 *
	 * @param string $method
	 * @param array $params
	 *
	 * @return bool
	 *
	 * @throws Exceptions\BadRequestException
	 * @throws Exceptions\ForbiddenRequestException
	 * @throws Exceptions\InvalidStateException
	 * @throws ReflectionException
	 */
	protected function tryCall($method, array $params): bool
	{
		$rc = new ReflectionClass($this);

		if ($rc->hasMethod($method)) {
			$rm = $rc->getMethod($method);

			if ($rm->isPublic() && !$rm->isAbstract() && !$rm->isStatic()) {
				$this->checkRequirements($rm);
				$rm->invokeArgs($this, Application\Reflection::combineArgs($rm, $params));

				return true;
			}
		}

		return false;
	}

	/**
	 * Initializes $this->globalParams, $this->action. Called by run()
	 *
	 * @return void
	 *
	 * @throws Exceptions\BadRequestException
	 */
	private function initGlobalParameters(): void
	{
		// init $this->globalParams
		$this->globalParams = [];

		$selfParams = [];

		$params = $this->request->getParameters();

		foreach ($params as $key => $value) {
			if (!preg_match('#^((?:[a-z0-9_]+-)*)((?!\d+\z)[a-z0-9_]+)\z#i', $key, $matches)) {
				continue;

			} elseif (!$matches[1]) {
				$selfParams[$key] = $value;

			} else {
				$this->globalParams[substr($matches[1], 0, -1)][$matches[2]] = $value;
			}
		}

		$this->params = $selfParams;

		// init & validate $this->action & $this->view
		$this->changeAction($selfParams[self::ACTION_KEY] ?? self::DEFAULT_ACTION);

		if (isset($selfParams[self::SIGNAL_KEY])) {
			$this->signal = $selfParams[self::SIGNAL_KEY];
		}
	}

	/**
	 * Returns an annotation value
	 *
	 * @param Reflector $ref
	 * @param string $name
	 *
	 * @return array|bool
	 */
	private function parseAnnotation(Reflector $ref, string $name)
	{
		if (!$ref->getDocComment() || !preg_match_all('#[\\s*]@' . preg_quote($name, '#') . '(?:\(\\s*([^)]*)\\s*\)|\\s|$)#', $ref->getDocComment(), $m)) {
			return false;
		}

		static $tokens = ['true' => true, 'false' => false, 'null' => null];

		$res = [];

		foreach ($m[1] as $s) {
			$parts = preg_split('#\s*,\s*#', $s, -1, PREG_SPLIT_NO_EMPTY);

			foreach ($parts !== [] ? $parts : ['true'] as $item) {
				$res[] = array_key_exists($tmp = strtolower($item), $tokens) ? $tokens[$tmp] : $item;
			}
		}

		return $res;
	}

}
