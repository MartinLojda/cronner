<?php

declare(strict_types=1);

namespace stekycz\Cronner\Tasks;


use DateTime;
use DateTimeInterface;
use Nette\Reflection\Method;
use ReflectionClass;
use stekycz\Cronner\ITimestampStorage;

final class Task
{
	use \Nette\SmartObject;

	/** @var object */
	private $object;

	/** @var Method */
	private $method;

	/** @var ITimestampStorage */
	private $timestampStorage;

	/** @var Parameters|null */
	private $parameters;

	/** @var DateTimeInterface|null */
	private $now;


	/**
	 * Creates instance of one task.
	 *
	 * @param object $object
	 */
	public function __construct($object, Method $method, ITimestampStorage $timestampStorage, DateTimeInterface $now = null)
	{
		$this->object = $object;
		$this->method = $method;
		$this->timestampStorage = $timestampStorage;
		$this->setNow($now);
	}


	public function getObjectName(): string
	{
		return get_class($this->object);
	}


	public function getMethodReflection(): Method
	{
		return $this->method;
	}


	public function getObjectPath(): string
	{
		$reflection = new ReflectionClass($this->object);

		return $reflection->getFileName();
	}


	/**
	 * Returns True if given parameters should be run.
	 */
	public function shouldBeRun(DateTimeInterface $now = null): bool
	{
		if ($now === null) {
			$now = new DateTime();
		}

		$parameters = $this->getParameters();
		if (!$parameters->isTask()) {
			return false;
		}
		$this->timestampStorage->setTaskName($parameters->getName());

		return $parameters->isInDay($now)
			&& $parameters->isInTime($now)
			&& $parameters->isNextPeriod($now, $this->timestampStorage->loadLastRunTime())
			&& $parameters->isInDayOfMonth($now);
	}


	public function getName(): string
	{
		return $this->getParameters()->getName();
	}


	public function __invoke(DateTimeInterface $now)
	{
		$this->method->invoke($this->object);
		$this->timestampStorage->setTaskName($this->getName());
		$this->timestampStorage->saveRunTime($now);
		$this->timestampStorage->setTaskName();
	}


	public function getNow()
	{
		if ($this->now === null) {
			$this->now = new \DateTime();
		}

		return $this->now;
	}


	public function setNow(?DateTimeInterface $now): void
	{
		if ($now === null) {
			$now = new \DateTime();
		}

		$this->now = $now;
	}


	/**
	 * Returns instance of parsed parameters.
	 */
	private function getParameters(): Parameters
	{
		if ($this->parameters === null) {
			$this->parameters = new Parameters(Parameters::parseParameters($this->method, $this->getNow()));
		}

		return $this->parameters;
	}
}
